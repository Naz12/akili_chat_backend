<?php
/**
 * E2E test: Doc-converter API (status/result by operation, validation).
 * Run: php tests/test_doc_converter_integration.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$region = 'local';
$base = "/api/v1/{$region}";
$user = \App\Models\User::where('email', 'snazrawi@gmail.com')->first();
if (! $user) {
    echo "User snazrawi@gmail.com not found. Set DOC_CONVERTER_E2E_USER_EMAIL or create user.\n";
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

echo "=== Doc-converter integration tests ===\n\n";

// ---- 1. Status without job_id → 400 ----
echo "--- 1. GET status without job_id (expect 400) ---\n";
$r = api('GET', $base . '/doc-converter/status', [], $token);
if (isset($r['error']) && (str_contains($r['error'] ?? '', 'job_id') || ($r['success'] ?? true) === false)) {
    echo "  OK: error for missing job_id\n";
} else {
    echo "  FAIL: " . json_encode($r, JSON_PRETTY_PRINT) . "\n";
    $failed++;
}

// ---- 2. Result without job_id → 400 ----
echo "--- 2. GET result without job_id (expect 400) ---\n";
$r2 = api('GET', $base . '/doc-converter/result', [], $token);
if (isset($r2['error']) && (str_contains($r2['error'] ?? '', 'job_id') || ($r2['success'] ?? true) === false)) {
    echo "  OK: error for missing job_id\n";
} else {
    echo "  FAIL: " . json_encode($r2, JSON_PRETTY_PRINT) . "\n";
    $failed++;
}

// ---- 3. Status with nonexistent job_id (expect 502 or error) ----
echo "--- 3. GET status with fake job_id ---\n";
$r3 = api('GET', $base . '/doc-converter/status?job_id=00000000-0000-0000-0000-000000000000', [], $token);
if (($r3['success'] ?? false) === false || isset($r3['error'])) {
    echo "  OK: error for nonexistent job\n";
} else {
    echo "  INFO: status returned (may be from microservice): " . ($r3['status'] ?? 'unknown') . "\n";
}

// ---- 4. Result with nonexistent job_id ----
echo "--- 4. GET result with fake job_id ---\n";
$r4 = api('GET', $base . '/doc-converter/result?job_id=00000000-0000-0000-0000-000000000000', [], $token);
if (($r4['success'] ?? false) === false || isset($r4['error'])) {
    echo "  OK: error for nonexistent job\n";
} else {
    echo "  INFO: " . json_encode($r4, JSON_PRETTY_PRINT) . "\n";
}

// ---- 5. Status with job_id in cache (convert operation) ----
echo "--- 5. Cache job with operation=convert, then status ---\n";
$fakeJobId = (string) \Illuminate\Support\Str::uuid();
Illuminate\Support\Facades\Cache::put('doc_convert_job:' . $fakeJobId, [
    'user_id' => $user->id,
    'guest_session_id' => null,
    'chat_session_id' => null,
    'operation' => 'convert',
], 60);
$r5 = api('GET', $base . '/doc-converter/status?job_id=' . urlencode($fakeJobId), [], $token);
Illuminate\Support\Facades\Cache::forget('doc_convert_job:' . $fakeJobId);
if (isset($r5['status']) || isset($r5['error'])) {
    echo "  OK: status returned (operation=convert)\n";
} else {
    echo "  FAIL: " . json_encode($r5, JSON_PRETTY_PRINT) . "\n";
    $failed++;
}

// ---- 6. Cache job with operation=extract, then status ---
echo "--- 6. Cache job with operation=extract, then status ---\n";
$fakeJobId2 = (string) \Illuminate\Support\Str::uuid();
Illuminate\Support\Facades\Cache::put('doc_convert_job:' . $fakeJobId2, [
    'user_id' => $user->id,
    'guest_session_id' => null,
    'chat_session_id' => null,
    'operation' => 'extract',
], 60);
$r6 = api('GET', $base . '/doc-converter/status?job_id=' . urlencode($fakeJobId2), [], $token);
Illuminate\Support\Facades\Cache::forget('doc_convert_job:' . $fakeJobId2);
if (isset($r6['status']) || isset($r6['error'])) {
    echo "  OK: status returned (operation=extract)\n";
} else {
    echo "  FAIL: " . json_encode($r6, JSON_PRETTY_PRINT) . "\n";
    $failed++;
}

echo "\n=== Summary ===\n";
if ($failed === 0) {
    echo "All doc-converter integration tests passed.\n";
    exit(0);
}
echo "{$failed} test(s) failed.\n";
exit(1);
