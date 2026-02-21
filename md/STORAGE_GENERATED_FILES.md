# Generated files (presentations, diagrams)

Download links for generated PPT and diagram files are served by the backend. Files are stored on the **local** disk at `storage/app/private/` (see `config/filesystems.php`).

## Single server

1. **Ensure the directory exists and is writable**
   ```bash
   mkdir -p storage/app/private/presentations storage/app/private/diagrams
   chmod -R 775 storage/app/private
   # If using PHP-FPM / Nginx, ensure the web server user can write:
   # chown -R www-data:www-data storage/app/private   # or your server user
   ```

2. If downloads still return **404 File not found**:
   - Check Laravel logs for `[PresentationController]` / `[DiagramController]` messages:
     - **"Download: no record"** – no row in `generated_files` for that id (wrong id or never saved).
     - **"Download: record exists but file missing"** – row exists but file is not on disk (e.g. `Storage::put()` failed, or see multi-server below).
   - **"Failed to save presentation/diagram file"** – `Storage::put()` failed; check permissions and disk space.

## Multiple app servers (load balancer)

If the app runs on more than one instance, the request that **saves** the file (when polling `/result`) may hit server A, while the **download** request may hit server B. The database record is shared, but the file exists only on A’s disk, so B returns 404.

**Fix:** Use shared storage for generated files so every instance can read them:

- Configure an S3 (or similar) disk and store generated files there instead of `local`, **or**
- Mount a shared filesystem (e.g. NFS) on the same path (e.g. `storage/app/private`) on every server.

Then either point the `local` disk root to that shared path or add a dedicated disk for generated files and use it in `PresentationController` and `DiagramController`.
