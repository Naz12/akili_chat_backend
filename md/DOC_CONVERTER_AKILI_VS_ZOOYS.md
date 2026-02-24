# Doc-converter: Akili vs Zooys

Comparison of how **Akili** and **zooys** call the same doc-converter microservice (`tools/doc-convertor`), so we can align and fix failures.

---

## 1. Convert (POST /v1/convert)

| Aspect | Zooys (DocumentConverterService) | Akili (DocConverterClient) |
|--------|----------------------------------|----------------------------|
| **URL** | `baseUrl . '/v1/convert'` | `baseUrl() . '/v1/convert'` ✓ same |
| **Auth** | `X-API-Key` header | `X-API-Key` via headers() ✓ same |
| **Timeout** | 120s | config (default 120) ✓ |
| **Body** | Multipart: `file` + **`target_format`** + **`options`** (array) | Multipart: `file` + **`target_format`** + **flattened options** as top-level keys |
| **Payload** | `['target_format' => $targetFormat, 'options' => $options]` | `array_merge(['target_format' => $targetFormat], $options)` |

**Difference:** Zooys always sends an **`options`** key (object/array). Akili merges `$options` into the form as top-level fields, so there is no `options` key. If the microservice expects a single **`options`** parameter (e.g. for conversion settings), Akili’s request shape is wrong.

**Align:** Send the same POST body as zooys: `target_format` + `options` (e.g. `'options' => $options` with default `[]`).

---

## 2. Status (GET /v1/conversion/status)

| Aspect | Zooys | Akili |
|--------|-------|-------|
| **URL** | `/v1/conversion/status` + `job_id` query | Same ✓ |
| **Auth** | X-API-Key | X-API-Key ✓ |
| **On failed** | Uses `$status['error']` and fails job with that message | Returns `status`, `progress`, `data` but does **not** forward microservice `error` to the client |

**Difference:** When status is `failed`, zooys reads and uses the microservice’s `error` field. Akili’s status endpoint does not put that into the JSON response, so the UI cannot show the real failure reason.

**Align:** When status is `failed`, include in the response an `error` field from microservice data (e.g. `data['error']` or `data['message']`).

---

## 3. Result (GET /v1/conversion/result)

| Aspect | Zooys | Akili |
|--------|-------|-------|
| **Usage** | Zooys **does not** use result for download URL when status is completed. It uses the **status** response: `$result['download_urls'][0]` from the last **status** call. Comment: “Use the final status response (since result endpoint returns null)”. | Akili calls result, handles binary (`_raw_body`) or JSON, saves to storage, returns `file_id` / `download_url`. |
| **Download** | Zooys: `downloadAndStore($downloadUrl)` with URL from **status** response. | Akili: GET result → binary or JSON → save → return our download URL. |

So for zooys, when the job is **completed**, the **status** endpoint is expected to return `download_urls` (or equivalent). Akili relies on the **result** endpoint to get the file (binary or JSON). If the microservice only adds `download_urls` to the **status** response and the **result** endpoint behaves differently (e.g. binary or null), both approaches can be valid; we just need to match what the microservice actually returns.

---

## 4. Config

| Zooys | Akili |
|-------|--------|
| `config('services.document_converter.url')` | `config('services.doc_converter.url')` |
| `DOCUMENT_CONVERTER_URL`, `DOCUMENT_CONVERTER_API_KEY` | `DOC_CONVERTER_URL`, `DOC_CONVERTER_API_KEY` |

Different env key names; both point to the same microservice if set to the same base URL and key.

---

## 5. Summary of changes to align Akili with zooys

1. **Convert request:** Send `target_format` and **`options`** (e.g. `'options' => $options` with default `[]`) in the POST body, same shape as zooys, instead of merging `$options` as top-level form fields.
2. **Status when failed:** Include the microservice failure reason in the API response (e.g. `error` from status `data`) so the UI can show “why” the job failed.
