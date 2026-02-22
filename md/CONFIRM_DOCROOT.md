# How to confirm docroot for chat.akmicroservice.com

The **document root** is the directory Nginx uses to serve `chat.akmicroservice.com`. It must point at the **Laravel backend’s `public` folder** so that API requests (including diagram/PPT result and file download) run the code you deploy.

---

## 1. See which config Nginx uses for chat.akmicroservice.com

On the server:

```bash
# List enabled site configs that mention chat
ls -la /etc/nginx/sites-enabled/ | grep chat

# Show the server block (and root directive) for chat.akmicroservice.com
sudo grep -E "server_name|root " /etc/nginx/sites-enabled/chat.akmicroservice.com*
```

You should see something like:

- `server_name chat.akmicroservice.com;`
- `root /home/deploy_user_dagi/services/akili/akili_chat_backend/public;`

The **`root`** path is the docroot.

---

## 2. Confirm the docroot is the backend you’re editing

The path must be the **same** backend where you have the updated diagram/PPT code:

- Expected:  
  **`/home/deploy_user_dagi/services/akili/akili_chat_backend/public`**

Checks:

```bash
# 1) Path exists
ls -la /home/deploy_user_dagi/services/akili/akili_chat_backend/public

# 2) Laravel entry point is there
ls -la /home/deploy_user_dagi/services/akili/akili_chat_backend/public/index.php

# 3) Controllers with the diagram/PPT logic are in the same app
ls -la /home/deploy_user_dagi/services/akili/akili_chat_backend/app/Http/Controllers/Api/DiagramController.php
ls -la /home/deploy_user_dagi/services/akili/akili_chat_backend/app/Http/Controllers/Api/PresentationController.php
```

If the **`root`** in Nginx is different (e.g. another directory or an old deploy), Nginx (and PHP-FPM) will run that other code, not the one you’re editing. Update the Nginx config so **`root`** points at this backend’s **`public`** directory, then reload Nginx.

---

## 3. Compare with the repo’s sample config

The repo’s sample Nginx config for this backend is:

- **File:** `akili_chat_backend/chat.akmicroservice.com.conf`
- **Docroot in that file:**  
  `root /home/deploy_user_dagi/services/akili/akili_chat_backend/public;`

To make the live server match the repo (after backing up):

```bash
sudo cp /etc/nginx/sites-enabled/chat.akmicroservice.com.conf /etc/nginx/sites-enabled/chat.akmicroservice.com.conf.bak
sudo cp /home/deploy_user_dagi/services/akili/akili_chat_backend/chat.akmicroservice.com.conf /etc/nginx/sites-enabled/chat.akmicroservice.com.conf
sudo nginx -t && sudo systemctl reload nginx
```

---

## 4. One-liner to print the active docroot

```bash
sudo grep -h "root " /etc/nginx/sites-enabled/chat.akmicroservice.com* 2>/dev/null | head -1
```

The path after `root` is the docroot. It must be  
`/home/deploy_user_dagi/services/akili/akili_chat_backend/public`  
so that requests to `https://chat.akmicroservice.com` use this backend (and its diagram/PPT fixes).
