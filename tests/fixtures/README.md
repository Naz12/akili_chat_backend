# Test fixtures

## sample.pdf

Minimal valid PDF (one empty page). Use it to test:

- **Doc-converter from chat**: upload this file in the chat, then say e.g. "Convert this to JPG" or "Extract text".
- **Manual API tests**: attach or send this file when testing convert/extract/split.

Path: `akili_chat_backend/tests/fixtures/sample.pdf`

---

## "Diagram could not be prepared for download"

That message appears when the **diagram** job completes but the backend never gets an image to save:

1. **Diagram microservice** returns a result with no `download_url` / `image_url` and no `image_base64` / `file_content`, or  
2. **Download from the microservice URL fails** (e.g. 403 if `DIAGRAM_API_KEY` is wrong or missing), or  
3. **Saving the file fails** (e.g. storage not writable).

So it’s usually **configuration or environment**: correct diagram service URL and API key, and writable storage for `storage/app/diagrams`. No code change is required; fix config and permissions.
