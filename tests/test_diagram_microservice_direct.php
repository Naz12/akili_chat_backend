<?php
/**
 * E2E test: Call the diagram microservice (tools/diagram) directly.
 * Flow: POST /generate-diagram (prompt: user login and registration flowchart) → poll status → get result → verify image.
 * Uses DIAGRAM_MICROSERVICE_URL and DIAGRAM_MICROSERVICE_API_KEY from config.
 * Run: php tests/test_diagram_microservice_direct.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$baseUrl = rtrim(config('services.diagram.url', ''), '/');
$apiKey = config('services.diagram.api_key');

if (!$baseUrl || !$apiKey) {
    echo "DIAGRAM_MICROSERVICE_URL and DIAGRAM_MICROSERVICE_API_KEY must be set in .env\n";
    exit(1);
}

$headers = [
    'Content-Type' => 'application/json',
    'X-API-Key' => $apiKey,
];

function diagramPost(string $baseUrl, array $headers, string $path, array $body): array
{
    $r = \Illuminate\Support\Facades\Http::withHeaders($headers)->timeout(120)->post($baseUrl . $path, $body);
    return $r->json() ?? [];
}

function diagramGet(string $baseUrl, array $headers, string $path): array
{
    $r = \Illuminate\Support\Facades\Http::withHeaders($headers)->timeout(30)->get($baseUrl . $path);
    return $r->json() ?? [];
}

function pollDiagramJob(string $baseUrl, array $headers, string $jobId, int $maxWait = 120): array
{
    $deadline = time() + $maxWait;
    while (time() < $deadline) {
        $status = diagramGet($baseUrl, $headers, '/status/' . $jobId);
        $data = $status['data'] ?? $status;
        $st = $data['status'] ?? $status['status'] ?? 'unknown';
        if ($st === 'completed' || $st === 'complete') {
            return diagramGet($baseUrl, $headers, '/result/' . $jobId);
        }
        if (in_array($st, ['failed', 'error'], true)) {
            return ['success' => false, 'error' => $status['message'] ?? $data['error'] ?? $st];
        }
        sleep(2);
    }
    return ['success' => false, 'error' => 'Timeout'];
}

function verifyDiagramImage(string $binary): array
{
    $size = strlen($binary);
    if ($size === 0) {
        return ['ok' => false, 'error' => 'Empty image'];
    }
    $isPng = $size >= 8 && $binary[0] === "\x89" && substr($binary, 1, 3) === "PNG";
    $isJpeg = $size >= 3 && $binary[0] === "\xFF" && $binary[1] === "\xD8" && $binary[2] === "\xFF";
    if (!$isPng && !$isJpeg) {
        return ['ok' => false, 'error' => 'Not a valid image (PNG/JPEG magic missing)'];
    }
    return ['ok' => true, 'size' => $size];
}

$prompt = 'Flowchart of user login and registration';

echo "=== Diagram microservice direct test ===\n";
echo "Base URL: {$baseUrl}\n";
echo "Prompt: {$prompt}\n\n";

// 1. Generate diagram
echo "--- 1. POST /generate-diagram ---\n";
$genResp = diagramPost($baseUrl, $headers, '/generate-diagram', [
    'prompt' => $prompt,
    'diagram_type' => 'flowchart',
    'output_format' => 'png',
]);
$jobId = $genResp['job_id'] ?? null;
if (!$jobId) {
    echo "FAIL: no job_id. " . json_encode($genResp, JSON_PRETTY_PRINT) . "\n";
    exit(1);
}
echo "Job ID: {$jobId}\n";

// 2. Poll status until completed
echo "--- 2. Poll status until completed ---\n";
$result = pollDiagramJob($baseUrl, $headers, $jobId);
if (isset($result['success']) && $result['success'] === false) {
    echo "FAIL: " . ($result['error'] ?? 'Unknown error') . "\n";
    exit(1);
}

// 3. Extract image from result (microservice may return download_url / image_base64 at top level or inside data)
$data = $result['data'] ?? $result;
$inner = is_array($data) && isset($data['data']) ? $data['data'] : $data;
$imageBase64 = $result['image_base64'] ?? $result['file_content'] ?? $inner['image_base64'] ?? $inner['file_content'] ?? $inner['image'] ?? null;
$downloadUrl = $result['download_url'] ?? $result['image_url'] ?? $inner['download_url'] ?? $inner['image_url'] ?? null;
$downloadUrl = is_string($downloadUrl) ? trim($downloadUrl) : null;
if ($downloadUrl === '') {
    $downloadUrl = null;
}

$imageBinary = null;
if (!empty($imageBase64)) {
    $imageBinary = base64_decode($imageBase64, true);
}
if (($imageBinary === false || strlen((string) $imageBinary) === 0) && !empty($downloadUrl)) {
    $imgResp = \Illuminate\Support\Facades\Http::timeout(30)->get($downloadUrl);
    if ($imgResp->successful()) {
        $imageBinary = $imgResp->body();
    }
}

if (!$imageBinary || strlen($imageBinary) === 0) {
    echo "FAIL: no image in result. download_url=" . ($downloadUrl ?: 'null') . " keys=" . json_encode(array_keys(is_array($inner) ? $inner : [])) . "\n";
    if (!empty($result['error'])) {
        echo "Microservice error: " . $result['error'] . "\n";
    }
    exit(1);
}

// 4. Verify image
$verify = verifyDiagramImage($imageBinary);
if (!$verify['ok']) {
    echo "FAIL: " . $verify['error'] . "\n";
    exit(1);
}
echo "Verify OK: image valid (size=" . $verify['size'] . ")\n";
echo "\n=== Diagram microservice direct test PASSED ===\n";
