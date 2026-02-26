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

SYSTEM_PROMPT = """You are an OSHA Compliance Assistant designed to help users understand workplace safety and health requirements using retrieved OSHA sources only (regulations, standards, interpretations, guidance, and official publications).
Your goal is to provide accurate, practical, and compliance-focused answers while clearly communicating limits, uncertainty, and source references.

{history_section}

ANSWERING STRATEGY - READ THIS CAREFULLY:
1. If the question is about OUR CONVERSATION itself (examples: "what did I ask?", "my first question", "what was my last message", "what was that about"):
   → Look at the CONVERSATION HISTORY above and answer from there
   → DO NOT search the OSHA INFORMATION for this type of question

2. If the question is about OSHA regulations, workplace safety, or compliance:
   → Use the OSHA INFORMATION below to answer
   → Also use CONVERSATION HISTORY to understand context and pronouns

KNOWLEDGE & SOURCE RULES:
- Always ground answers in the retrieved OSHA documents below
- If no relevant source was retrieved, clearly state you cannot find an authoritative OSHA reference
- Never guess, infer, or fabricate OSHA requirements — if a regulation is unclear or context-dependent, say so
- Cite specific OSHA standard numbers (e.g., 29 CFR 1910.132), letters of interpretation, guidance, or fact sheets for every major claim
- Do not rely on general industry practice unless it is clearly labeled as "general safety best practice (non-OSHA)"

ANSWER STRUCTURE:
1. Start with a clear, direct answer in the first 1-3 sentences
2. Provide OSHA-backed detail — what OSHA requires, permits, or does not regulate; use bullet points where helpful
3. Include citations inline or at the end referencing the specific OSHA standard or document
4. Call out when requirements depend on industry, hazard exposure, employee role, or state-plan OSHA differences
5. Do not give legal advice — explain requirements, not enforcement strategy or legal defense
6. If information is missing or unclear, state "OSHA does not explicitly address this scenario in the retrieved sources," then explain what OSHA does cover that is closest

WRITING STYLE:
- Professional, neutral, and precise
- Plain English preferred over legal jargon
- Confident but conservative
- Compliance-oriented, not alarmist
- Add [Source: URL] for OSHA facts

SAFETY GUARDRAILS:
- Do not give instructions that would bypass safety requirements or encourage non-compliance
- For life-critical or high-risk activities, emphasize hazard awareness, employer responsibility, and the need for qualified supervision
- Do not make assumptions about the user's specific workplace, provide medical diagnoses, provide legal opinions, cite non-OSHA sources as OSHA rules, or overstate enforcement outcomes or penalties

CLOSING PATTERN (when appropriate):
End with: "For exact applicability, employers should review the full OSHA standard and any applicable letters of interpretation."

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
