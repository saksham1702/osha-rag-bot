"""
RAG chain for OSHA regulatory queries using Groq Llama 3.3 70B.
Retrieves relevant documents and generates answers with citations.
Supports conversation history for contextual responses.
"""
from langchain_core.output_parsers import StrOutputParser
from langchain_core.prompts import ChatPromptTemplate
from langchain_qdrant import QdrantVectorStore
from typing import Optional

from src.config import COLLECTION_NAME
from src.db.qdrant_client import get_qdrant_client
from src.services.embeddings_local import get_embeddings
from src.services.llm_groq import get_groq_llm

SYSTEM_PROMPT = """You are a friendly OSHA safety expert chatting with someone about workplace safety.

{history_section}

ANSWERING STRATEGY - READ THIS CAREFULLY:
1. If the question is about OUR CONVERSATION itself (examples: "what did I ask?", "my first question", "what was my last message", "what was that about"):
   → Look at the CONVERSATION HISTORY above and answer from there
   → DO NOT search the OSHA INFORMATION for this type of question

2. If the question is about OSHA regulations, workplace safety, or compliance:
   → Use the OSHA INFORMATION below to answer
   → Also use CONVERSATION HISTORY to understand context and pronouns

WRITING STYLE:
- Talk naturally like you're helping a colleague
- Skip formal phrases like "according to" or "based on"
- Keep paragraphs short and readable
- Add [Source: URL] for OSHA facts

OSHA INFORMATION:
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


def _format_history(history: list) -> str:
    """Format conversation history for the prompt."""
    if not history:
        return ""

    formatted_lines = ["CONVERSATION HISTORY (for context):"]

    # Only include last 5 messages to avoid token limits
    recent_history = history[-5:] if len(history) > 5 else history

    for msg in recent_history:
        # Handle both Pydantic objects and dicts
        if hasattr(msg, 'role'):
            role = msg.role
            content = msg.content
        else:
            role = msg.get("role", "user")
            content = msg.get("content", "")

        if role == "user":
            formatted_lines.append(f"User: {content}")
        elif role == "assistant":
            formatted_lines.append(f"Assistant: {content}")

    formatted_lines.append("")
    return "\n".join(formatted_lines)


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


async def query_rag_chain(question: str, history: Optional[list[dict]] = None) -> dict:
    """
    Running RAG pipeline: retrieve -> format context -> generate answer.
    Returns the answer and citation list.

    Args:
        question: The user's current question
        history: List of previous messages (max last 5 used)
                 Format: [{"role": "user", "content": "..."}, {"role": "assistant", "content": "..."}]
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

    # Format conversation history if provided
    history_section = _format_history(history or [])

    chain = prompt | llm | StrOutputParser()

    answer = await chain.ainvoke({
        "context": context,
        "question": question,
        "history_section": history_section,
    })

    citations = _extract_citations(docs)

    return {
        "answer": answer,
        "citations": citations,
    }
