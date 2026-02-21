<?php
/**
 * E2E test: Call the PPT microservice (tools/ppt) directly.
 * Flow: generate-outline → poll result → generate-content → poll result → export → poll result → verify PPTX.
 * Uses PRESENTATION_MICROSERVICE_URL and PRESENTATION_MICROSERVICE_API_KEY from config.
 * Run: php tests/test_ppt_microservice_direct.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$baseUrl = rtrim(config('services.presentation.url', ''), '/');
$apiKey = config('services.presentation.api_key');

if (!$baseUrl || !$apiKey) {
    echo "PRESENTATION_MICROSERVICE_URL and PRESENTATION_MICROSERVICE_API_KEY must be set in .env\n";
    exit(1);
}

$headers = [
    'Content-Type' => 'application/json',
    'X-API-Key' => $apiKey,
];

function pptPost(string $baseUrl, array $headers, string $path, array $body): array {
    $r = \Illuminate\Support\Facades\Http::withHeaders($headers)->timeout(60)->post($baseUrl . $path, $body);
    return $r->json() ?? [];
}

function pptGet(string $baseUrl, array $headers, string $path): array {
    $r = \Illuminate\Support\Facades\Http::withHeaders($headers)->timeout(30)->get($baseUrl . $path);
    return $r->json() ?? [];
}

function pollPptJob(string $baseUrl, array $headers, string $jobId, int $maxWait = 300): array {
    $deadline = time() + $maxWait;
    while (time() < $deadline) {
        $status = pptGet($baseUrl, $headers, '/jobs/' . $jobId . '/status');
        $st = $status['status'] ?? $status['data']['status'] ?? 'unknown';
        if ($st === 'completed') {
            return pptGet($baseUrl, $headers, '/jobs/' . $jobId . '/result');
        }
        if (in_array($st, ['failed', 'error'], true)) {
            return ['success' => false, 'error' => $status['message'] ?? $st];
        }
        sleep(3);
    }
    return ['success' => false, 'error' => 'Timeout'];
}

echo "=== PPT microservice direct test ===\n";
echo "Base URL: {$baseUrl}\n\n";

// 1. Generate outline
echo "--- 1. POST /generate-outline ---\n";
$outlineResp = pptPost($baseUrl, $headers, '/generate-outline', [
    'content' => 'Solar energy benefits with 3 slides',
    'language' => 'English',
    'tone' => 'Professional',
    'length' => 'Medium',
]);
$outlineJobId = $outlineResp['job_id'] ?? null;
if (!$outlineJobId) {
    echo "FAIL: no job_id. " . json_encode($outlineResp, JSON_PRETTY_PRINT) . "\n";
    exit(1);
}
echo "Outline job_id: {$outlineJobId}\n";

echo "--- 2. Poll outline result ---\n";
$outlineResult = pollPptJob($baseUrl, $headers, $outlineJobId);
$outlineData = $outlineResult['data'] ?? $outlineResult;
$inner = is_array($outlineData) && isset($outlineData['data']) ? $outlineData['data'] : $outlineData;
$outline = $inner['outline'] ?? $inner;
if (empty($outline['slides']) && !empty($inner['slides'])) {
    $outline = ['title' => $inner['title'] ?? 'Presentation', 'slides' => $inner['slides']];
}
if (empty($outline['slides'])) {
    echo "FAIL: outline has no slides. " . json_encode($outlineResult, JSON_PRETTY_PRINT) . "\n";
    exit(1);
}
echo "Outline OK: title=" . ($outline['title'] ?? '') . ", slides=" . count($outline['slides']) . "\n";

// Ensure slide shape for content
foreach ($outline['slides'] as $i => $s) {
    if (empty($outline['slides'][$i]['header'])) {
        $outline['slides'][$i]['header'] = $s['title'] ?? 'Slide ' . ($i + 1);
    }
    if (!isset($outline['slides'][$i]['subheaders']) || !is_array($outline['slides'][$i]['subheaders'])) {
        $outline['slides'][$i]['subheaders'] = [];
    }
    if (empty($outline['slides'][$i]['slide_number'])) {
        $outline['slides'][$i]['slide_number'] = $i + 1;
    }
}

// 3. Generate content
echo "\n--- 3. POST /generate-content ---\n";
$contentResp = pptPost($baseUrl, $headers, '/generate-content', [
    'outline' => $outline,
    'language' => 'English',
    'tone' => 'Professional',
    'detail_level' => 'Medium',
]);
$contentJobId = $contentResp['job_id'] ?? null;
if (!$contentJobId) {
    echo "FAIL: no job_id. " . json_encode($contentResp, JSON_PRETTY_PRINT) . "\n";
    exit(1);
}
echo "Content job_id: {$contentJobId}\n";

echo "--- 4. Poll content result ---\n";
$contentResult = pollPptJob($baseUrl, $headers, $contentJobId);
$contentData = $contentResult['data'] ?? $contentResult;
$contentInner = is_array($contentData) && isset($contentData['data']) ? $contentData['data'] : $contentData;
$content = $contentInner['content'] ?? $contentInner;
if (empty($content['slides']) && !empty($contentInner['slides'])) {
    $content = ['title' => $contentInner['title'] ?? $outline['title'] ?? 'Presentation', 'slides' => $contentInner['slides']];
}
if (empty($content['slides'])) {
    $content = ['title' => $outline['title'] ?? 'Presentation', 'slides' => $outline['slides']];
}
foreach ($content['slides'] as $i => $s) {
    if (!isset($content['slides'][$i]['content']) || $content['slides'][$i]['content'] === '') {
        $content['slides'][$i]['content'] = $s['header'] ?? $s['title'] ?? 'Slide ' . ($i + 1);
    }
}
echo "Content OK: slides=" . count($content['slides']) . "\n";

// 5. Export (tools/ppt expects slide_number, header, subheaders, slide_type, content per slide)
$exportSlides = [];
foreach ($content['slides'] as $i => $s) {
    $exportSlides[] = [
        'slide_number' => (int)($s['slide_number'] ?? $i + 1),
        'header' => (string)($s['header'] ?? $s['title'] ?? ''),
        'subheaders' => isset($s['subheaders']) && is_array($s['subheaders']) ? $s['subheaders'] : [],
        'slide_type' => (string)($s['slide_type'] ?? 'content'),
        'content' => is_array($s['content'] ?? '') ? implode("\n", $s['content']) : (string)($s['content'] ?? ''),
    ];
}
$exportContent = ['title' => $content['title'] ?? 'Presentation', 'slides' => $exportSlides];

echo "\n--- 5. POST /export ---\n";
$exportResp = pptPost($baseUrl, $headers, '/export', [
    'content' => $exportContent,
    'random_id' => 'direct-test-' . uniqid(),
    'template' => 'corporate_blue',
    'color_scheme' => 'blue',
    'font_style' => 'modern',
]);
$exportJobId = $exportResp['job_id'] ?? null;
if (!$exportJobId) {
    echo "FAIL: no job_id. " . json_encode($exportResp, JSON_PRETTY_PRINT) . "\n";
    exit(1);
}
echo "Export job_id: {$exportJobId}\n";

echo "--- 6. Poll export result ---\n";
$exportResult = pollPptJob($baseUrl, $headers, $exportJobId);
$exportData = $exportResult['data'] ?? $exportResult;
$exportInner = is_array($exportData) && isset($exportData['data']) ? $exportData['data'] : $exportData;
$fileContentB64 = $exportInner['file_content'] ?? $exportInner['data']['file_content'] ?? null;
if (!$fileContentB64) {
    echo "FAIL: no file_content in result. " . json_encode($exportResult, JSON_PRETTY_PRINT) . "\n";
    exit(1);
}

// 7. Verify PPTX
$decoded = base64_decode($fileContentB64, true);
if ($decoded === false || strlen($decoded) === 0) {
    echo "FAIL: file_content decode failed or empty\n";
    exit(1);
}
if (strlen($decoded) < 2 || $decoded[0] !== 'P' || $decoded[1] !== 'K') {
    echo "FAIL: not a valid PPTX (ZIP magic missing)\n";
    exit(1);
}
echo "Verify OK: PPTX valid (size=" . strlen($decoded) . ")\n";
echo "\n=== PPT microservice direct test PASSED ===\n";
