# How generated files (diagram & PPT) reach the frontend

## Intended flow

1. **Frontend** starts a job (diagram or PPT export) and gets a `job_id` from the backend.
2. **Frontend** polls the backend **status** endpoint until status is `completed`.
3. **Frontend** calls the backend **result** endpoint: `GET /api/v1/{region}/diagram/result?job_id=...` or `GET /api/v1/{region}/presentations/result?job_id=...`.
4. **Backend** calls the **microservice** (diagram.akmicroservice.com or ppt.akmicroservice.com) to get the job result.
5. **Backend** receives either a **download_url** or **file_content** (base64) from the microservice.
6. **Backend downloads and stores the file:**
   - If `download_url`: fetches the URL **with the microservice X-API-Key**, then saves the response body to Laravel storage (`storage/app/private/diagrams/` or `storage/app/private/presentations/`).
   - If `file_content`: base64-decodes and saves to the same storage.
7. **Backend** creates a **GeneratedFile** record (id = UUID, path, type = `diagram` or `presentation`) and returns **file_id** to the frontend.
8. **Frontend** uses **file_id** to build the download URL: `GET /api/v1/{region}/diagram/files/{fileId}/download` or `.../presentations/files/{fileId}/download`.
9. **Backend** **download** action looks up **GeneratedFile** by file_id and type, then streams the file from `Storage::disk('local')` to the client.

So: **yes, the Akili backend is designed to download the files from the microservices, store them in the backend, and serve them to the Akili frontend** via the `/files/{fileId}/download` routes. The frontend never talks to the microservice directly; it only talks to the backend.

## Why "no file" can still happen

- The **result** step only returns **file_id** if step 6 succeeds. If the backend cannot get the file (e.g. microservice returns no `download_url`/`file_content`, or the fetch to the microservice download URL returns 403 because the API key is missing/wrong), the backend returns `success: true` but **no file_id**, and the frontend shows "could not be prepared for download" / "no file was produced".
- So the persistent issue is almost always: **microservice result is OK, but the backend’s fetch (with API key) or save step fails**, or the **live web request is running old code/different config** (e.g. OPcache, wrong document root, or env not loaded).

## Verification

Run the CLI script (see below) on the server. It simulates the **result** step (get microservice result → fetch URL with API key → save → create GeneratedFile) and then verifies the file can be read back. If that works, the backend code and config are correct and the problem is that the **web** request (PHP-FPM) is not running the same code or config.
