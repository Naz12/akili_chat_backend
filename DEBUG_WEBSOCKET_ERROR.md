# Debugging WebSocket Error

## Current Error

The frontend shows:
```javascript
error: {
  type: 'PusherError',
  data: {...}  // Need to see what's inside
}
```

## Steps to Debug

### 1. Check Browser Console

In the browser console, expand the error object:
```javascript
error: {
  type: 'PusherError',
  data: {
    code: ???,
    message: ???
  }
}
```

**Look for:**
- `code: 4001` = App key does not exist
- `code: 4004` = Application does not exist
- Other codes = Different issues

### 2. Check Soketi Logs

```bash
tail -f /var/log/akili-websocket.log
```

Look for:
- App key errors
- Connection errors
- Initialization errors

### 3. Verify Service Configuration

```bash
# Check if service was updated
cat /etc/systemd/system/akili-websocket.service | grep SOKETI_APP_MANAGER

# Should show:
# SOKETI_APP_MANAGER_DRIVER=array
# SOKETI_APP_MANAGER_ARRAY_0_KEY=akili-chat-key
```

### 4. Verify Soketi is Running

```bash
sudo systemctl status akili-websocket
curl http://127.0.0.1:6001
```

### 5. Test Direct Connection

```bash
# Test if Soketi accepts connections
curl -i -N \
  -H "Connection: Upgrade" \
  -H "Upgrade: websocket" \
  -H "Sec-WebSocket-Version: 13" \
  -H "Sec-WebSocket-Key: dGhlIHNhbXBsZSBub25jZQ==" \
  http://127.0.0.1:6001/app/akili-chat-key
```

## Common Issues

### Issue 1: Service Not Restarted
**Symptom:** Still getting old errors
**Fix:** 
```bash
sudo systemctl restart akili-websocket
```

### Issue 2: Wrong Driver
**Symptom:** `findByKey` errors
**Fix:** Ensure using `array` driver, not `redis`

### Issue 3: App Key Mismatch
**Symptom:** `code: 4001`
**Fix:** Verify environment variables match

### Issue 4: Service File Not Updated
**Symptom:** Old configuration still active
**Fix:**
```bash
sudo cp akili-websocket.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl restart akili-websocket
```

---

**Next Step:** Check the browser console error.data to see the exact error code and message.

