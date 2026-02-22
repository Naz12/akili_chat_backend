# Diagram flow – Zooys reference

How **zooys** handles diagram generation and how **Akili** aligns with it.

## Zooys backend

- **Paths:** `zooysbackend/app/Services/AIDiagramService.php`, `app/Http/Controllers/Api/Client/DiagramController.php`
- **Flow:** Frontend gets a **zooys job_id** (not the microservice job_id). A **queue worker** runs the job: it calls the diagram microservice, polls status, then calls **getJobResult(microserviceJobId, aiResultId)**. The worker downloads the image from the microservice and stores it in Laravel (`Storage::disk('public')`), then updates the job with `image_url` (public Laravel URL). The frontend only talks to zooys; it never calls the microservice directly.

### Microservice response (zooys expectation)

In `AIDiagramService::getJobResult()` zooys reads the **raw** microservice JSON:

- `$status = $responseData['status']` (top level): `"completed"`, `"processing"`, or `"failed"`
- `$downloadUrl = $responseData['download_url']` (top level)

So the diagram microservice is expected to return at **top level** e.g.:

```json
{ "status": "completed", "download_url": "https://..." }
```

Zooys then:

1. Downloads the image: `Http::timeout(60)->get($downloadUrl)`
2. Stores it: `Storage::disk('public')->put($storagePath, $response->body())`
3. Saves a public URL in the AI result and returns that to the frontend

Zooys does **not** use base64 or nested `data.download_url`; it only uses top-level `download_url`.

## Zooys frontend

- **Path:** `zooysfrontend/lib/api/ai-tools/diagram-api.ts`
- **Flow:** `POST /api/diagram/generate` → get `job_id` → poll `GET /api/diagram/status?job_id=` → when `status === 'completed'`, call `GET /api/diagram/result?job_id=` → result contains `data.image_url` (Laravel public URL). No direct microservice calls.

## Akili alignment

- **Same microservice:** Akili uses the same diagram microservice (`DIAGRAM_MICROSERVICE_URL`). The service may return `status` and `download_url` at **top level** (zooys style) or wrap them in `data`, or return base64.
- **DiagramMicroserviceClient:** We merge the response so top-level keys are not lost: `$data = array_merge($body, $body['data'] ?? [])`. So both `{ "download_url": "..." }` and `{ "data": { "download_url": "..." } }` work.
- **DiagramController::result:** We accept both `download_url`/`image_url` (and aliases) and base64 keys (`image_base64`, `file_content`, `image`, etc.) from `$data` and nested `$inner`/`$nested`. We then download (or decode), store in `storage/app/private/diagrams`, create a `GeneratedFile`, and return `file_id`. Frontend uses `GET /{region}/diagram/files/{fileId}/download` to get the file (same idea as zooys: backend stores and serves the file).
- **Retries:** We retry `getJobResult` up to 3 times with 2s delay when the result has no image, to handle delayed availability of the artifact.

If the diagram still fails with “no image”, check Laravel logs for `[DiagramController] Result has no image` and the logged `data_keys`/`inner_keys` to see the exact microservice response shape.
