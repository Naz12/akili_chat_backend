#!/usr/bin/env php
<?php
/**
 * End-to-end verification: backend downloads from microservice, stores file, and makes it
 * available for the frontend (same flow as result + download endpoints).
 *
 * Run from repo root:
 *   php scripts/verify-generated-files-e2e.php diagram <job_id>
 *   php scripts/verify-generated-files-e2e.php ppt <job_id>
 *
 * If this passes but the browser still shows "no file", the web request is using
 * different code or config (e.g. OPcache, different document root, or .env not loaded).
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$type = $argv[1] ?? null;
$jobId = $argv[2] ?? null;

if (! in_array($type, ['diagram', 'ppt'], true) || ! $jobId) {
    echo "Usage: php scripts/verify-generated-files-e2e.php diagram|ppt <job_id>\n";
    exit(1);
}

$Storage = \Illuminate\Support\Facades\Storage::disk('local');
$GeneratedFile = \App\Models\GeneratedFile::class;

// ---------- 1) Get result from microservice ----------
if ($type === 'diagram') {
    $client = app(\App\Services\Tools\DiagramMicroserviceClient::class);
    $result = $client->getJobResult($jobId);
    $dataKey = 'download_url';
    $altKeys = ['image_url', 'image_base64', 'file_content'];
    $subDir = 'diagrams';
    $ext = 'png';
} else {
    $client = app(\App\Services\Tools\PptMicroserviceClient::class);
    $result = $client->getJobResult($jobId);
    $dataKey = 'download_url';
    $altKeys = ['file_url', 'file_content'];
    $subDir = 'presentations';
    $ext = 'pptx';
}

if (! ($result['success'] ?? false)) {
    echo "[FAIL] Microservice result error: " . ($result['error'] ?? 'unknown') . "\n";
    exit(1);
}

$data = $result['data'] ?? [];
$inner = is_array($data) && isset($data['data']) ? $data['data'] : $data;
$nested = is_array($inner['data'] ?? null) ? $inner['data'] : [];

$downloadUrl = $inner['download_url'] ?? $inner['file_url'] ?? $data['download_url'] ?? $data['file_url']
    ?? $nested['download_url'] ?? $nested['file_url'] ?? null;
$downloadUrl = is_string($downloadUrl) ? trim($downloadUrl) : null;
if ($downloadUrl === '') {
    $downloadUrl = null;
}

$fileContentB64 = $inner['file_content'] ?? $data['file_content'] ?? $nested['file_content'] ?? null;

echo "[1] Microservice result: data_keys=" . implode(',', array_keys(is_array($data) ? $data : [])) . "\n";
echo "    download_url=" . ($downloadUrl ? 'yes' : 'no') . ", file_content=" . ($fileContentB64 ? 'yes' : 'no') . "\n";

if (! $downloadUrl && ! $fileContentB64) {
    echo "[FAIL] No download_url or file_content in result. Frontend will show 'no file'.\n";
    exit(1);
}

// ---------- 2) Download or decode and save ----------
$fileId = (string) \Illuminate\Support\Str::uuid();
$path = $subDir . '/' . $fileId . '.' . $ext;
$filename = $type === 'diagram' ? 'diagram.' . $ext : ($inner['filename'] ?? $data['filename'] ?? 'presentation.pptx');
if ($type === 'presentation' && ! preg_match('/\.pptx$/i', $filename)) {
    $filename .= '.pptx';
}

if (! $Storage->exists($subDir)) {
    $Storage->makeDirectory($subDir);
}

if ($downloadUrl) {
    $apiKey = $type === 'diagram' ? config('services.diagram.api_key') : config('services.presentation.api_key');
    $headers = ! empty($apiKey) ? ['X-API-Key' => $apiKey] : [];
    $response = \Illuminate\Support\Facades\Http::withHeaders($headers)->timeout(60)->get($downloadUrl);
    if (! $response->successful()) {
        echo "[FAIL] Fetch download_url returned " . $response->status() . ". API key is " . (empty($apiKey) ? 'MISSING' : 'set') . ".\n";
        exit(1);
    }
    $body = $response->body();
} else {
    $body = base64_decode($fileContentB64, true);
    if ($body === false || strlen($body) === 0) {
        echo "[FAIL] file_content decode failed or empty.\n";
        exit(1);
    }
}

$written = $Storage->put($path, $body);
if (! $written) {
    echo "[FAIL] Storage::put failed for path: " . $path . "\n";
    exit(1);
}
echo "[2] File saved: " . $path . " (" . strlen($body) . " bytes)\n";

// ---------- 3) Create GeneratedFile (so download endpoint can find it) ----------
$GeneratedFile::create([
    'id' => $fileId,
    'path' => $path,
    'filename' => $filename,
    'type' => $type,
]);
echo "[3] GeneratedFile created: id=" . $fileId . "\n";

// ---------- 4) Verify we can read it back (same as download endpoint) ----------
$file = $GeneratedFile::where('id', $fileId)->where('type', $type)->first();
if (! $file || ! $Storage->exists($file->path)) {
    echo "[FAIL] Download endpoint would return 404 (record or file missing).\n";
    exit(1);
}
echo "[4] Download endpoint would serve: " . $file->path . "\n";

$pathPrefix = $type === 'ppt' ? 'presentations' : 'diagram';
echo "\n[OK] E2E verified: backend can download, store, and serve this file. Frontend URL: /api/v1/{region}/" . $pathPrefix . "/files/" . $fileId . "/download\n";
echo "If the browser still shows 'no file', the web request is not using this code/config (check OPcache, document root, .env).\n";
