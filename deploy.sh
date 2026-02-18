#!/bin/bash

# OSHA RAG Bot - Digital Ocean Deployment Script
# Run this on your Digital Ocean droplet

set -e  # Exit on any error

echo "🚀 Starting OSHA RAG Bot Deployment..."
echo ""

# Step 1: Check if Docker is installed
echo "📦 Checking Docker installation..."
if ! command -v docker &> /dev/null; then
    echo "Docker not found. Installing Docker..."
    curl -fsSL https://get.docker.com -o get-docker.sh
    sh get-docker.sh
    rm get-docker.sh
    echo "✅ Docker installed successfully"
else
    echo "✅ Docker is already installed: $(docker --version)"
fi

# Step 2: Check if Docker Compose is installed
echo ""
echo "📦 Checking Docker Compose installation..."
if ! command -v docker-compose &> /dev/null; then
    echo "Docker Compose not found. Installing..."
    curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
    chmod +x /usr/local/bin/docker-compose
    echo "✅ Docker Compose installed successfully"
else
    echo "✅ Docker Compose is already installed: $(docker-compose --version)"
fi

# Step 3: Clone repository
echo ""
echo "📥 Cloning repository..."
cd /opt
if [ -d "osha-rag-bot" ]; then
    echo "Repository already exists. Pulling latest changes..."
    cd osha-rag-bot
    git pull
else
    git clone https://github.com/saksham1702/osha-rag-bot.git
    cd osha-rag-bot
    echo "✅ Repository cloned successfully"
fi

# Step 4: Configure environment
echo ""
echo "⚙️  Configuring environment..."
if [ ! -f ".env" ]; then
    cp .env.example .env
    echo "⚠️  IMPORTANT: Edit .env file with your configuration:"
    echo "   - GROQ_API_KEY (required)"
    echo "   - INGEST_TOKEN (required)"
    echo ""
    echo "Opening .env file for editing..."
    echo "Press any key to continue..."
    read -n 1 -s
    nano .env
else
    echo "✅ .env file already exists"
fi

# Step 5: Stop existing containers (if any)
echo ""
echo "🛑 Stopping existing containers..."
docker-compose -f docker-compose.prod.yml down 2>/dev/null || true

# Step 6: Build and start containers
echo ""
echo "🔨 Building and starting containers..."
docker-compose -f docker-compose.prod.yml up -d --build

# Step 7: Wait for services to be ready
echo ""
echo "⏳ Waiting for services to start..."
sleep 10

# Step 8: Check health
echo ""
echo "🏥 Checking health status..."
curl -s http://localhost/health | python3 -m json.tool || echo "Health check endpoint not responding yet"

# Step 9: Show status
echo ""
echo "📊 Container status:"
docker ps

echo ""
echo "✅ Deployment complete!"
echo ""
echo "📍 Next steps:"
echo "   1. Verify health: curl http://localhost/health"
echo "   2. Check logs: docker-compose -f docker-compose.prod.yml logs -f"
echo "   3. Trigger ingestion:"
echo "      curl -X POST 'http://localhost/ingest/osha' \\"
echo "        -H 'Authorization: Bearer your-ingest-token'"
echo ""
echo "🌐 Access your API at: http://159.65.254.20"
echo ""
echo "⚠️  SECURITY: Remember to change your root password: passwd"
echo ""
