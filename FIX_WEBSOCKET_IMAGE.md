# Fix WebSocket Docker Image Issue

## Problem
The Docker image `quay.io/soketi/soketi:latest-18` doesn't exist, causing the service to fail.

## Solution

Try these image options in order:

### Option 1: Docker Hub (Recommended)
```bash
# Test pull
sudo docker pull docker.io/soketi/soketi:latest

# If that works, update service file
sudo cp /home/deploy_user_dagi/services/akili_chat_backend/akili-websocket.service /etc/systemd/system/akili-websocket.service
sudo systemctl daemon-reload
sudo systemctl restart akili-websocket.service
```

### Option 2: Try specific version tags
```bash
# Try these tags one by one:
sudo docker pull soketi/soketi:1.6
sudo docker pull soketi/soketi:1.6.1
sudo docker pull soketi/soketi:1.5
```

### Option 3: Use Node 18 base image and install Soketi
If Docker images don't work, we can create a custom Dockerfile.

## Current Service File
The service file has been updated to use `docker.io/soketi/soketi:latest`.

## Next Steps
1. Try pulling the image: `sudo docker pull docker.io/soketi/soketi:latest`
2. If successful, copy updated service file and restart
3. If it fails, try the alternative tags above
4. Check logs: `sudo tail -f /var/log/akili-websocket.log`

