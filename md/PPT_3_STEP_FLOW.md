# Presentation (PPT) 3-Step Flow

Generating a PPT is a **3-step operation** (aligned with zooys and the tools/ppt microservice):

1. **Generate outline** → show to user → user may **edit** the outline.
2. **Generate content** from the (possibly edited) outline → show to user → user may **edit** the content.
3. User **chooses a style** from available templates → **export** with final content + style → PPT file.

---

## Step 1: Outline

- **From chat:** User says e.g. "Generate a PPT about X with 10 slides". Backend returns `reply_type: presentation_outline`, `job_id`.
- **Direct API:** `POST /api/v1/{region}/presentations/generate-outline`  
  Body: `{ "content": "topic and optional slide count", "language", "tone", "length" }`  
  Response: `{ "success": true, "job_id": "..." }`
- **Poll:** `GET /api/v1/{region}/presentations/status?job_id={job_id}` until `status === "completed"`.
- **Get result:** `GET /api/v1/{region}/presentations/result?job_id={job_id}`  
  Response includes **outline** (e.g. `title`, `slides` with `slide_number`, `header`, `subheaders`).
- **Frontend:** Show outline to user; allow editing (add/remove/reorder slides, change headers). Use the (edited) outline for Step 2.

---

## Step 2: Content

- **API:** `POST /api/v1/{region}/presentations/generate-content`  
  Body: `{ "outline": { "title": "...", "slides": [ ... ] }, "language", "tone", "detail_level" }`  
  (Use the outline from Step 1, optionally edited.)  
  Response: `{ "success": true, "job_id": "..." }`
- **Poll:** `GET .../presentations/status?job_id={job_id}` then `GET .../presentations/result?job_id={job_id}`.
- **Result:** Full **content** for each slide (e.g. `title`, `slides` with `content`).
- **Frontend:** Show content to user; allow editing. Use the (edited) content for Step 3.

---

## Step 3: Style + Export

- **Get styles:** `GET /api/v1/{region}/presentations/templates`  
  Response: `{ "success": true, "templates": { "corporate_blue": { "name", "description", "color_scheme", ... }, ... } }`
- **Frontend:** User picks a template (and optionally `color_scheme`, `font_style`).
- **Export:** `POST /api/v1/{region}/presentations/export`  
  Body: `{ "content": { "title", "slides": [ ... ] }, "random_id": "unique-id", "template", "color_scheme", "font_style" }`  
  (Use the final content from Step 2.)  
  Response: `{ "success": true, "job_id": "..." }`
- **Poll:** `GET .../presentations/status?job_id={job_id}` then `GET .../presentations/result?job_id={job_id}`.
- **Result:** When completed, result may include `file_id` and `filename`; download via  
  `GET /api/v1/{region}/presentations/files/{fileId}/download`.

---

## Summary

| Step | Action              | Endpoint (POST/GET)                    | User action        |
|------|---------------------|----------------------------------------|--------------------|
| 1    | Generate outline    | POST generate-outline → status → result| Edit outline       |
| 2    | Generate content    | POST generate-content → status → result| Edit content       |
| 3    | Choose style + export | GET templates → POST export → status → result → download | Pick template, then export |

Reference: zooys backend `PresentationController` and `AIPresentationService`; tools/ppt microservice (`/generate-outline`, `/generate-content`, `/export`, `/templates`, `/jobs/{id}/status`, `/jobs/{id}/result`).
