<?php
/**
 * E2E test: PPT 3-step flow.
 * 1. Outline (from chat or generate-outline) → poll result → show to user → USER EDITS outline.
 * 2. Generate-content with (edited) outline → poll result → show to user → USER EDITS content.
 * 3. GET templates → USER CHOOSES STYLE → export with (edited) content + template → poll → download PPT.
 * Run from backend: php tests/test_ppt_e2e_flow.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$region = 'local';
$base = "/api/v1/{$region}";
$user = \App\Models\User::where('email', 'snazrawi@gmail.com')->first();
if (!$user) {
    echo "User snazrawi@gmail.com not found.\n";
    exit(1);
}
$token = auth('api')->login($user);

function api(string $method, string $path, array $body = [], ?string $token = null): array {
    $req = Illuminate\Http\Request::create($path, $method, $body, [], [], [
        'HTTP_AUTHORIZATION' => $token ? 'Bearer ' . $token : '',
        'CONTENT_TYPE' => 'application/json',
    ]);
    $req->headers->set('Authorization', $token ? 'Bearer ' . $token : '');
    if ($body && in_array($method, ['POST', 'PUT', 'PATCH'])) {
        $req->merge($body);
    }
    app()->instance('request', $req);
    $route = app('router')->getRoutes()->match($req);
    $resp = $route->run();
    return json_decode($resp->getContent(), true) ?? [];
}

function poll_result(string $jobId, string $token, int $maxWait = 300): array {
    $base = '/api/v1/local';
    $deadline = time() + $maxWait;
    while (time() < $deadline) {
        $status = api('GET', "{$base}/presentations/status?job_id=" . urlencode($jobId), [], $token);
        $st = $status['status'] ?? 'unknown';
        if ($st === 'completed') {
            $res = api('GET', "{$base}/presentations/result?job_id=" . urlencode($jobId), [], $token);
            return $res;
        }
        if (($status['success'] ?? false) === false || in_array($st, ['failed', 'error'], true)) {
            return ['success' => false, 'error' => $status['error'] ?? $st];
        }
        sleep(3);
    }
    return ['success' => false, 'error' => 'Timeout waiting for job'];
}

echo "=== Step 0: Trigger outline via chat ===\n";
$chatResp = api('POST', $base . '/chat', [
    'message' => 'Generate a presentation about solar energy with 3 slides.',
], $token);
$replyType = $chatResp['reply_type'] ?? null;
$outlineJobId = $chatResp['job_id'] ?? null;
if (!$outlineJobId) {
    echo "Chat did not return job_id. Response: " . json_encode($chatResp, JSON_PRETTY_PRINT) . "\n";
    exit(1);
}
echo "Outline job_id: {$outlineJobId}\n";

echo "\n=== Step 1: Poll outline result (user would then edit outline) ===\n";
$outlineResult = poll_result($outlineJobId, $token);
if (empty($outlineResult['success'])) {
    echo "Outline result failed: " . json_encode($outlineResult, JSON_PRETTY_PRINT) . "\n";
    exit(1);
}
$outlineData = $outlineResult['data'] ?? $outlineResult;
// Normalize: outline may be in data.outline or data with title/slides
$outline = $outlineData['outline'] ?? $outlineData;
if (empty($outline['slides']) && !empty($outlineData['slides'])) {
    $outline = ['title' => $outlineData['title'] ?? 'Presentation', 'slides' => $outlineData['slides']];
}
if (empty($outline['slides'])) {
    echo "Outline has no slides. data: " . json_encode($outlineData, JSON_PRETTY_PRINT) . "\n";
    exit(1);
}
echo "Outline received: title=" . ($outline['title'] ?? '') . ", slides=" . count($outline['slides']) . "\n";
// Simulate user edit: ensure each slide has header/subheaders for generate-content
foreach ($outline['slides'] as $i => $s) {
    if (empty($s['header'])) {
        $outline['slides'][$i]['header'] = $s['title'] ?? 'Slide ' . ($i + 1);
    }
    if (!isset($s['subheaders']) || !is_array($s['subheaders'])) {
        $outline['slides'][$i]['subheaders'] = $s['subheaders'] ?? [];
    }
    if (empty($s['slide_number'])) {
        $outline['slides'][$i]['slide_number'] = $i + 1;
    }
}

echo "\n=== Step 2: Generate content from (edited) outline ===\n";
$contentResp = api('POST', $base . '/presentations/generate-content', [
    'outline' => $outline,
    'language' => 'English',
    'tone' => 'Professional',
    'detail_level' => 'Medium',
], $token);
$contentJobId = $contentResp['job_id'] ?? null;
if (!$contentJobId) {
    echo "Generate-content failed: " . json_encode($contentResp, JSON_PRETTY_PRINT) . "\n";
    exit(1);
}
echo "Content job_id: {$contentJobId}\n";

echo "\n=== Step 2b: Poll content result (user would then edit content) ===\n";
$contentResult = poll_result($contentJobId, $token);
if (empty($contentResult['success'])) {
    echo "Content result failed: " . json_encode($contentResult, JSON_PRETTY_PRINT) . "\n";
    exit(1);
}
$contentData = $contentResult['data'] ?? $contentResult;
$content = $contentData['content'] ?? $contentData;
if (empty($content['slides']) && !empty($contentData['slides'])) {
    $content = ['title' => $contentData['title'] ?? $outline['title'] ?? 'Presentation', 'slides' => $contentData['slides']];
}
if (empty($content['slides'])) {
    $content = ['title' => $outline['title'] ?? 'Presentation', 'slides' => $outline['slides']];
}
// Ensure each slide has a 'content' string for export (outline-only slides may have only header/subheaders)
foreach ($content['slides'] as $i => $s) {
    if (!isset($content['slides'][$i]['content']) || $content['slides'][$i]['content'] === '') {
        $content['slides'][$i]['content'] = $s['header'] ?? $s['title'] ?? 'Slide ' . ($i + 1);
    }
}
echo "Content received: slides=" . count($content['slides']) . "\n";

echo "\n=== Step 3: Get templates (user chooses style) ===\n";
$templatesResp = api('GET', $base . '/presentations/templates', [], $token);
$templates = $templatesResp['templates'] ?? [];
if (empty($templates)) {
    echo "No templates. Using default corporate_blue.\n";
    $chosenTemplate = 'corporate_blue';
} else {
    $ids = array_keys($templates);
    $chosenTemplate = $ids[0];
    // Export requires template to be a string (e.g. corporate_blue). If key is numeric, use a known default.
    if (!is_string($chosenTemplate) || is_numeric($chosenTemplate)) {
        $chosenTemplate = 'corporate_blue';
    }
    echo "Chosen template: {$chosenTemplate}\n";
}

echo "\n=== Step 4: Export with content + style ===\n";
$exportResp = api('POST', $base . '/presentations/export', [
    'content' => $content,
    'random_id' => 'e2e-test-' . uniqid(),
    'template' => $chosenTemplate,
    'color_scheme' => 'blue',
    'font_style' => 'modern',
], $token);
$exportJobId = $exportResp['job_id'] ?? null;
if (!$exportJobId) {
    echo "Export failed: " . json_encode($exportResp, JSON_PRETTY_PRINT) . "\n";
    exit(1);
}
echo "Export job_id: {$exportJobId}\n";

echo "\n=== Step 5: Poll export result and download ===\n";
$exportResult = poll_result($exportJobId, $token);
if (empty($exportResult['success'])) {
    echo "Export result failed: " . json_encode($exportResult, JSON_PRETTY_PRINT) . "\n";
    exit(1);
}
$fileId = $exportResult['file_id'] ?? null;
$filename = $exportResult['filename'] ?? null;
if (!$fileId) {
    echo "Export completed but no file_id. Result: " . json_encode($exportResult, JSON_PRETTY_PRINT) . "\n";
    exit(1);
}
echo "file_id: {$fileId}, filename: {$filename}\n";

$downloadResp = app()->call([app(\App\Http\Controllers\Api\PresentationController::class), 'download'], [
    'request' => Illuminate\Http\Request::create($base . '/presentations/files/' . $fileId . '/download'),
    'fileId' => $fileId,
]);
if ($downloadResp->getStatusCode() !== 200) {
    echo "Download failed: " . $downloadResp->getContent() . "\n";
    exit(1);
}
echo "Download OK.\n";

// Verify stored file: exists, non-empty, PPTX (ZIP magic)
$file = \App\Models\GeneratedFile::where('id', $fileId)->where('type', 'presentation')->first();
if (!$file || !\Illuminate\Support\Facades\Storage::disk('local')->exists($file->path)) {
    echo "Verify FAIL: file not in storage\n";
    exit(1);
}
$contents = \Illuminate\Support\Facades\Storage::disk('local')->get($file->path);
if (strlen($contents) === 0) {
    echo "Verify FAIL: file is empty\n";
    exit(1);
}
if (strlen($contents) < 2 || $contents[0] !== 'P' || $contents[1] !== 'K') {
    echo "Verify FAIL: not a valid PPTX (ZIP magic missing)\n";
    exit(1);
}
echo "Verify OK: generated file is valid PPTX (size=" . strlen($contents) . ").\n";
echo "=== Done ===\n";
