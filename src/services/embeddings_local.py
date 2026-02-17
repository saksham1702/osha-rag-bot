"""
Local embedding service using sentence-transformers.
Runs MiniLM-L6-v2 directly in the FastAPI container.
"""
from sentence_transformers import SentenceTransformer
from langchain_core.embeddings import Embeddings


class LocalEmbeddings(Embeddings):
    """LangChain-compatible embeddings using local sentence-transformers."""

    def __init__(self, model_name: str = "all-MiniLM-L6-v2"):
        self.model = SentenceTransformer(model_name)

    def embed_documents(self, texts: list[str]) -> list[list[float]]:
        """Embed a list of documents."""
        embeddings = self.model.encode(texts, normalize_embeddings=True)
        return embeddings.tolist()

    def embed_query(self, text: str) -> list[float]:
        """Embed a single query."""
        embedding = self.model.encode([text], normalize_embeddings=True)
        return embedding[0].tolist()


def get_embeddings() -> LocalEmbeddings:
    """Return local embeddings instance."""
    return LocalEmbeddings()
