"""
OSHA web crawler and ingestion pipeline.
Fetching pages from osha.gov/laws-regs, cleaning HTML, chunking text,
embedding locally via sentence-transformers, and upserting to Qdrant with rich metadata for citations.
"""
import hashlib
import logging
from urllib.parse import urljoin, urlparse

import httpx
from bs4 import BeautifulSoup
from langchain_core.documents import Document
from langchain_text_splitters import RecursiveCharacterTextSplitter

from src.config import (
    CHUNK_OVERLAP,
    CHUNK_SIZE,
    COLLECTION_NAME,
    MAX_INGEST_PAGES,
    OSHA_BASE_URL,
    OSHA_LAWS_REGS_PATH,
)
from src.db.qdrant_client import get_qdrant_client
from src.services.embeddings_local import get_embeddings

logger = logging.getLogger(__name__)

# -- Text splitter configured once --
text_splitter = RecursiveCharacterTextSplitter(
    chunk_size=CHUNK_SIZE,
    chunk_overlap=CHUNK_OVERLAP,
    separators=["\n\n", "\n", ". ", " ", ""],
)


def _compute_chunk_hash(content: str, url: str) -> str:
    """Computing a deterministic hash for deduplication based on content and source URL."""
    raw = f"{url}::{content}"
    return hashlib.sha256(raw.encode()).hexdigest()


def _check_robots_txt(base_url: str, path: str) -> bool:
    """
    Checking robots.txt to verify crawling is permitted for the given path.
    robots.txt is a standard file at a site's root that tells crawlers
    which pages they are allowed or disallowed from accessing.
    """
    headers = {
        "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36",
        "Accept": "*/*",
        "Referer": "https://www.google.com/",
    }
    try:
        resp = httpx.get(f"{base_url}/robots.txt", headers=headers, timeout=10)
        if resp.status_code != 200:
            # No robots.txt found, assuming crawling is allowed
            return True

        disallowed_paths = []
        current_agent_applies = False
        for line in resp.text.splitlines():
            line = line.strip().lower()
            if line.startswith("user-agent:"):
                agent = line.split(":", 1)[1].strip()
                current_agent_applies = agent == "*"
            elif line.startswith("disallow:") and current_agent_applies:
                disallowed = line.split(":", 1)[1].strip()
                if disallowed:
                    disallowed_paths.append(disallowed)

        for disallowed in disallowed_paths:
            if path.startswith(disallowed):
                logger.warning(f"Path {path} is disallowed by robots.txt")
                return False
        return True
    except Exception as e:
        logger.warning(f"Failed to fetch robots.txt: {e}. Proceeding with crawl.")
        return True


def _clean_html(soup: BeautifulSoup) -> str:
    """Removing navigation, footer, scripts, and style tags. Returning clean text."""
    for tag in soup.find_all(["nav", "footer", "script", "style", "header", "aside"]):
        tag.decompose()
    return soup.get_text(separator="\n", strip=True)


def _extract_page_metadata(soup: BeautifulSoup, url: str) -> dict:
    """Extracting metadata from the page for citation support."""
    title = ""
    title_tag = soup.find("title")
    if title_tag:
        title = title_tag.get_text(strip=True)

    h1 = ""
    h1_tag = soup.find("h1")
    if h1_tag:
        h1 = h1_tag.get_text(strip=True)

    meta_desc = ""
    meta_tag = soup.find("meta", attrs={"name": "description"})
    if meta_tag:
        meta_desc = meta_tag.get("content", "")

    return {
        "source_url": url,
        "page_title": title or h1,
        "section_heading": h1,
        "meta_description": meta_desc,
        "domain": "osha.gov",
        "content_type": "laws-regs",
    }


def _get_existing_hashes() -> set:
    """Fetching all existing chunk hashes from Qdrant for deduplication."""
    client = get_qdrant_client()
    existing_hashes = set()
    try:
        # Scrolling through all points to collect hashes
        offset = None
        while True:
            result = client.scroll(
                collection_name=COLLECTION_NAME,
                limit=100,
                offset=offset,
                with_payload=True,
                with_vectors=False,
            )
            points, next_offset = result
            for point in points:
                chunk_hash = point.payload.get("chunk_hash")
                if chunk_hash:
                    existing_hashes.add(chunk_hash)
            if next_offset is None:
                break
            offset = next_offset
    except Exception as e:
        logger.warning(f"Could not fetch existing hashes: {e}")

    return existing_hashes


async def crawl_osha_pages(max_pages: int = MAX_INGEST_PAGES) -> list[dict]:
    """
    Crawling OSHA laws-regs pages starting from the base path.
    Returning a list of dicts with 'url', 'html', and 'metadata' keys.
    """
    if not _check_robots_txt(OSHA_BASE_URL, OSHA_LAWS_REGS_PATH):
        logger.error("Crawling disallowed by robots.txt")
        return []

    start_url = f"{OSHA_BASE_URL}{OSHA_LAWS_REGS_PATH}"
    visited = set()
    to_visit = [start_url]
    pages = []

    headers = {
        "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36",
        "Accept": "text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8",
        "Accept-Language": "en-US,en;q=0.9",
        "Accept-Encoding": "gzip, deflate, br",
        "DNT": "1",
        "Connection": "keep-alive",
        "Upgrade-Insecure-Requests": "1",
        "Sec-Fetch-Dest": "document",
        "Sec-Fetch-Mode": "navigate",
        "Sec-Fetch-Site": "none",
        "Sec-Fetch-User": "?1",
        "Cache-Control": "max-age=0",
        "Referer": "https://www.google.com/",
    }

    async with httpx.AsyncClient(timeout=30, follow_redirects=True, headers=headers) as client:
        while to_visit and len(pages) < max_pages:
            url = to_visit.pop(0)
            if url in visited:
                continue
            visited.add(url)

            try:
                resp = await client.get(url)
                if resp.status_code != 200:
                    logger.warning(f"Skipping {url} (status {resp.status_code})")
                    continue

                soup = BeautifulSoup(resp.text, "html.parser")
                metadata = _extract_page_metadata(soup, url)
                clean_text = _clean_html(soup)

                if len(clean_text.strip()) < 50:
                    continue

                pages.append({
                    "url": url,
                    "text": clean_text,
                    "metadata": metadata,
                })
                logger.info(f"Crawled: {url} ({len(clean_text)} chars)")

                # Discovering internal links under /laws-regs/
                for a_tag in soup.find_all("a", href=True):
                    href = a_tag["href"]
                    full_url = urljoin(url, href)
                    parsed = urlparse(full_url)

                    is_osha = "osha.gov" in parsed.netloc
                    is_laws_regs = parsed.path.startswith("/laws-regs")
                    not_visited = full_url not in visited
                    no_fragment = not parsed.fragment

                    if is_osha and is_laws_regs and not_visited and no_fragment:
                        to_visit.append(full_url)

            except Exception as e:
                logger.error(f"Error crawling {url}: {e}")
                continue

    logger.info(f"Crawling complete. Total pages: {len(pages)}")
    return pages


async def process_and_upsert(pages: list[dict]) -> dict:
    """
    Processing crawled pages: chunking, hashing for dedup, embedding, and upserting.
    Attaching rich metadata to each chunk for citation support.
    Returning stats dict with counts.
    """
    from langchain_qdrant import QdrantVectorStore

    embeddings = get_embeddings()
    existing_hashes = _get_existing_hashes()

    all_documents = []
    skipped = 0

    for page in pages:
        chunks = text_splitter.split_text(page["text"])
        for i, chunk in enumerate(chunks):
            chunk_hash = _compute_chunk_hash(chunk, page["url"])

            if chunk_hash in existing_hashes:
                skipped += 1
                continue

            # Building rich metadata for citations
            metadata = {
                **page["metadata"],
                "chunk_index": i,
                "chunk_hash": chunk_hash,
                "total_chunks": len(chunks),
            }

            doc = Document(page_content=chunk, metadata=metadata)
            all_documents.append(doc)

    if all_documents:
        # Upserting via LangChain QdrantVectorStore
        client = get_qdrant_client()
        vector_store = QdrantVectorStore(
            client=client,
            collection_name=COLLECTION_NAME,
            embedding=embeddings,
        )
        vector_store.add_documents(documents=all_documents)

    stats = {
        "pages_processed": len(pages),
        "chunks_added": len(all_documents),
        "chunks_skipped_dedup": skipped,
    }
    logger.info(f"Ingestion stats: {stats}")
    return stats


async def run_osha_ingestion(max_pages: int = MAX_INGEST_PAGES) -> dict:
    """
    Full ingestion pipeline: crawl -> process -> upsert.
    Called by both the API endpoint and the weekly cron job.
    """
    logger.info("Starting OSHA ingestion pipeline...")
    pages = await crawl_osha_pages(max_pages=max_pages)
    if not pages:
        logger.warning("No pages crawled. Ingestion skipped.")
        return {"pages_processed": 0, "chunks_added": 0, "chunks_skipped_dedup": 0}

    stats = await process_and_upsert(pages)
    logger.info(f"Ingestion complete: {stats}")
    return stats
