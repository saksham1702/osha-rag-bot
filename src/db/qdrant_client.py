from qdrant_client import QdrantClient
from qdrant_client.http.models import Distance, VectorParams

from src.config import COLLECTION_NAME, EMBEDDING_DIM, QDRANT_URL

# Singleton client instance
_client = None


def get_qdrant_client():
    """Return a singleton QdrantClient connected to the configured URL."""
    global _client
    if _client is None:
        _client = QdrantClient(url=QDRANT_URL)
    return _client


def ensure_collection():
    """Create the OSHA collection if it does not already exist."""
    client = get_qdrant_client()
    existing = [c.name for c in client.get_collections().collections]
    if COLLECTION_NAME not in existing:
        client.create_collection(
            collection_name=COLLECTION_NAME,
            vectors_config=VectorParams(
                size=EMBEDDING_DIM,
                distance=Distance.COSINE,
            ),
        )
        print(f"Created Qdrant collection: {COLLECTION_NAME}")
    else:
        print(f"Qdrant collection already exists: {COLLECTION_NAME}")
