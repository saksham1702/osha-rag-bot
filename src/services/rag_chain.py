"""
RAG chain for OSHA regulatory queries using Groq Llama 3.3 70B.
Retrieves relevant documents and generates answers with citations.
"""
from langchain_core.output_parsers import StrOutputParser
from langchain_core.prompts import ChatPromptTemplate
from langchain_qdrant import QdrantVectorStore

from src.config import COLLECTION_NAME
from src.db.qdrant_client import get_qdrant_client
from src.services.embeddings_local import get_embeddings
from src.services.llm_groq import get_groq_llm

SYSTEM_PROMPT = """You are an OSHA regulatory assistant. Your role is to answer questions
about OSHA laws, regulations, and workplace safety standards.

RULES:
- ONLY use the provided context to answer questions
- If the context does not contain enough information, say so clearly
- Always cite the source URL for every fact you reference
- Format citations as [Source: URL] at the end of each relevant statement
- Be precise and factual, do not speculate beyond the provided context
- If multiple sources are relevant, cite all of them

CONTEXT:
{context}

USER QUESTION: {question}"""

prompt = ChatPromptTemplate.from_template(SYSTEM_PROMPT)


def _format_docs_with_citations(docs) -> str:
    """Formatting retrieved documents with their source metadata for the prompt."""
    formatted_parts = []
    for i, doc in enumerate(docs):
        source_url = doc.metadata.get("source_url", "Unknown source")
        page_title = doc.metadata.get("page_title", "")
        section = doc.metadata.get("section_heading", "")

        header = f"[Document {i + 1}]"
        if page_title:
            header += f" Title: {page_title}"
        if section:
            header += f" | Section: {section}"
        header += f"\nSource: {source_url}"

        formatted_parts.append(f"{header}\n{doc.page_content}")

    return "\n\n---\n\n".join(formatted_parts)


def _extract_citations(docs) -> list[dict]:
    """Extracting unique citation info from retrieved documents."""
    seen_urls = set()
    citations = []
    for doc in docs:
        url = doc.metadata.get("source_url", "")
        if url and url not in seen_urls:
            seen_urls.add(url)
            citations.append({
                "url": url,
                "title": doc.metadata.get("page_title", ""),
                "section": doc.metadata.get("section_heading", ""),
            })
    return citations


def get_retriever(k: int = 5):
    """Building a Qdrant retriever for similarity search."""
    client = get_qdrant_client()
    embeddings = get_embeddings()
    vector_store = QdrantVectorStore(
        client=client,
        collection_name=COLLECTION_NAME,
        embedding=embeddings,
    )
    return vector_store.as_retriever(search_kwargs={"k": k})


async def query_rag_chain(question: str) -> dict:
    """
    Running RAG pipeline: retrieve -> format context -> generate answer.
    Returns the answer and citation list.
    """
    retriever = get_retriever()
    llm = get_groq_llm()

    docs = await retriever.ainvoke(question)

    if not docs:
        return {
            "answer": "No relevant OSHA regulations were found for your question. "
                      "Please try rephrasing or ask about a specific OSHA topic.",
            "citations": [],
        }

    context = _format_docs_with_citations(docs)
    chain = prompt | llm | StrOutputParser()

    answer = await chain.ainvoke({
        "context": context,
        "question": question,
    })

    citations = _extract_citations(docs)

    return {
        "answer": answer,
        "citations": citations,
    }
