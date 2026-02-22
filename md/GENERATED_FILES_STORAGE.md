# Where generated files are stored (and why you don’t see them in public)

## 1. They are not in `public/` on purpose

- **Diagram** and **presentation** files are stored with **Laravel Storage**, not in the web-visible `public/` folder.
- Config: `config/filesystems.php` → disk **`local`** → `root` = **`storage_path('app/private')`**.
- So files go to:
  - **Diagrams:** `storage/app/private/diagrams/<uuid>.png`
  - **Presentations:** `storage/app/private/presentations/<uuid>.pptx`
- The frontend does **not** open these paths directly. It calls:
  - `GET /api/v1/{region}/diagram/files/{fileId}/download`
  - `GET /api/v1/{region}/presentations/files/{fileId}/download`
- The backend then streams the file from that storage. So **you will never see these files inside `public/`** — that’s by design and is correct for security.

## 2. Why “no file” in the browser even though logic is correct

On the server we saw:

- **`storage/app/private/diagrams`** and **`storage/app/private/presentations`** exist and **already contain files** (created when running CLI scripts as your user).
- **PHP-FPM** runs as **`www-data`** (from `php-fpm pool` config).
- Those directories are owned by **`dev_friend:dev_friend`** with mode **`700`** (only owner can read/write/execute).

So when a **browser** request hits the backend:

1. The request is handled by PHP-FPM as **www-data**.
2. The controller tries to save: `Storage::disk('local')->put($path, $body)` → writes under `storage/app/private/diagrams/` or `.../presentations/`.
3. **www-data** is not the owner and has no permission on a `700` directory → the write fails (or the directory isn’t even readable).
4. The controller never gets a successful save → no **file_id** is returned → frontend shows “could not be prepared for download” / “no file was produced”.

So the issue is **permissions**: the PHP-FPM user must be able to create and write files in `storage/app/private/` (and in the `diagrams` and `presentations` subdirs).

## 3. Fix: let the web server write under storage

Run on the server (adjust user/group if your PHP-FPM pool uses something else):

```bash
cd /home/deploy_user_dagi/services/akili/akili_chat_backend

# Option A: give ownership of storage to www-data (PHP-FPM user)
sudo chown -R www-data:www-data storage

# Option B: keep your user as owner but allow group write, and put www-data in that group
# sudo chgrp -R www-data storage
# sudo chmod -R g+rwx storage
# (and add your deploy user to group www-data if needed)
```

Then ensure the private subdirs exist and are writable:

```bash
sudo -u www-data mkdir -p storage/app/private/diagrams storage/app/private/presentations
# If you used Option A, www-data already owns storage, so no further chmod needed.
```

After that, trigger diagram or PPT export again from the browser. The backend (running as www-data) should be able to write under `storage/app/private/` and return a **file_id**; the frontend will then use the **download** route to get the file (still not from `public/`).

## 4. Quick check

- List files (as the user that runs PHP-FPM):
  ```bash
  sudo -u www-data ls -la storage/app/private/diagrams
  sudo -u www-data ls -la storage/app/private/presentations
  ```
- Test write as www-data:
  ```bash
  sudo -u www-data touch storage/app/private/diagrams/.write-test && sudo -u www-data rm storage/app/private/diagrams/.write-test
  ```
  If this fails, PHP-FPM will also fail to save generated files.

## 5. Summary

| Question | Answer |
|----------|--------|
| Are generated files in `public/`? | **No.** They are in `storage/app/private/diagrams/` and `.../presentations/`. |
| Why don’t I see them in `public/`? | By design; they are served via the API download route, not as public static files. |
| Why does the browser still get “no file”? | PHP-FPM runs as **www-data**; `storage/app/private` was not writable by www-data (wrong owner/permissions). |
| What to do? | Make `storage` (or at least `storage/app/private`) owned or writable by **www-data**, then retry from the browser. |
