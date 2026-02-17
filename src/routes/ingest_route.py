"""
Ingestion route for triggering OSHA content crawling and embedding.
"""
from fastapi import APIRouter, BackgroundTasks
import logging

from src.services.ingest import run_osha_ingestion

router = APIRouter()
logger = logging.getLogger(__name__)


@router.post("/ingest/osha")
async def ingest_osha(background_tasks: BackgroundTasks):
    """Triggering OSHA content ingestion. Running in background to avoid timeout."""
    logger.info("Starting OSHA ingestion...")

    # Running ingestion in the background
    background_tasks.add_task(run_osha_ingestion)

    return {
        "status": "accepted",
        "message": "OSHA ingestion started in background. Check logs for progress.",
    }
