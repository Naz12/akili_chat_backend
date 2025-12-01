# WebSocket Setup Instructions

## ✅ What's Been Done

1. ✅ Systemd service file created: `akili-websocket.service`
2. ✅ Nginx configuration updated: `chat.akmicroservice.com.conf`
3. ✅ AdminBroadcastNotification class created
4. ✅ NotifierController updated to support WebSocket and WebPush
5. ✅ Admin notifier view updated with new channel options

## 🚀 Setup Steps

### Step 1: Copy and Start WebSocket Service

Run the setup script (requires sudo password):

```bash
cd /home/deploy_user_dagi/services/akili_chat_backend
sudo bash setup-websocket.sh
```

Or manually:

```bash
# Copy service file
sudo cp akili-websocket.service /etc/systemd/system/

# Reload systemd
sudo systemctl daemon-reload

# Enable service
sudo systemctl enable akili-websocket.service

# Start service
sudo systemctl start akili-websocket.service

# Check status
sudo systemctl status akili-websocket.service
```

### Step 2: Apply Nginx Configuration

The nginx config has been updated. Apply it:

```bash
# Test nginx config
sudo nginx -t

# If test passes, reload nginx
sudo systemctl reload nginx
```

Or use the existing script:

```bash
sudo bash apply-nginx-cors.sh
```

### Step 3: Clear Laravel Cache

```bash
cd /home/deploy_user_dagi/services/akili_chat_backend
php artisan config:clear
php artisan cache:clear
```

### Step 4: Verify WebSocket is Running

```bash
# Check service status
sudo systemctl status akili-websocket.service

# Check Docker container
docker ps | grep akili-websocket

# Check logs
sudo tail -f /var/log/akili-websocket.log

# Test endpoint (should return HTTP 400 - that's normal for WebSocket)
curl http://localhost:6001
```

## 🔧 Configuration

### Soketi App Credentials

The service is configured with these default credentials:
- **App ID**: `akili-chat`
- **App Key**: `akili-chat-key`
- **App Secret**: `akili-chat-secret`

These are set in the systemd service file. To change them, edit:
```bash
sudo nano /etc/systemd/system/akili-websocket.service
```

Then restart:
```bash
sudo systemctl daemon-reload
sudo systemctl restart akili-websocket.service
```

### Backend Configuration

The backend uses Redis for broadcasting (already configured). No additional `.env` changes needed for basic setup.

If you want to use Pusher later, update `.env`:
```
BROADCAST_DRIVER=pusher
PUSHER_APP_ID=...
PUSHER_APP_KEY=...
PUSHER_APP_SECRET=...
PUSHER_APP_CLUSTER=...
```

## 📍 WebSocket Endpoints

- **WebSocket URL**: `wss://chat.akmicroservice.com/app/`
- **Stats/Metrics**: `http://localhost:9601` (internal only)
- **Direct WebSocket**: `ws://localhost:6001` (internal only)

## 🧪 Testing

### Test WebSocket Server

```bash
# Check if container is running
docker ps | grep akili-websocket

# Check service logs
sudo tail -20 /var/log/akili-websocket.log

# Test HTTP endpoint (should return 400 - normal for WebSocket)
curl -v http://localhost:6001
```

### Test Admin Notifier

1. Login to admin portal: `https://chat.akmicroservice.com/admin`
2. Go to: `https://chat.akmicroservice.com/admin/notifier`
3. Select users
4. Select channels (including WebSocket and Web Push)
5. Enter subject and message
6. Click "Broadcast"
7. Check if notifications are sent

### Test End-to-End (requires frontend)

1. Frontend connects to: `wss://chat.akmicroservice.com/app/`
2. Send notification via admin panel
3. Frontend should receive notification in real-time

## 🔍 Troubleshooting

### Service won't start

```bash
# Check service status
sudo systemctl status akili-websocket.service

# Check logs
sudo tail -50 /var/log/akili-websocket.log

# Check Docker
docker ps -a | grep akili-websocket
```

### Docker permission issues

If you see permission errors, ensure user is in docker group:
```bash
sudo usermod -aG docker deploy_user_dagi
# Then logout and login again
```

### Nginx proxy issues

```bash
# Test nginx config
sudo nginx -t

# Check nginx error logs
sudo tail -50 /var/log/nginx/chat.akmicroservice.error.log

# Check if WebSocket upgrade headers are working
curl -i -N -H "Connection: Upgrade" -H "Upgrade: websocket" \
  https://chat.akmicroservice.com/app/
```

### WebSocket connection fails

1. Check if service is running: `sudo systemctl status akili-websocket`
2. Check if nginx is proxying: `curl http://localhost:6001`
3. Check nginx logs for WebSocket upgrade errors
4. Verify SSL certificate is valid
5. Check firewall rules (ports 6001, 9601 should be accessible locally)

## 📊 Monitoring

### View Service Logs

```bash
# Follow logs in real-time
sudo tail -f /var/log/akili-websocket.log

# View last 100 lines
sudo tail -100 /var/log/akili-websocket.log
```

### Check Service Status

```bash
# Service status
sudo systemctl status akili-websocket.service

# Docker container status
docker ps | grep akili-websocket

# Container logs
docker logs akili-websocket 2>&1 | tail -50
```

## 🔄 Restarting Services

```bash
# Restart WebSocket service
sudo systemctl restart akili-websocket.service

# Restart nginx
sudo systemctl reload nginx

# Restart PHP-FPM (if needed)
sudo systemctl restart php8.3-fpm
```

## 📝 Notes

- WebSocket server runs in Docker container with Node 18
- Service auto-restarts on failure
- Logs are written to `/var/log/akili-websocket.log`
- WebSocket respects user preferences (same as other channels)
- WebSocket only works for users who are currently online/connected
- Web Push requires user subscription (separate from WebSocket)

## ✅ Verification Checklist

- [ ] Service file copied to `/etc/systemd/system/`
- [ ] Service enabled and started
- [ ] Docker container running
- [ ] Nginx configuration applied
- [ ] Nginx reloaded
- [ ] Laravel cache cleared
- [ ] Admin notifier shows new channels
- [ ] Can send test notification via admin panel
- [ ] WebSocket endpoint accessible (test with curl)

## 🎯 Next Steps

1. **Frontend Integration**: Connect frontend to WebSocket
2. **Web Push Setup**: Generate VAPID keys and configure
3. **Testing**: Test with real users
4. **Monitoring**: Set up monitoring/alerts if needed

