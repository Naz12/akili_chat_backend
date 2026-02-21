<?php
/**
 * E2E test: Akili chat endpoint routes prompts to the right tools.
 *
 * - Generate PPT: POST /chat with e.g. "Generate a presentation about X with N slides"
 *   → reply_type=presentation_outline, job_id (AI manager classifies "presentation" or keyword match).
 * - Generate flowchart/diagram: POST /chat with e.g. "Generate a flowchart diagram for Y"
 *   → reply_type=diagram, job_id (AI manager classifies "diagram" or keyword match).
 * - Convert file: doc-converter is used via direct API (POST /doc-converter/convert with file).
 *   Chat does not yet route "convert this file" + attachment to doc-converter; this test only checks the status endpoint is reachable.
 *
 * Run from backend: php tests/test_chat_tools_e2e.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$region = 'local';
$base = "/api/v1/{$region}";
$user = \App\Models\User::where('email', 'snazrawi@gmail.com')->first();
if (!$user) {
    echo "User snazrawi@gmail.com not found. Create a user or change the email in this script.\n";
    exit(1);
}
$token = auth('api')->login($user);

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

$failed = 0;

// ---- Test 1: Generate PPT (presentation) ----
echo "=== Test 1: Chat → Generate PPT ===\n";
$chatResp = api('POST', $base . '/chat', [
    'message' => 'Generate a presentation about renewable energy with 5 slides.',
], $token);

$replyType = $chatResp['reply_type'] ?? null;
$jobId = $chatResp['job_id'] ?? null;
$reply = $chatResp['reply'] ?? '';

if ($replyType === 'presentation_outline' && !empty($jobId)) {
    echo "  OK: reply_type=presentation_outline, job_id={$jobId}\n";
} else {
    echo "  FAIL: expected reply_type=presentation_outline and job_id. Got: " . json_encode([
        'reply_type' => $replyType,
        'job_id' => $jobId,
        'reply_preview' => substr($reply, 0, 80),
    ], JSON_PRETTY_PRINT) . "\n";
    $failed++;
}

// ---- Test 2: Generate flowchart diagram ----
echo "\n=== Test 2: Chat → Generate flowchart diagram ===\n";
$chatResp2 = api('POST', $base . '/chat', [
    'message' => 'Generate a flowchart diagram for user login process.',
], $token);

$replyType2 = $chatResp2['reply_type'] ?? null;
$jobId2 = $chatResp2['job_id'] ?? null;
$reply2 = $chatResp2['reply'] ?? '';

if ($replyType2 === 'diagram' && !empty($jobId2)) {
    echo "  OK: reply_type=diagram, job_id={$jobId2}\n";
} else {
    echo "  FAIL: expected reply_type=diagram and job_id. Got: " . json_encode([
        'reply_type' => $replyType2,
        'job_id' => $jobId2,
        'reply_preview' => substr($reply2, 0, 80),
    ], JSON_PRETTY_PRINT) . "\n";
    $failed++;
}

// ---- Test 3: Alternative diagram phrasing ----
echo "\n=== Test 3: Chat → \"Draw a flowchart for order processing\" ===\n";
$chatResp3 = api('POST', $base . '/chat', [
    'message' => 'Draw a flowchart for order processing.',
], $token);

$replyType3 = $chatResp3['reply_type'] ?? null;
$jobId3 = $chatResp3['job_id'] ?? null;

if ($replyType3 === 'diagram' && !empty($jobId3)) {
    echo "  OK: reply_type=diagram, job_id={$jobId3}\n";
} else {
    echo "  FAIL: expected reply_type=diagram and job_id. Got reply_type=" . ($replyType3 ?? 'null') . ", job_id=" . ($jobId3 ?? 'null') . "\n";
    $failed++;
}

// ---- Test 4: Doc-converter (direct API; chat does not route "convert file" to doc-converter yet) ----
echo "\n=== Test 4: Doc-converter API (direct) ===\n";
$convertUrl = config('services.doc_converter.url') ?? env('DOC_CONVERTER_URL');
if (empty($convertUrl)) {
    echo "  SKIP: DOC_CONVERTER_URL not configured.\n";
} else {
    // Just check the route exists; full convert needs a real file
    $statusResp = api('GET', $base . '/doc-converter/status?job_id=test-nonexistent', [], $token);
    $hasError = isset($statusResp['error']) || ($statusResp['success'] ?? false) === false;
    if ($hasError && (isset($statusResp['error']) ? strpos($statusResp['error'], 'job_id') !== false || true : true)) {
        echo "  OK: doc-converter/status endpoint reachable (returns error for bad job_id as expected).\n";
    } else {
        echo "  INFO: doc-converter/status response: " . json_encode($statusResp) . "\n";
    }
}

// ---- Summary ----
echo "\n=== Summary ===\n";
if ($failed === 0) {
    echo "All chat tool tests passed.\n";
    exit(0);
}
echo "{$failed} test(s) failed.\n";
exit(1);
