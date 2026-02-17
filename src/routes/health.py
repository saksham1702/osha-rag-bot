"""
Health check and metrics endpoints.
Verifying Qdrant connectivity and collection status.
"""
import logging

from fastapi import APIRouter

from src.config import COLLECTION_NAME
from src.db.qdrant_client import get_qdrant_client

router = APIRouter()
logger = logging.getLogger(__name__)


@router.get("/health")
async def health_check():
    """Health check endpoint."""
    health_status = {"status": "ok", "services": {}}

    try:
        client = get_qdrant_client()
        collections = client.get_collections().collections
        health_status["services"]["qdrant"] = {
            "status": "ok",
            "collections": len(collections),
        }
    except Exception as e:
        health_status["services"]["qdrant"] = {"status": "error", "message": str(e)}
        health_status["status"] = "degraded"
        logger.error(f"Qdrant health check failed: {e}")

    return health_status


@router.get("/metrics")
async def metrics():
    """Returning basic stats about the OSHA collection."""
    try:
        client = get_qdrant_client()
        info = client.get_collection(COLLECTION_NAME)
        return {
            "collection": COLLECTION_NAME,
            "total_vectors": info.points_count,
            "vectors_count": info.vectors_count,
            "status": info.status.value if info.status else "unknown",
        }
    except Exception as e:
        logger.error(f"Metrics fetch failed: {e}")
        return {
            "collection": COLLECTION_NAME,
            "total_vectors": 0,
            "error": str(e),
        }
