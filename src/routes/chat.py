"""
Chat endpoint for OSHA RAG queries with conversation history.
"""
import hashlib
import logging
from fastapi import APIRouter
from pydantic import BaseModel
from typing import Optional

from src.services.rag_chain import query_rag_chain
from src.utils.cache import retrieval_cache

router = APIRouter()
logger = logging.getLogger(__name__)


class ChatMessage(BaseModel):
    role: str
    content: str


class ChatRequest(BaseModel):
    message: str
    history: Optional[list[ChatMessage]] = []


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
    Supports conversation history for contextual responses.
    """
    cache_key = hashlib.md5(request.message.lower().strip().encode()).hexdigest()

    # Only use cache if no history (first message)
    if not request.history:
        cached = retrieval_cache.get(cache_key)
        if cached:
            logger.info(f"Cache hit for question: {request.message[:50]}...")
            return cached

    logger.info(f"Processing question with {len(request.history)} history messages: {request.message[:50]}...")

    # Pass history to RAG chain
    result = await query_rag_chain(request.message, history=request.history)

    # Only cache if no history
    if not request.history:
        retrieval_cache.set(cache_key, result)

    return result
