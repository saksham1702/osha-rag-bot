"""
Chat endpoint for OSHA RAG queries.
"""
import hashlib
import logging
from fastapi import APIRouter
from pydantic import BaseModel

from src.services.rag_chain import query_rag_chain
from src.utils.cache import retrieval_cache

router = APIRouter()
logger = logging.getLogger(__name__)


class ChatRequest(BaseModel):
    message: str


class CitationResponse(BaseModel):
    url: str
    title: str
    section: str


class ChatResponse(BaseModel):
    answer: str
    citations: list[CitationResponse]


@router.post("/chat", response_model=ChatResponse)
async def chat(request: ChatRequest):
    """
    Main chat endpoint using Groq Llama 3.3 70B.
    Retrieves OSHA context and generates answers with citations.
    """
    cache_key = hashlib.md5(request.message.lower().strip().encode()).hexdigest()
    cached = retrieval_cache.get(cache_key)

    if cached:
        logger.info(f"Cache hit for question: {request.message[:50]}...")
        return cached

    logger.info(f"Processing question: {request.message[:50]}...")
    result = await query_rag_chain(request.message)

    retrieval_cache.set(cache_key, result)

    return result
