# Tools microservices: endpoints and response data types

This document lists the endpoints each tool uses and the **return data types** (JSON shape or binary). It aligns microservice responses with what our clients and controllers expect, so we can add fallback keys or binary handling when services differ.

---

## 1. Presentation (PPT) microservice — `tools/ppt`

| Endpoint | Method | What we call | Return type | Our expectation |
|----------|--------|--------------|-------------|------------------|
| `/generate-outline` | POST | `submitOutline()` | JSON: `job_id`, `status`, `message` | ✓ client uses `job_id` |
| `/generate-content` | POST | `submitContent()` | JSON: `job_id`, `status`, `message` | ✓ |
| `/export` | POST | `submitExport()` | JSON: `job_id`, `status`, `message` | ✓ |
| `/jobs/{id}/status` | GET | `getJobStatus()` | JSON: `status`, `progress`, `data` | ✓ PptMicroserviceClient merges `body` + `body['data']` |
| `/jobs/{id}/result` | GET | `getJobResult()` | **Either** JSON **or** binary (FileResponse) | ✓ Client: if binary or non-JSON body → `_raw_body` + `_content_type`. JSON: `data.download_url`, `data.file_url`, `data.file_content` (base64). Controller also checks `inner.result.file_content`, `filename`. |

**Controller (PresentationController::result)** looks for:

- **Binary:** `data._raw_body` (set by client when response is binary / non-JSON).
- **URL:** `download_url`, `file_url` (from `inner`, `data`, `nested`).
- **Base64:** `file_content` (from `inner`, `data`, `nested`, `resultBlob`).
- **Filename:** `filename` (default `presentation.pptx`).

**Optional fallbacks to add if your PPT service returns different keys:** `output_url`, `result_url`, `file`, `output_file_url`, `result_file_url` (single URL); `output_base64`, `content_base64` (base64 content).

---

## 2. Diagram microservice — `tools/diagram`

| Endpoint | Method | What we call | Return type | Our expectation |
|----------|--------|--------------|-------------|------------------|
| `/generate-diagram` | POST | `generateDiagram()` | JSON: `job_id`, `status`, `message` | ✓ |
| `/status/{job_id}` | GET | `getJobStatus()` | JSON: `status`, `progress`, `data` | ✓ |
| `/result/{job_id}` | GET | `getJobResult()` | **Either** JSON **or** binary (PNG/SVG/PDF) | ✓ Client: binary → `_raw_body` + `_content_type`. JSON: ref says `download_url`; we also accept many aliases. |

**Reference (TOOLS_MICROSERVICES_REFERENCE.md):** Result (completed) = `{ "job_id", "status": "completed", "download_url": "https://.../download/{job_id}" }`. In practice the service may also stream the image as **binary** on GET `/result/{job_id}`.

**Controller (DiagramController::result)** looks for:

- **Binary:** `data._raw_body`.
- **URL:** `download_url`, `image_url`, `diagram_url`, `result_url` (from `inner`, `data`, `nested`).
- **Base64:** `image_base64`, `file_content`, `image`, `png_base64`, `image_data`, `result`, `output` (from `inner`, `data`, `nested`).
- **Extension:** `output_format` from `inner` or `data` (default `png`).

**Optional fallbacks:** `file`, `url` (single URL) in case the diagram service returns those.

---

## 3. Doc-converter microservice — `tools/doc-convertor`

| Operation | Status endpoint | Result endpoint | Result return type | Our handling |
|-----------|-----------------|-----------------|--------------------|--------------|
| Convert | `GET /v1/conversion/status?job_id=` | `GET /v1/conversion/result?job_id=` | **Single file:** binary (FileResponse, e.g. `application/octet-stream`, `application/pdf`). **Multi-file:** JSON with `download_urls` / `files`. | ✓ DocConverterClient::get() returns `_raw_body` + `_content_type` for binary; JSON merged with `body['data']`. Controller: proxyDownloadUrlsToStorage uses _raw_body, then download_url(s), then file_content/content_base64/output_base64. normalizeDocConverterResultData maps many URL keys to download_url. |
| Extract | `GET /v1/extraction/status?job_id=` | `GET /v1/extraction/result?job_id=` | JSON: `content` / `text` (extracted text), not a file URL. | ✓ Normalizer collects `content`, `text`, `extracted_text`, `result_text`. |
| Merge | `GET /v1/pdf/merge/status?job_id=` | `GET /v1/pdf/merge/result?job_id=` | Binary or JSON with URLs. | ✓ Same get() handling. |
| Split | `GET /v1/pdf/split/status?job_id=` | `GET /v1/pdf/split/result?job_id=` | Binary or JSON. | ✓ Same. |
| PDF ops (compress, watermark, etc.) | `GET /v1/pdf/{op}/status?job_id=` | `GET /v1/pdf/{op}/result?job_id=` | Binary or JSON. | ✓ Same. |

**Controller** uses:

- **Binary:** `_raw_body` + `_content_type` (extension from Content-Type when possible).
- **URLs:** `download_url`, `output_url`, `file_url`, `output_file_url`, `result_url`, `converted_file_url`, etc. (see `normalizeDocConverterResultData`).
- **Base64:** `file_content`, `content_base64`, `output_base64`.
- **Content (extract):** `content`, `text`, `extracted_text`, `result_text`.

**DocConverterClient::get()** treats as binary when: `Content-Type` is `application/octet-stream`, `application/pdf`, or `image/*`; or body does not look like JSON. For Office formats (e.g. docx, xlsx) the service may return `application/vnd.openxmlformats-...`; we also treat non-JSON body as binary, and extension is derived from Content-Type in the controller.

---

## Summary

| Tool | Result endpoint can return | We handle |
|------|----------------------------|-----------|
| **PPT** | JSON (`download_url`, `file_url`, `file_content`) or **binary** (PPTX) | Both; client sets `_raw_body` for binary. Controller uses URL, base64, or _raw_body. |
| **Diagram** | JSON (`download_url` per ref) or **binary** (PNG/SVG/PDF) | Both; client sets `_raw_body` for binary. Controller uses URL, base64, or _raw_body. |
| **Doc-converter** | **Binary** (single file) or JSON (`download_url`/`download_urls`/`content`/etc.) | Both; client sets `_raw_body` for binary; controller normalizes many URL keys and content keys. |

If a microservice returns a **different key** (e.g. `output_url` instead of `download_url`), add that key to the controller’s fallback chain (or to `normalizeDocConverterResultData` for doc-converter) so we still produce `file_id` / `download_url` for the frontend.
