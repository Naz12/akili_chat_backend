# WebSocket Docker Build Fix

## Issue
Docker permission denied even though user is in docker group.

## Solution Options

### Option 1: Build with sudo (Quickest)
```bash
cd /home/deploy_user_dagi/services/akili_chat_backend
sudo bash build-soketi-docker.sh
```

### Option 2: Activate docker group in current session
```bash
newgrp docker
cd /home/deploy_user_dagi/services/akili_chat_backend
bash build-soketi-docker.sh
```

### Option 3: Manual build with sudo
```bash
cd /home/deploy_user_dagi/services/akili_chat_backend
sudo docker build -f Dockerfile.soketi -t akili-soketi:latest .
```

## After Building

Once the image is built:

```bash
# Copy service file
sudo cp akili-websocket.service /etc/systemd/system/

# Reload systemd
sudo systemctl daemon-reload

# Start service
sudo systemctl start akili-websocket.service

# Check status
sudo systemctl status akili-websocket.service
```

## Verify Image

```bash
# Check if image exists
sudo docker images | grep akili-soketi

# Should show: akili-soketi   latest   ...
```

