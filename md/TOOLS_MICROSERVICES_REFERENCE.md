# Tools microservices reference (diagram + doc-convertor)

Based on reading **tools/diagram** and **tools/doc-convertor** on the server. Use this to align Akili backend and AI classifier with what the microservices actually support.

---

## 1. Diagram microservice (tools/diagram)

**Base URL**: e.g. `https://diagram.akmicroservice.com` or `DIAGRAM_MICROSERVICE_URL`  
**Auth**: `X-API-Key` header.

### 1.1 Endpoints

| Method | Path | Purpose |
|--------|------|--------|
| GET | `/health` | Health (no auth) |
| POST | `/generate-diagram` | Create job → `job_id` |
| GET | `/status/{job_id}` | Job status |
| GET | `/result/{job_id}` | Result + `download_url` |
| GET | `/download/{job_id}` | Download PNG/SVG/PDF (public) |

### 1.2 Supported diagram types

From `models/schema.py` and `config/settings.py`:

**Graph-based (Graphviz):**
- `flowchart`, `sequence`, `class`, `state`, `er`, `user_journey`, `block`, `mindmap`

**Chart-based (matplotlib/plotly):**
- `pie`, `quadrant`, `timeline`, `sankey`, `xy`

**Full list (13 types):**  
`flowchart`, `sequence`, `class`, `state`, `er`, `user_journey`, `block`, `mindmap`, `pie`, `quadrant`, `timeline`, `sankey`, `xy`

### 1.3 Request body (POST /generate-diagram)

```json
{
  "language": "en",
  "diagram_type": "flowchart",
  "prompt": "User login process: Start -> Validate -> End",
  "output_format": "png"
}
```

- **language** (optional): default `"en"`
- **diagram_type** (required): one of the 13 types above
- **prompt** (required): description for the diagram
- **output_format** (optional): `png` | `svg` | `pdf` — default in microservice is **svg**

### 1.4 Response

- Create: `{ "job_id": "uuid", "status": "queued" }`
- Result (completed): `{ "job_id", "status": "completed", "download_url": "https://.../download/{job_id}" }`

### 1.5 Akili alignment

- **DiagramMicroserviceClient**: Already uses `/generate-diagram`, `/status/{job_id}`, `/result/{job_id}`. Sends `diagram_type`, `prompt`, `output_format` (we use `png`).
- **Intent classifier**: Should allow all 13 types; map “journey” → `user_journey`. Add missing: `class`, `user_journey`, `block`, `quadrant`, `timeline`, `sankey`, `xy`.
- **resolveDiagramType**: Use the same 13 values; default `flowchart` when unclear.

---

## 2. Doc-convertor microservice (tools/doc-convertor)

**Base URL**: e.g. `https://convertor.akmicroservice.com` or `DOC_CONVERTER_URL`  
**Auth**: `X-API-Key` header.

### 2.1 Operations and endpoints

| Operation | POST endpoint | Status | Result |
|-----------|---------------|--------|--------|
| **Convert** | `POST /v1/convert` | `GET /v1/conversion/status?job_id=` | `GET /v1/conversion/result?job_id=` |
| **Extract** | `POST /v1/extract` | `GET /v1/extraction/status?job_id=` | `GET /v1/extraction/result?job_id=` |
| **Merge** | `POST /v1/merge` | `GET /v1/pdf/merge/status?job_id=` | `GET /v1/pdf/merge/result?job_id=` |
| **Split** | `POST /v1/pdf/split` | `GET /v1/pdf/split/status?job_id=` | `GET /v1/pdf/split/result?job_id=` |

Merge and split do **not** use `/v1/conversion/status` or `/v1/conversion/result`; they use operation-specific paths under `/v1/pdf/{operation}/`.

### 2.2 Convert (POST /v1/convert)

- **Request**: multipart `file`, `target_format` (e.g. `pdf`, `docx`, `md`, `text` is not a valid target — use **extract** for text).
- **Target formats** (from CONVERSION_SUPPORTED_FORMATS): doc, docx, html, jpg, md, pdf, png, ppt, pptx, xls, xlsx.
- For “convert to text” the microservice uses **extract** with `extraction_type=text`, not convert.
- **GET /v1/conversion/result**: For a **single** output file the service returns **binary** (`FileResponse`, `Content-Type: application/octet-stream`), not JSON. For multiple files it returns JSON with `download_urls` and `files`. Same for **GET /v1/pdf/{operation}/result** (compress, watermark, etc.). Akili backend treats non-JSON/binary response as file content, saves to storage, and returns its own `download_url`.

### 2.3 Extract (POST /v1/extract)

- **Request**: multipart `file`; form `extraction_type`: `text` | `metadata` | `both`; optional `language`, `max_pages`.
- **Result**: Extraction result returns **content in the response body** (e.g. `content`, `text`), not a download URL. Use for “convert this to text” or “extract text”.

### 2.4 Merge (POST /v1/merge)

- **Request**: multipart **`files`** (2–10 files). Optional: `page_order` (`as_uploaded` | `alphabetical`), `remove_blank_pages`, `add_page_numbers`.
- **Status/result**: `GET /v1/pdf/merge/status?job_id=`, `GET /v1/pdf/merge/result?job_id=`.

### 2.5 Split (POST /v1/pdf/split)

- **Request**: multipart `file` (PDF only); **`split_points`** (required): comma-separated page numbers, e.g. `"3,7,12"`. Optional: `title_prefix`, `author`.
- **Status/result**: `GET /v1/pdf/split/status?job_id=`, `GET /v1/pdf/split/result?job_id=`.
- So split **requires** the user (or AI) to specify at which pages to split (e.g. “split at pages 3 and 7” → `split_points=3,7`). Without that, the microservice returns 400.

### 2.6 Akili alignment

- **Convert**: Keep using `/v1/convert` and `/v1/conversion/status`, `/v1/conversion/result`. For “convert to text” or “extract text”, use **extract** (POST /v1/extract) and extraction status/result.
- **DocConverterClient**:
  - **Merge**: Call `POST /v1/merge` (we do). For merge **status/result** use `GET /v1/pdf/merge/status` and `GET /v1/pdf/merge/result`, not conversion.
  - **Split**: Call `POST /v1/pdf/split` (not `/v1/split`) with **`split_points`** required. Use `GET /v1/pdf/split/status` and `GET /v1/pdf/split/result`.
  - **Extract**: Add `extractDocument()` calling POST /v1/extract; add getExtractionStatus/Result using extraction status/result URLs.
- **Intent classifier**: For “convert to text” or “extract text”, classify as doc_converter with operation **extract**. Operations: `convert`, `extract`, `merge`, `split`.
- **Controller**: Merge/split result endpoints must call the correct status/result URLs per operation (conversion vs pdf/merge vs pdf/split vs extraction).

---

## 3. Summary

| Tool | What to align |
|------|----------------|
| **Diagram** | Use all 13 diagram types; map journey → user_journey; optional language; output_format png/svg/pdf. |
| **Doc-converter** | Convert + conversion status/result; **Extract** for text (extract + extraction status/result); **Merge** → /v1/merge + pdf/merge status/result; **Split** → /v1/pdf/split with split_points + pdf/split status/result. |
