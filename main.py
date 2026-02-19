"""
OSHA RAG Bot - FastAPI Entry Point.
"""
import logging
from contextlib import asynccontextmanager
from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware

from src.db.qdrant_client import ensure_collection
from src.routes import chat, health, ingest_route

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(name)s: %(message)s",
)
logger = logging.getLogger(__name__)


@asynccontextmanager
async def lifespan(app: FastAPI):
    """Manage startup and shutdown events."""
    logger.info("Initializing OSHA RAG Bot...")
    ensure_collection()
    logger.info("OSHA RAG Bot is ready.")
    yield
    logger.info("OSHA RAG Bot shutting down.")


app = FastAPI(
    title="OSHA RAG Bot",
    description="Retrieval-Augmented Generation bot for OSHA laws and regulations",
    version="2.0.0",
    lifespan=lifespan,
)

app.add_middleware(
    CORSMiddleware,
    allow_origins=[
        "https://staging.knowella.com",
        "https://knowella.com",
        "https://apirg.knowella.com",
        "http://localhost:3000",  # For local testing
    ],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

app.include_router(chat.router, tags=["Chat"])
app.include_router(ingest_route.router, tags=["Ingestion"])
app.include_router(health.router, tags=["Health"])


@app.get("/")
async def root():
    return {
        "app": "OSHA RAG Bot",
        "version": "2.0.0",
        "docs": "/docs",
    }
