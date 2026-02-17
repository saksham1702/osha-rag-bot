# OSHA RAG Bot

AI-powered Q&A system for OSHA (Occupational Safety and Health Administration) regulations using Retrieval-Augmented Generation (RAG).

## Features

- 🤖 **Local Embeddings** - sentence-transformers (MiniLM-L6-v2) running in-process
- 🗄️ **Vector Database** - Self-hosted Qdrant for fast similarity search
- 🧠 **LLM Generation** - Groq Llama 3.3 70B for accurate answers
- 📚 **Citation Support** - Every answer includes source URLs
- 🚀 **Production-Ready** - Docker-based deployment with Nginx reverse proxy
- ⚡ **Fast** - Responses under 2 seconds
- 💰 **Cost-Effective** - Free embeddings, minimal API costs

## Architecture

```
User → Nginx → FastAPI → Local Embeddings → Qdrant → Groq LLM
```

- **FastAPI**: Python web framework handling requests
- **Sentence-Transformers**: Local embedding generation (no external API)
- **Qdrant**: Vector database for semantic search
- **Groq**: Fast LLM inference for answer generation
- **Nginx**: Reverse proxy with rate limiting

## Quick Start

### Prerequisites

- Docker & Docker Compose
- Groq API key (free tier available)

### Deployment

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd Knowella_osha
   ```

2. **Configure environment**
   ```bash
   cp .env.example .env
   # Edit .env and add your GROQ_API_KEY
   ```

3. **Start containers**
   ```bash
   docker-compose -f docker-compose.prod.yml up -d
   ```

4. **Verify health**
   ```bash
   curl http://localhost/health
   ```

5. **Ingest OSHA data** (takes 10-20 minutes)
   ```bash
   curl -X POST "http://localhost/ingest/osha" \
     -H "Authorization: Bearer your-ingest-token"
   ```

6. **Test chat**
   ```bash
   curl -X POST "http://localhost/chat" \
     -H "Content-Type: application/json" \
     -d '{"message":"What are OSHA fall protection requirements?"}'
   ```

## API Endpoints

### `POST /chat`
Ask questions about OSHA regulations.

**Request:**
```json
{
  "message": "What PPE is required for welding?"
}
```

**Response:**
```json
{
  "answer": "According to OSHA regulations, welding operations require...",
  "citations": [
    {
      "url": "https://www.osha.gov/laws-regs/regulations/standardnumber/1910/1910.252",
      "title": "Welding, cutting, and brazing",
      "section": "Subpart Q"
    }
  ]
}
```

### `POST /ingest/osha`
Trigger data ingestion (protected by INGEST_TOKEN).

**Headers:**
```
Authorization: Bearer your-ingest-token
```

**Query Parameters:**
- `max_pages` (optional): Maximum pages to crawl (default: 500)

### `GET /health`
Health check for all services.

### `GET /metrics`
Collection statistics and vector counts.

## Configuration

Edit `.env` file:

```env
# Qdrant Vector Database
QDRANT_URL=http://qdrant:6333

# Ingestion Configuration
INGEST_TOKEN=change-me-to-a-random-secret
MAX_INGEST_PAGES=500

# LLM API
GROQ_API_KEY=your_groq_api_key_here
```

## Development

### Local Testing

```bash
# Install dependencies
pip install -r requirements.txt

# Run locally (requires Qdrant running)
uvicorn main:app --reload
```

### Project Structure

```
.
├── main.py                      # FastAPI entry point
├── Dockerfile                   # Container image
├── docker-compose.prod.yml      # Production orchestration
├── requirements.txt             # Python dependencies
├── nginx/
│   └── nginx.conf              # Reverse proxy config
└── src/
    ├── config.py               # Configuration
    ├── routes/                 # API endpoints
    │   ├── chat.py
    │   ├── ingest_route.py
    │   └── health.py
    ├── services/               # Business logic
    │   ├── embeddings_local.py # Local embeddings
    │   ├── rag_chain.py        # RAG pipeline
    │   ├── llm_groq.py         # Groq LLM
    │   └── ingest.py           # Web crawling
    ├── db/
    │   └── qdrant_client.py    # Vector DB client
    └── utils/
        └── cache.py            # Response caching
```

## Technical Details

### Embeddings
- **Model**: sentence-transformers/all-MiniLM-L6-v2
- **Dimensions**: 384
- **Speed**: ~100-150ms per query
- **Location**: Runs in-process (no external API)

### Vector Database
- **Engine**: Qdrant
- **Collection**: osha_laws_regs
- **Storage**: Docker volume (persists across restarts)
- **Capacity**: ~10,000 chunks from 500 OSHA pages

### LLM
- **Provider**: Groq
- **Model**: Llama 3.3 70B
- **Speed**: ~1-2 seconds per response
- **Rate Limits**: 30 req/min (free tier)

### Chunking Strategy
- **Size**: 1000 characters
- **Overlap**: 200 characters
- **Deduplication**: Hash-based

## Monitoring

```bash
# View logs
docker-compose -f docker-compose.prod.yml logs -f

# Check container status
docker ps

# View metrics
curl http://localhost/metrics
```

## Security

- ✅ INGEST_TOKEN protects ingestion endpoint
- ✅ Rate limiting via Nginx (10 req/s for API, 1 req/min for ingestion)
- ✅ No authentication on /chat endpoint (as designed)
- ⚠️ Add SSL/HTTPS for production (use Let's Encrypt)

## Cost Analysis

- **Embeddings**: FREE (local)
- **Vector DB**: FREE (self-hosted)
- **Hosting**: ~$6-12/month (Digital Ocean droplet)
- **LLM API**: Groq free tier or ~$0.0001/token

## Troubleshooting

### Qdrant connection failed
```bash
# Check if Qdrant container is running
docker ps | grep qdrant

# Check Qdrant logs
docker logs osha_qdrant
```

### Embeddings slow on first request
First request downloads the MiniLM model (~90MB). Subsequent requests use cached model.

### Ingestion fails
- Check INGEST_TOKEN is correct
- Verify internet connection to osha.gov
- Check Docker logs: `docker logs osha_fastapi`

## License

MIT

## Contributing

Issues and pull requests welcome!

---

Built with ❤️ using FastAPI, Qdrant, and Groq
