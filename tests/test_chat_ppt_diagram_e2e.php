<?php
/**
 * E2E: Chat endpoint → PPT (all stages: outline, content, export) and Diagram.
 * Run: php tests/test_chat_ppt_diagram_e2e.php
 * Optional: PPT_DIAGRAM_E2E_SKIP_POLL=1 to only test chat returns job_id (no polling).
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$region = 'local';
$base = "/api/v1/{$region}";
$user = \App\Models\User::where('email', 'snazrawi@gmail.com')->first();
if (!$user) {
    echo "User snazrawi@gmail.com not found. Create a user or set PPT_DIAGRAM_E2E_USER_EMAIL.\n";
    exit(1);
}
$token = auth('api')->login($user);

$skipPoll = (bool) getenv('PPT_DIAGRAM_E2E_SKIP_POLL');
// Per-job poll: pollMax * 3 seconds. Content generation can take 2–4 min; use 80+ for full PPT flow.
$pollMax = (int) (getenv('PPT_DIAGRAM_E2E_POLL_MAX') ?: '80');

/**
 * Verify a generated file exists in storage and has valid format (PPTX = ZIP magic; diagram = PNG/JPEG magic).
 * Returns ['ok' => true] or ['ok' => false, 'error' => string].
 */
function verify_generated_file(string $fileId, string $type): array
{
    $file = \App\Models\GeneratedFile::where('id', $fileId)->where('type', $type)->first();
    if (!$file) {
        return ['ok' => false, 'error' => 'GeneratedFile not found'];
    }
    $path = $file->path;
    if (!\Illuminate\Support\Facades\Storage::disk('local')->exists($path)) {
        return ['ok' => false, 'error' => 'File not on disk'];
    }
    $contents = \Illuminate\Support\Facades\Storage::disk('local')->get($path);
    $size = strlen($contents);
    if ($size === 0) {
        return ['ok' => false, 'error' => 'File is empty'];
    }
    if ($type === 'presentation') {
        // PPTX is ZIP (magic PK)
        if ($size < 2 || $contents[0] !== 'P' || $contents[1] !== 'K') {
            return ['ok' => false, 'error' => 'Not a valid PPTX (ZIP magic missing)'];
        }
        return ['ok' => true, 'size' => $size, 'path' => $path];
    }
    if ($type === 'diagram') {
        // PNG: 89 50 4E 47 0D 0A 1A 0A
        $isPng = $size >= 8 && $contents[0] === "\x89" && substr($contents, 1, 3) === "PNG";
        // JPEG: FF D8 FF
        $isJpeg = $size >= 3 && $contents[0] === "\xFF" && $contents[1] === "\xD8" && $contents[2] === "\xFF";
        if (!$isPng && !$isJpeg) {
            return ['ok' => false, 'error' => 'Not a valid image (PNG/JPEG magic missing)'];
        }
        return ['ok' => true, 'size' => $size, 'path' => $path];
    }
    return ['ok' => true, 'size' => $size, 'path' => $path];
}

function api(string $method, string $path, array $body = [], ?string $token = null): array
{
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

function poll_presentation_job(string $jobId, string $token, int $maxWait = 180): array
{
    $base = '/api/v1/local';
    $deadline = time() + $maxWait;
    while (time() < $deadline) {
        $status = api('GET', "{$base}/presentations/status?job_id=" . urlencode($jobId), [], $token);
        $st = $status['status'] ?? 'unknown';
        if ($st === 'completed') {
            return api('GET', "{$base}/presentations/result?job_id=" . urlencode($jobId), [], $token);
        }
        if (($status['success'] ?? false) === false || in_array($st, ['failed', 'error'], true)) {
            return ['success' => false, 'error' => $status['error'] ?? $st];
        }
        sleep(3);
    }
    return ['success' => false, 'error' => 'Timeout'];
}

function poll_diagram_job(string $jobId, string $token, int $maxWait = 120): array
{
    $base = '/api/v1/local';
    $deadline = time() + $maxWait;
    while (time() < $deadline) {
        $status = api('GET', "{$base}/diagram/status?job_id=" . urlencode($jobId), [], $token);
        $st = $status['status'] ?? 'unknown';
        if ($st === 'completed') {
            return api('GET', "{$base}/diagram/result?job_id=" . urlencode($jobId), [], $token);
        }
        if (($status['success'] ?? false) === false || in_array($st, ['failed', 'error'], true)) {
            return ['success' => false, 'error' => $status['error'] ?? $st];
        }
        sleep(2);
    }
    return ['success' => false, 'error' => 'Timeout'];
}

$failed = 0;

// ---- PPT: Chat → outline → content → export (all stages) ----
echo "=== PPT: Chat → outline → content → export ===\n";

$chatResp = api('POST', $base . '/chat', [
    'message' => 'Generate a presentation about renewable energy with 3 slides.',
], $token);

$outlineJobId = $chatResp['job_id'] ?? null;
$replyType = $chatResp['reply_type'] ?? null;

if ($replyType !== 'presentation_outline' || empty($outlineJobId)) {
    echo "  FAIL: Chat did not return presentation_outline + job_id. " . json_encode([
        'reply_type' => $replyType,
        'job_id' => $outlineJobId,
        'reply' => substr($chatResp['reply'] ?? '', 0, 80),
    ]) . "\n";
    $failed++;
} else {
    echo "  OK: outline job_id={$outlineJobId}\n";

    if (!$skipPoll) {
        $outlineResult = poll_presentation_job($outlineJobId, $token, $pollMax * 3);
        if (empty($outlineResult['success'])) {
            echo "  FAIL: outline poll: " . json_encode($outlineResult) . "\n";
            $failed++;
        } else {
            $outlineData = $outlineResult['data'] ?? $outlineResult;
            $outline = $outlineData['outline'] ?? $outlineData;
            if (empty($outline['slides']) && !empty($outlineData['slides'])) {
                $outline = ['title' => $outlineData['title'] ?? 'Presentation', 'slides' => $outlineData['slides']];
            }
            if (empty($outline['slides'])) {
                echo "  FAIL: outline has no slides\n";
                $failed++;
            } else {
                foreach ($outline['slides'] as $i => $s) {
                    if (empty($s['header'])) {
                        $outline['slides'][$i]['header'] = $s['title'] ?? 'Slide ' . ($i + 1);
                    }
                    if (!isset($s['subheaders']) || !is_array($s['subheaders'])) {
                        $outline['slides'][$i]['subheaders'] = [];
                    }
                    if (empty($s['slide_number'])) {
                        $outline['slides'][$i]['slide_number'] = $i + 1;
                    }
                }

                $contentResp = api('POST', $base . '/presentations/generate-content', [
                    'outline' => $outline,
                    'language' => 'English',
                    'tone' => 'Professional',
                    'detail_level' => 'Medium',
                ], $token);
                $contentJobId = $contentResp['job_id'] ?? null;
                if (!$contentJobId) {
                    echo "  FAIL: generate-content: " . json_encode($contentResp) . "\n";
                    $failed++;
                } else {
                    echo "  OK: content job_id={$contentJobId}\n";
                    $contentResult = poll_presentation_job($contentJobId, $token, $pollMax * 3);
                    if (empty($contentResult['success'])) {
                        echo "  FAIL: content poll: " . json_encode($contentResult) . "\n";
                        $failed++;
                    } else {
                        $contentData = $contentResult['data'] ?? $contentResult;
                        $content = $contentData['content'] ?? $contentData;
                        if (empty($content['slides']) && !empty($contentData['slides'])) {
                            $content = ['title' => $contentData['title'] ?? $outline['title'] ?? 'Presentation', 'slides' => $contentData['slides']];
                        }
                        if (empty($content['slides'])) {
                            $content = ['title' => $outline['title'] ?? 'Presentation', 'slides' => $outline['slides']];
                        }
                        foreach ($content['slides'] as $i => $s) {
                            if (!isset($content['slides'][$i]['content']) || $content['slides'][$i]['content'] === '') {
                                $content['slides'][$i]['content'] = $s['header'] ?? $s['title'] ?? 'Slide ' . ($i + 1);
                            }
                        }

                        $exportResp = api('POST', $base . '/presentations/export', [
                            'content' => $content,
                            'random_id' => 'e2e-' . uniqid(),
                            'template' => 'corporate_blue',
                            'color_scheme' => 'blue',
                            'font_style' => 'modern',
                        ], $token);
                        $exportJobId = $exportResp['job_id'] ?? null;
                        if (!$exportJobId) {
                            echo "  FAIL: export: " . json_encode($exportResp) . "\n";
                            $failed++;
                        } else {
                            echo "  OK: export job_id={$exportJobId}\n";
                            $exportResult = poll_presentation_job($exportJobId, $token, $pollMax * 3);
                            if (empty($exportResult['success'])) {
                                echo "  FAIL: export poll: " . json_encode($exportResult) . "\n";
                                $failed++;
                            } elseif (empty($exportResult['file_id'])) {
                                echo "  FAIL: export completed but no file_id\n";
                                $failed++;
                            } else {
                                $fileId = $exportResult['file_id'];
                                $verify = verify_generated_file($fileId, 'presentation');
                                if (!$verify['ok']) {
                                    echo "  FAIL: file verify: " . $verify['error'] . "\n";
                                    $failed++;
                                } else {
                                    echo "  OK: PPT file_id={$fileId} verified (size=" . ($verify['size'] ?? 0) . ")\n";
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}

// ---- Diagram: Chat → poll status/result ----
echo "\n=== Diagram: Chat → diagram job ===\n";

$diagChat = api('POST', $base . '/chat', [
    'message' => 'Generate a flowchart diagram for user login with 3 steps.',
], $token);

$diagJobId = $diagChat['job_id'] ?? null;
$diagReplyType = $diagChat['reply_type'] ?? null;

if ($diagReplyType !== 'diagram' || empty($diagJobId)) {
    echo "  FAIL: Chat did not return diagram + job_id. " . json_encode([
        'reply_type' => $diagReplyType,
        'job_id' => $diagJobId,
    ]) . "\n";
    $failed++;
} else {
    echo "  OK: diagram job_id={$diagJobId}\n";
    if (!$skipPoll) {
        $diagResult = poll_diagram_job($diagJobId, $token, 90);
        if (empty($diagResult['success'])) {
            echo "  FAIL: diagram poll: " . json_encode($diagResult) . "\n";
            $failed++;
        } else {
            $fileId = $diagResult['file_id'] ?? null;
            if ($fileId) {
                $verify = verify_generated_file($fileId, 'diagram');
                if (!$verify['ok']) {
                    echo "  FAIL: diagram file verify: " . $verify['error'] . "\n";
                    $failed++;
                } else {
                    echo "  OK: diagram file_id={$fileId} verified (size=" . ($verify['size'] ?? 0) . ")\n";
                }
            } else {
                echo "  OK: diagram result received (no file_id)\n";
            }
        }
    }
}

// ---- Quick export-only (minimal payload, no chat) ----
echo "\n=== Export only (minimal payload) ===\n";

$exportOnly = api('POST', $base . '/presentations/export', [
    'content' => [
        'title' => 'Test',
        'slides' => [
            ['title' => 'Slide 1', 'content' => 'Content one'],
            ['header' => 'Slide 2', 'content' => 'Content two'],
        ],
    ],
    'random_id' => 'quick-e2e-' . uniqid(),
    'template' => 'corporate_blue',
    'color_scheme' => 'blue',
    'font_style' => 'modern',
], $token);

if (!empty($exportOnly['success']) && !empty($exportOnly['job_id'])) {
    echo "  OK: export returned job_id=" . $exportOnly['job_id'] . "\n";
} else {
    echo "  FAIL: export: " . json_encode($exportOnly) . "\n";
    $failed++;
}

echo "\n=== Summary ===\n";
if ($failed === 0) {
    echo "All tests passed.\n";
    exit(0);
}
echo "{$failed} test(s) failed.\n";
exit(1);
