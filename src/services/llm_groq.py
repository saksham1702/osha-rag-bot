"""
Groq LLM service using Llama 3.3 70B model.
Implements LangChain-compatible interface via langchain-groq.
"""
from langchain_groq import ChatGroq

from src.config import GROQ_API_KEY


def get_groq_llm(temperature: float = 0.3, max_tokens: int = 1024):
    """
    Returns a configured Groq Llama 3.3 70B LLM instance.

    Uses the langchain-groq package for integration.
    Model: llama-3.3-70b-versatile (70B parameters, fast inference)
    """
    if not GROQ_API_KEY:
        raise ValueError("GROQ_API_KEY is not configured. Please set it in .env file.")

    return ChatGroq(
        model="llama-3.3-70b-versatile",
        groq_api_key=GROQ_API_KEY,
        temperature=temperature,
        max_tokens=max_tokens,
    )
