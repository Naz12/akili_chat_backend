# Doc-converter operations and diagram types (AI classification)

Reference for aligning with zooys and the tools microservices. The AI (intent classifier) decides not only **which tool** to use but also **which operation** (doc-converter) or **which diagram type** (diagram).

---

## 1. Doc-converter: operations

The **doc-converter** microservice (tools/doc-convertor) supports multiple operations. The intent classifier returns `intent: "doc_converter"` and in `entities`:

- **`operation`**: one of `convert`, `merge`, `split`
- **`target_format`** (for convert): e.g. `text`, `md`, `pdf`

### Backend

- **IntentClassifierService**: Classifier prompt includes doc_converter intent and asks for `entities.operation` and `entities.target_format`.
- **AIChatApiController::tryDocConverterReply**: Uses `operation` and `target_format` from intent; with one attachment runs convert or split; merge requires multiple files (user is directed to use merge endpoint).
- **DocConverterClient**: `convertDocument()`, `mergeDocuments()`, `splitDocument()` call microservice `/v1/convert`, `/v1/merge`, `/v1/split`. Align endpoint paths with zooys/tools/doc-convertor if different.
- **DocConverterController**: `POST .../convert`, `POST .../merge`, `POST .../split`; shared `status` and `result` for all operations.

### Frontend

- **doc-converter-api**: `convert()`, `merge()`, `split()`, `getStatus()`, `getResult()`.
- **Chat**: When chat returns `reply_type: "doc_converter"` and `job_id`, the UI polls status then result and shows text content or a download link (same pattern as diagram/PPT).

### Zooys reference

Check zooys backend and frontend for how doc-converter operations are exposed and how the AI is prompted to choose operation (merge/split/convert). Paths: zooysbackend (Laravel), zooysfrontend (Next.js), tools/doc-convertor (FastAPI).

---

## 2. Diagram: diagram type

The **diagram** microservice accepts `diagram_type` (e.g. flowchart, sequence, er). The intent classifier returns `intent: "diagram"` and in `entities`:

- **`diagram_type`**: one of `flowchart`, `sequence`, `er`, `state`, `gantt`, `mindmap`, `pie`, `journey` (or flowchart if unclear).

### Backend

- **IntentClassifierService**: Classifier prompt asks for `entities.diagram_type` when intent is diagram.
- **AIChatApiController::tryDiagramReply**: Uses `resolveDiagramType($message, $intent)` which prefers `$intent['entities']['diagram_type']` (if allowed), then infers from message keywords (e.g. "sequence diagram" → sequence), else defaults to `flowchart`.
- **DiagramMicroserviceClient::generateDiagram**: Passes `diagramType` to `POST /generate-diagram`.

### Frontend

- **diagram-api**: `generateDiagram(payload)` accepts optional `diagram_type`; when triggered from chat, the backend chooses the type from the classifier and message.
- **Chat**: Diagram messages show the generated image; payload may include `diagram_type` for display.

### Zooys reference

Zooys frontend/backend: diagram type may be chosen by the user or by the AI. Use the same diagram_type values the tools/diagram microservice expects.

---

## 3. Summary

| Tool           | AI decides                         | Backend use                          |
|----------------|------------------------------------|--------------------------------------|
| doc_converter  | intent + operation + target_format | convert/merge/split + target_format  |
| diagram        | intent + diagram_type             | flowchart, sequence, er, state, etc. |
