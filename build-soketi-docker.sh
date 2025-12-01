#!/bin/bash

# Build Soketi Docker image from Dockerfile
# This creates a custom image with Node 18 and Soketi installed

echo "🔨 Building Soketi Docker image..."

cd /home/deploy_user_dagi/services/akili_chat_backend

# Check if user can run docker, if not use sudo
if docker ps >/dev/null 2>&1; then
    DOCKER_CMD="docker"
elif sudo docker ps >/dev/null 2>&1; then
    DOCKER_CMD="sudo docker"
    echo "⚠️  Using sudo for Docker commands"
else
    echo "❌ Cannot access Docker."
    echo ""
    echo "Try one of these:"
    echo "1. Run with sudo: sudo bash build-soketi-docker.sh"
    echo "2. Activate docker group: newgrp docker (then run script again)"
    echo "3. Log out and log back in to activate docker group"
    exit 1
fi

# Build the Docker image
$DOCKER_CMD build -f Dockerfile.soketi -t akili-soketi:latest .

if [ $? -eq 0 ]; then
    echo "✅ Docker image built successfully!"
    echo ""
    echo "Next steps:"
    echo "1. Copy service file: sudo cp akili-websocket.service /etc/systemd/system/"
    echo "2. Reload systemd: sudo systemctl daemon-reload"
    echo "3. Start service: sudo systemctl start akili-websocket.service"
else
    echo "❌ Failed to build Docker image"
    exit 1
fi

