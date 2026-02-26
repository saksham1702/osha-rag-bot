import os

from dotenv import load_dotenv

load_dotenv()

# -- Qdrant --
QDRANT_URL = os.getenv("QDRANT_URL", "http://qdrant:6333")
COLLECTION_NAME = "osha_laws_regs"
EMBEDDING_DIM = 384  # MiniLM-L6-v2 output dimension

# -- Chunking --
CHUNK_SIZE = 1000
CHUNK_OVERLAP = 200

# -- Auth --
INGEST_TOKEN = os.getenv("INGEST_TOKEN", "change-me-to-a-random-secret")

# -- Application --
MAX_INGEST_PAGES = int(os.getenv("MAX_INGEST_PAGES", "500"))

# -- OSHA Crawling --
OSHA_BASE_URL = "https://www.osha.gov"
OSHA_LAWS_REGS_PATH = "/laws-regs"
OSHA_PUBLICATIONS_PATH = "/publications"

# -- LLM API --
GROQ_API_KEY = os.getenv("GROQ_API_KEY", "")

# -- Proxy Settings (for scraping) --
PROXY_ENABLED = os.getenv("PROXY_ENABLED", "false").lower() == "true"
PROXY_URL = os.getenv("PROXY_URL", "")  # Format: http://username:password@proxy-server:port
