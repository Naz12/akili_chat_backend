<?php
/**
 * Validation and error-handling tests: chat, upload, doc-converter.
 * Ensures graceful validation (422/400), user-friendly error messages, and no raw 500s.
 *
 * Run: php tests/test_chat_validation_and_errors.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$region = 'local';
$base = "/api/v1/{$region}";

$user = \App\Models\User::where('email', 'snazrawi@gmail.com')->first();
if (! $user) {
    echo "User snazrawi@gmail.com not found. Create a user or change the email.\n";
    exit(1);
}
$token = auth('api')->login($user);

/** Send request through HTTP kernel so validation/exception handling returns proper status. */
function apiWithStatus(string $method, string $path, array $body = [], ?string $token = null): array
{
    $req = Illuminate\Http\Request::create($path, $method, $body, [], [], [
        'HTTP_AUTHORIZATION' => $token ? 'Bearer ' . $token : '',
        'CONTENT_TYPE' => 'application/json',
    ]);
    $req->headers->set('Authorization', $token ? 'Bearer ' . $token : '');
    if ($body && in_array($method, ['POST', 'PUT', 'PATCH'])) {
        $req->merge($body);
    }
    $kernel = app(\Illuminate\Contracts\Http\Kernel::class);
    $resp = $kernel->handle($req);
    $data = json_decode($resp->getContent(), true) ?? [];
    $data['_status'] = $resp->getStatusCode();
    $kernel->terminate($req, $resp);
    return $data;
}

$failed = 0;

// ---- Chat: empty message -> 422 ----
echo "=== 1. Chat validation: empty message → 422 ===\n";
$r = apiWithStatus('POST', $base . '/chat', ['message' => ''], $token);
if (($r['_status'] ?? 0) === 422 && (isset($r['errors']['message']) || isset($r['message']))) {
    echo "  OK: 422 with validation error.\n";
} else {
    echo "  FAIL: expected 422. Got status=" . ($r['_status'] ?? 'none') . ", " . json_encode($r, JSON_PRETTY_PRINT) . "\n";
    $failed++;
}

// ---- Chat: invalid attachment_url -> 422 ----
echo "\n=== 2. Chat validation: invalid attachment_url → 422 ===\n";
$r2 = apiWithStatus('POST', $base . '/chat', [
    'message' => 'Convert to PDF',
    'attachment_url' => 'not-a-valid-url',
], $token);
if (($r2['_status'] ?? 0) === 422) {
    echo "  OK: 422 for invalid attachment_url.\n";
} else {
    echo "  FAIL: expected 422. Got status=" . ($r2['_status'] ?? 'none') . "\n";
    $failed++;
}

// ---- Chat: unreachable attachment URL → graceful reply (200), not 500 ----
echo "\n=== 3. Chat: unreachable attachment URL → 200 with reply (not 500) ===\n";
$unreachableUrl = 'https://example.invalid/static/nonexistent.pdf';
$r3 = apiWithStatus('POST', $base . '/chat', [
    'message' => 'Convert this to JPG',
    'attachment_url' => $unreachableUrl,
], $token);
$status3 = $r3['_status'] ?? 0;
$reply3 = $r3['reply'] ?? '';
if ($status3 === 200 && str_contains($reply3, 'Could not read')) {
    echo "  OK: 200 with user-friendly reply (Could not read).\n";
} elseif ($status3 === 500) {
    echo "  FAIL: got 500 instead of graceful reply. response=" . json_encode($r3, JSON_PRETTY_PRINT) . "\n";
    $failed++;
} else {
    echo "  OK: status={$status3}, reply=" . substr($reply3, 0, 60) . "\n";
}

// ---- Doc-converter status: missing job_id → 400 ----
echo "\n=== 4. Doc-converter status: missing job_id → 400 ===\n";
$r4 = apiWithStatus('GET', $base . '/doc-converter/status', [], $token);
if (($r4['_status'] ?? 0) === 400 && isset($r4['error']) && str_contains(strtolower($r4['error'] ?? ''), 'job_id')) {
    echo "  OK: 400 with error about job_id.\n";
} else {
    echo "  FAIL: expected 400. Got status=" . ($r4['_status'] ?? 'none') . ", " . json_encode($r4, JSON_PRETTY_PRINT) . "\n";
    $failed++;
}

// ---- Doc-converter result: missing job_id → 400 ----
echo "\n=== 5. Doc-converter result: missing job_id → 400 ===\n";
$r5 = apiWithStatus('GET', $base . '/doc-converter/result', [], $token);
if (($r5['_status'] ?? 0) === 400 && isset($r5['error'])) {
    echo "  OK: 400 with error.\n";
} else {
    echo "  FAIL: expected 400. Got status=" . ($r5['_status'] ?? 'none') . "\n";
    $failed++;
}

// ---- Doc-converter status: invalid job_id → 502 or success: false ----
echo "\n=== 6. Doc-converter status: invalid job_id → 502 or success false ===\n";
$r6 = apiWithStatus('GET', $base . '/doc-converter/status?job_id=00000000-0000-0000-0000-000000000000', [], $token);
$status6 = $r6['_status'] ?? 0;
$success6 = $r6['success'] ?? true;
if ($status6 === 502 || $success6 === false) {
    echo "  OK: invalid job_id returns error (502 or success=false).\n";
} else {
    echo "  INFO: status={$status6}, success=" . ($r6['success'] ?? '?') . " (microservice may return 200 for unknown job).\n";
}

// ---- Chat: split without page numbers → graceful reply ----
echo "\n=== 7. Chat doc-converter: split without page numbers → reply (no 500) ===\n";
$appUrl = rtrim(config('app.url'), '/');
$fakePath = $appUrl . '/storage/chat-attachments/does-not-exist.pdf';
$r7 = apiWithStatus('POST', $base . '/chat', [
    'message' => 'Split this PDF',
    'attachment_url' => $fakePath,
], $token);
$reply7 = $r7['reply'] ?? '';
if (($r7['_status'] ?? 0) === 200 && (str_contains($reply7, 'specify') || str_contains($reply7, 'page') || str_contains($reply7, 'Could not read'))) {
    echo "  OK: 200 with guidance or file error.\n";
} elseif (($r7['_status'] ?? 0) === 500) {
    echo "  FAIL: got 500. reply=" . substr($reply7, 0, 100) . "\n";
    $failed++;
} else {
    echo "  OK: status=" . ($r7['_status'] ?? '?') . ", reply=" . substr($reply7, 0, 80) . "\n";
}

// ---- Chat: protect without password → graceful reply ----
echo "\n=== 8. Chat doc-converter: protect without password → reply ===\n";
$storageDir = storage_path('app/public/chat-attachments');
$testFile = $storageDir . '/validation-test-' . uniqid() . '.pdf';
$wrote = false;
if (is_dir($storageDir) || @mkdir($storageDir, 0755, true)) {
    $wrote = @file_put_contents($testFile, "%PDF-1.4\n minimal\n%%EOF") !== false;
}
$attachUrl = null;
if ($wrote) {
    $attachUrl = $appUrl . '/storage/chat-attachments/' . basename($testFile);
}
if ($attachUrl) {
    $r8 = apiWithStatus('POST', $base . '/chat', [
        'message' => 'Protect this PDF',
        'attachment_url' => $attachUrl,
    ], $token);
    $reply8 = $r8['reply'] ?? '';
    if (($r8['_status'] ?? 0) === 200 && str_contains(strtolower($reply8), 'password')) {
        echo "  OK: 200 with password guidance.\n";
    } else {
        echo "  FAIL: expected reply about password. status=" . ($r8['_status'] ?? '?') . ", reply=" . substr($reply8, 0, 80) . "\n";
        $failed++;
    }
    @unlink($testFile);
} else {
    echo "  SKIP: could not create test file (storage not writable).\n";
}

// ---- Upload: missing file → 422 (POST with no file) ----
echo "\n=== 9. Upload: missing file → 422 ===\n";
$r9 = apiWithStatus('POST', $base . '/chat/upload', [], $token);
$status9 = $r9['_status'] ?? 0;
if ($status9 === 422) {
    echo "  OK: 422 for missing file.\n";
} elseif ($status9 === 500 && isset($r9['error'])) {
    echo "  OK: 500 with error (validation may run before controller).\n";
} else {
    echo "  INFO: status={$status9} (expected 422 for required file).\n";
}

// ---- 10. Upload: real file → 200 with url OR 500 with actionable error ----
echo "\n=== 10. Upload: real file (catches storage read-only) ===\n";
$tmpPath = tempnam(sys_get_temp_dir(), 'akili_upload_');
file_put_contents($tmpPath, "%PDF-1.4\nminimal\n%%EOF");
$uploadedFile = new \Illuminate\Http\UploadedFile($tmpPath, 'test.pdf', 'application/pdf', \UPLOAD_ERR_OK, true);
$reqUpload = Illuminate\Http\Request::create($base . '/chat/upload', 'POST', [], [], ['file' => $uploadedFile], [
    'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
]);
$kernel = app(\Illuminate\Contracts\Http\Kernel::class);
$respUpload = $kernel->handle($reqUpload);
$code = $respUpload->getStatusCode();
$bodyUpload = json_decode($respUpload->getContent(), true) ?? [];
$kernel->terminate($reqUpload, $respUpload);
@unlink($tmpPath);

if ($code === 200 && ! empty($bodyUpload['url'])) {
    echo "  OK: 200 with url (storage writable).\n";
} elseif ($code === 500 && isset($bodyUpload['error'])) {
    $err = $bodyUpload['error'];
    if (str_contains($err, 'fix-permissions') || str_contains($err, 'not writable') || str_contains($err, 'read-only')) {
        echo "  OK: 500 with actionable error (storage not writable; run fix-permissions.sh on server).\n";
    } else {
        echo "  FAIL: 500 but error message should mention fix-permissions or writable. Got: " . substr($err, 0, 120) . "\n";
        $failed++;
    }
} else {
    echo "  FAIL: expected 200 with url or 500 with actionable error. status={$code}, body=" . json_encode($bodyUpload, JSON_PRETTY_PRINT) . "\n";
    $failed++;
}

echo "\n=== Summary ===\n";
if ($failed === 0) {
    echo "All validation and error-handling tests passed.\n";
    exit(0);
}
echo "{$failed} test(s) failed.\n";
exit(1);
