<?php
/**
 * E2E test: Diagram via Akili backend chat endpoint.
 * Flow: POST /chat with "Draw a flowchart for user login and registration" → get diagram job_id →
 *       poll /diagram/status and /diagram/result → verify stored file.
 * Run: php tests/test_diagram_chat_e2e.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$region = 'local';
$base = "/api/v1/{$region}";

$user = \App\Models\User::where('email', 'snazrawi@gmail.com')->first();
if (!$user) {
    $email = getenv('DIAGRAM_E2E_USER_EMAIL') ?: null;
    $user = $email ? \App\Models\User::where('email', $email)->first() : null;
}
if (!$user) {
    echo "User not found. Set DIAGRAM_E2E_USER_EMAIL or ensure snazrawi@gmail.com exists.\n";
    exit(1);
}
$token = auth('api')->login($user);

$pollMax = (int) (getenv('DIAGRAM_E2E_POLL_MAX') ?: '60');

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

function poll_diagram_job(string $jobId, string $token, int $maxWait = 120): array
{
    $base = '/api/v1/local';
    $deadline = time() + $maxWait;
    while (time() < $deadline) {
        $status = api('GET', "{$base}/diagram/status?job_id=" . urlencode($jobId), [], $token);
        $st = $status['status'] ?? 'unknown';
        if ($st === 'completed' || $st === 'complete') {
            return api('GET', "{$base}/diagram/result?job_id=" . urlencode($jobId), [], $token);
        }
        if (($status['success'] ?? false) === false || in_array($st, ['failed', 'error'], true)) {
            return ['success' => false, 'error' => $status['error'] ?? $st];
        }
        sleep(2);
    }
    return ['success' => false, 'error' => 'Timeout'];
}

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
    if ($type === 'diagram') {
        $isPng = $size >= 8 && $contents[0] === "\x89" && substr($contents, 1, 3) === "PNG";
        $isJpeg = $size >= 3 && $contents[0] === "\xFF" && $contents[1] === "\xD8" && $contents[2] === "\xFF";
        if (!$isPng && !$isJpeg) {
            return ['ok' => false, 'error' => 'Not a valid image (PNG/JPEG magic missing)'];
        }
        return ['ok' => true, 'size' => $size, 'path' => $path];
    }
    return ['ok' => true, 'size' => $size, 'path' => $path];
}

$prompt = 'Draw a flowchart for user login and registration';

echo "=== Diagram via Akili chat E2E test ===\n";
echo "Prompt: {$prompt}\n\n";

// 1. Send chat message
echo "--- 1. POST /chat ---\n";
$chatResp = api('POST', $base . '/chat', [
    'message' => $prompt,
], $token);

$jobId = $chatResp['job_id'] ?? null;
$replyType = $chatResp['reply_type'] ?? null;

if ($replyType !== 'diagram' || empty($jobId)) {
    echo "FAIL: Chat did not return diagram + job_id. reply_type=" . ($replyType ?? 'null') . " job_id=" . ($jobId ?? 'null') . "\n";
    echo json_encode($chatResp, JSON_PRETTY_PRINT) . "\n";
    exit(1);
}
echo "OK: reply_type=diagram, job_id={$jobId}\n";

// 2. Poll diagram status/result via backend
echo "--- 2. Poll /diagram/status and /diagram/result ---\n";
$result = poll_diagram_job($jobId, $token, $pollMax);
if (($result['success'] ?? false) !== true) {
    echo "FAIL: diagram poll: " . ($result['error'] ?? json_encode($result)) . "\n";
    exit(1);
}

$fileId = $result['file_id'] ?? null;
if (!$fileId) {
    echo "FAIL: result has no file_id. " . json_encode(array_keys($result)) . "\n";
    exit(1);
}
echo "OK: file_id={$fileId}\n";

// 3. Verify stored file
echo "--- 3. Verify generated file ---\n";
$verify = verify_generated_file($fileId, 'diagram');
if (!$verify['ok']) {
    echo "FAIL: " . $verify['error'] . "\n";
    exit(1);
}
echo "OK: diagram file verified (size=" . ($verify['size'] ?? 0) . ", path=" . ($verify['path'] ?? '') . ")\n";
echo "\n=== Diagram chat E2E test PASSED ===\n";
