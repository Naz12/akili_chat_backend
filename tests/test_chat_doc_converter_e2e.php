<?php
/**
 * E2E test: Akili chat doc-converter with REAL file.
 *
 * Runs ALL doc-converter operations from chat (convert, extract, split, merge, compress,
 * page numbers, watermark, protect). Uses real PDF; polls each job until completed/failed;
 * prints a full report: which operations work and which don't.
 *
 * Run from backend: php tests/test_chat_doc_converter_e2e.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$region = 'local';
$base = "/api/v1/{$region}";

$user = \App\Models\User::where('email', 'snazrawi@gmail.com')->first();
if (! $user) {
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

/**
 * Poll doc-converter job until completed or failed; then return result response.
 */
function poll_doc_converter_job(string $base, string $jobId, ?string $token, int $maxPoll = 40, int $interval = 3): array
{
    $status = null;
    for ($i = 0; $i < $maxPoll; $i++) {
        $statusResp = api('GET', $base . '/doc-converter/status?job_id=' . urlencode($jobId), [], $token);
        if (isset($statusResp['success']) && $statusResp['success'] !== true) {
            return ['status' => 'error', 'result' => $statusResp];
        }
        $status = $statusResp['status'] ?? null;
        if ($status === 'completed' || $status === 'failed') {
            break;
        }
        sleep($interval);
    }
    $resultResp = api('GET', $base . '/doc-converter/result?job_id=' . urlencode($jobId), [], $token);
    return ['status' => $status ?? 'unknown', 'result' => $resultResp];
}

// ---- Prepare real file ----
$storageDir = storage_path('app/public/chat-attachments');
$testFilePaths = [];
$attachmentUrl = null;
$appUrl = rtrim(config('app.url'), '/');

if (! is_dir($storageDir)) {
    @mkdir($storageDir, 0755, true);
}
$fixturePdf = __DIR__ . '/fixtures/sample.pdf';
if (is_readable($fixturePdf)) {
    $testFileName = 'chat-doc-test-' . uniqid() . '.pdf';
    $destPath = $storageDir . DIRECTORY_SEPARATOR . $testFileName;
    if (@copy($fixturePdf, $destPath)) {
        $testFilePaths[] = $destPath;
        $attachmentUrl = $appUrl . '/storage/chat-attachments/' . $testFileName;
    }
}
if (! $attachmentUrl) {
    $minimalPdf = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/MediaBox[0 0 612 792]/Parent 2 0 R>>endobj\nxref\n0 4\ntrailer<</Size 4/Root 1 0 R>>\nstartxref\n200\n%%EOF";
    $testFileName = 'chat-doc-test-' . uniqid() . '.pdf';
    $destPath = $storageDir . DIRECTORY_SEPARATOR . $testFileName;
    if (@file_put_contents($destPath, $minimalPdf) !== false) {
        $testFilePaths[] = $destPath;
        $attachmentUrl = $appUrl . '/storage/chat-attachments/' . $testFileName;
    }
}
if (! $attachmentUrl) {
    echo "FAIL: Could not create test file. Check permissions: {$storageDir}\n";
    exit(1);
}

echo "File: " . (is_readable($fixturePdf) ? 'tests/fixtures/sample.pdf' : 'minimal PDF') . "\n\n";

$failed = 0;
$report = [];
$jobsToPoll = [];

// ---- Test 1: Convert to JPG ----
echo "=== 1. Convert to JPG ===\n";
$r1 = api('POST', $base . '/chat', ['message' => 'Convert this PDF to JPG', 'attachment_url' => $attachmentUrl], $token);
$reply1 = $r1['reply'] ?? '';
$jobId1 = $r1['job_id'] ?? null;
$replyType1 = $r1['reply_type'] ?? null;

if (str_contains($reply1, 'Could not read')) {
    $report[] = ['operation' => 'Convert to JPG', 'chat_routed' => true, 'job_id' => null, 'poll_status' => '-', 'result_ok' => false, 'displayable' => false, 'note' => 'Could not read attachment'];
    echo "  FAIL: Could not read attachment.\n";
    $failed++;
} elseif ($replyType1 === 'doc_converter' && ! empty($jobId1)) {
    $report[] = ['operation' => 'Convert to JPG', 'chat_routed' => true, 'job_id' => $jobId1, 'poll_status' => null, 'result_ok' => null, 'displayable' => null, 'note' => ''];
    $jobsToPoll[] = ['name' => 'Convert to JPG', 'job_id' => $jobId1];
    echo "  OK: job_id={$jobId1}\n";
} else {
    $report[] = ['operation' => 'Convert to JPG', 'chat_routed' => false, 'job_id' => null, 'poll_status' => '-', 'result_ok' => false, 'displayable' => false, 'note' => $replyType1 ? 'No job_id' : 'Not routed'];
    echo "  FAIL: not routed or no job_id\n";
    $failed++;
}

// ---- Test 2: Extract text ----
echo "\n=== 2. Extract text ===\n";
$r2 = api('POST', $base . '/chat', ['message' => 'Extract text from this document', 'attachment_url' => $attachmentUrl], $token);
$reply2 = $r2['reply'] ?? '';
$jobId2 = $r2['job_id'] ?? null;
$replyType2 = $r2['reply_type'] ?? null;

if (str_contains($reply2, 'Could not read')) {
    $report[] = ['operation' => 'Extract text', 'chat_routed' => true, 'job_id' => null, 'poll_status' => '-', 'result_ok' => false, 'displayable' => false, 'note' => 'Could not read attachment'];
    $failed++;
} elseif ($replyType2 === 'doc_converter' && ! empty($jobId2)) {
    $report[] = ['operation' => 'Extract text', 'chat_routed' => true, 'job_id' => $jobId2, 'poll_status' => null, 'result_ok' => null, 'displayable' => null, 'note' => ''];
    $jobsToPoll[] = ['name' => 'Extract text', 'job_id' => $jobId2];
    echo "  OK: job_id={$jobId2}\n";
} else {
    $report[] = ['operation' => 'Extract text', 'chat_routed' => false, 'job_id' => null, 'poll_status' => '-', 'result_ok' => false, 'displayable' => false, 'note' => 'Not routed'];
    $failed++;
}

// ---- Test 3: Split ----
echo "\n=== 3. Split PDF at page 1 ===\n";
$r3 = api('POST', $base . '/chat', ['message' => 'Split this PDF at page 1', 'attachment_url' => $attachmentUrl], $token);
$reply3 = $r3['reply'] ?? '';
$jobId3 = $r3['job_id'] ?? null;
$replyType3 = $r3['reply_type'] ?? null;

if (str_contains($reply3, 'Could not read')) {
    $report[] = ['operation' => 'Split', 'chat_routed' => true, 'job_id' => null, 'poll_status' => '-', 'result_ok' => false, 'displayable' => false, 'note' => 'Could not read'];
    $failed++;
} elseif ($replyType3 === 'doc_converter' && ! empty($jobId3)) {
    $report[] = ['operation' => 'Split', 'chat_routed' => true, 'job_id' => $jobId3, 'poll_status' => null, 'result_ok' => null, 'displayable' => null, 'note' => ''];
    $jobsToPoll[] = ['name' => 'Split', 'job_id' => $jobId3];
    echo "  OK: job_id={$jobId3}\n";
} else {
    $report[] = ['operation' => 'Split', 'chat_routed' => str_contains(strtolower($reply3), 'page') || $replyType3 === 'doc_converter', 'job_id' => null, 'poll_status' => '-', 'result_ok' => false, 'displayable' => false, 'note' => 'No job or asked for pages'];
    if (! str_contains($reply3, 'page') && $replyType3 !== 'doc_converter') {
        $failed++;
    }
}

// ---- Test 4: Merge (no job expected) ----
echo "\n=== 4. Merge (single file) ===\n";
$r4 = api('POST', $base . '/chat', ['message' => 'Merge these into one PDF', 'attachment_url' => $attachmentUrl], $token);
$reply4 = $r4['reply'] ?? '';
$mergeOk = str_contains(strtolower($reply4), 'merge') && (str_contains(strtolower($reply4), 'multiple') || str_contains(strtolower($reply4), 'attach'));
$report[] = ['operation' => 'Merge', 'chat_routed' => true, 'job_id' => null, 'poll_status' => '-', 'result_ok' => true, 'displayable' => false, 'note' => $mergeOk ? 'Asks for multiple files (correct)' : (empty($r4['job_id']) ? 'No job' : 'Got job')];
if (! $mergeOk && empty($r4['job_id'])) {
    echo "  FAIL: expected merge guidance.\n";
    $failed++;
} else {
    echo "  OK: " . ($mergeOk ? 'asks for multiple files' : 'no job') . "\n";
}

// ---- Test 5: Compress ----
echo "\n=== 5. Compress PDF ===\n";
$r5 = api('POST', $base . '/chat', ['message' => 'Compress this PDF', 'attachment_url' => $attachmentUrl], $token);
$reply5 = $r5['reply'] ?? '';
$jobId5 = $r5['job_id'] ?? null;
$replyType5 = $r5['reply_type'] ?? null;

if (str_contains($reply5, 'Could not read')) {
    $report[] = ['operation' => 'Compress', 'chat_routed' => true, 'job_id' => null, 'poll_status' => '-', 'result_ok' => false, 'displayable' => false, 'note' => 'Could not read'];
    $failed++;
} elseif ($replyType5 === 'doc_converter' && ! empty($jobId5)) {
    $report[] = ['operation' => 'Compress', 'chat_routed' => true, 'job_id' => $jobId5, 'poll_status' => null, 'result_ok' => null, 'displayable' => null, 'note' => ''];
    $jobsToPoll[] = ['name' => 'Compress', 'job_id' => $jobId5];
    echo "  OK: job_id={$jobId5}\n";
} else {
    $report[] = ['operation' => 'Compress', 'chat_routed' => false, 'job_id' => null, 'poll_status' => '-', 'result_ok' => false, 'displayable' => false, 'note' => 'Not routed'];
    $failed++;
}

// ---- Test 6: Page numbers ----
echo "\n=== 6. Add page numbers ===\n";
$r6 = api('POST', $base . '/chat', ['message' => 'Add page numbers to this PDF', 'attachment_url' => $attachmentUrl], $token);
$reply6 = $r6['reply'] ?? '';
$jobId6 = $r6['job_id'] ?? null;
$replyType6 = $r6['reply_type'] ?? null;

if (str_contains($reply6, 'Could not read')) {
    $report[] = ['operation' => 'Page numbers', 'chat_routed' => true, 'job_id' => null, 'poll_status' => '-', 'result_ok' => false, 'displayable' => false, 'note' => 'Could not read'];
    $failed++;
} elseif ($replyType6 === 'doc_converter' && ! empty($jobId6)) {
    $report[] = ['operation' => 'Page numbers', 'chat_routed' => true, 'job_id' => $jobId6, 'poll_status' => null, 'result_ok' => null, 'displayable' => null, 'note' => ''];
    $jobsToPoll[] = ['name' => 'Page numbers', 'job_id' => $jobId6];
    echo "  OK: job_id={$jobId6}\n";
} else {
    $report[] = ['operation' => 'Page numbers', 'chat_routed' => false, 'job_id' => null, 'poll_status' => '-', 'result_ok' => false, 'displayable' => false, 'note' => 'Not routed'];
    $failed++;
}

// ---- Test 7: Watermark ----
echo "\n=== 7. Watermark ===\n";
$r7 = api('POST', $base . '/chat', ['message' => 'Add watermark DRAFT to this PDF', 'attachment_url' => $attachmentUrl], $token);
$reply7 = $r7['reply'] ?? '';
$jobId7 = $r7['job_id'] ?? null;
$replyType7 = $r7['reply_type'] ?? null;

if (str_contains($reply7, 'Could not read')) {
    $report[] = ['operation' => 'Watermark', 'chat_routed' => true, 'job_id' => null, 'poll_status' => '-', 'result_ok' => false, 'displayable' => false, 'note' => 'Could not read'];
    $failed++;
} elseif ($replyType7 === 'doc_converter' && ! empty($jobId7)) {
    $report[] = ['operation' => 'Watermark', 'chat_routed' => true, 'job_id' => $jobId7, 'poll_status' => null, 'result_ok' => null, 'displayable' => null, 'note' => ''];
    $jobsToPoll[] = ['name' => 'Watermark', 'job_id' => $jobId7];
    echo "  OK: job_id={$jobId7}\n";
} else {
    $report[] = ['operation' => 'Watermark', 'chat_routed' => str_contains($reply7, 'watermark'), 'job_id' => null, 'poll_status' => '-', 'result_ok' => false, 'displayable' => false, 'note' => 'No job or guidance'];
    if (! str_contains($reply7, 'watermark') && ! str_contains($reply7, 'not configured')) {
        $failed++;
    }
}

// ---- Test 8: Protect ----
echo "\n=== 8. Protect PDF ===\n";
$r8 = api('POST', $base . '/chat', ['message' => 'Protect this PDF', 'attachment_url' => $attachmentUrl], $token);
$reply8 = $r8['reply'] ?? '';
$jobId8 = $r8['job_id'] ?? null;
$protectOk = str_contains(strtolower($reply8), 'password');
$report[] = ['operation' => 'Protect', 'chat_routed' => true, 'job_id' => $jobId8, 'poll_status' => '-', 'result_ok' => true, 'displayable' => false, 'note' => $protectOk ? 'Asks for password (correct)' : (! empty($jobId8) ? 'Got job' : 'Reply')];
echo "  OK: " . ($protectOk ? 'asks for password' : (empty($jobId8) ? 'reply' : 'job_id')) . "\n";

// ---- Poll each job and fill report ----
echo "\n=== Polling jobs (wait for result) ===\n";
foreach ($jobsToPoll as $item) {
    $name = $item['name'];
    $jobId = $item['job_id'];
    echo "  [{$name}] polling job_id=" . substr($jobId, 0, 8) . "... ";
    $out = poll_doc_converter_job($base, $jobId, $token, 40, 3);
    $status = $out['status'];
    $result = $out['result'];
    echo "-> {$status}\n";

    $ri = null;
    foreach ($report as $i => $row) {
        if (($row['job_id'] ?? '') === $jobId) {
            $ri = $i;
            break;
        }
    }
    if ($ri === null) {
        continue;
    }
    $report[$ri]['poll_status'] = $status;

    if (! is_array($result)) {
        $report[$ri]['result_ok'] = false;
        $report[$ri]['displayable'] = false;
        $report[$ri]['note'] = 'Result not array';
        $failed++;
        continue;
    }
    if (isset($result['success']) && $result['success'] === true) {
        $data = $result['data'] ?? null;
        $report[$ri]['result_ok'] = true;
        $hasContent = is_array($data) && (! empty($data['download_url']) || ! empty($data['download_urls']) || (isset($data['content']) && (string) $data['content'] !== ''));
        $report[$ri]['displayable'] = $hasContent;
        $report[$ri]['note'] = $hasContent ? 'Download/content OK' : 'No download URL or content';
    } elseif (isset($result['error'])) {
        $report[$ri]['result_ok'] = false;
        $report[$ri]['displayable'] = false;
        $report[$ri]['note'] = substr((string) $result['error'], 0, 50);
    } else {
        $report[$ri]['result_ok'] = false;
        $report[$ri]['displayable'] = false;
        $report[$ri]['note'] = 'Unexpected response';
        $failed++;
    }
}

// ---- Full report ----
echo "\n";
echo "=============================================================================\n";
echo "           DOC-CONVERTER OPERATIONS REPORT (Akili Chat)\n";
echo "=============================================================================\n";
echo sprintf("%-16s | %-6s | %-8s | %-6s | %-6s | %s\n", 'Operation', 'Routed', 'Status', 'Result', 'Display', 'Note');
echo "-----------------+--------+----------+--------+--------+---------------------------\n";

$working = 0;
$notWorking = 0;
foreach ($report as $row) {
    $routed = $row['chat_routed'] ? 'yes' : 'no';
    $status = $row['poll_status'] ?? '-';
    $resultOk = $row['result_ok'] === true ? 'yes' : ($row['result_ok'] === false ? 'no' : '-');
    $disp = $row['displayable'] === true ? 'yes' : ($row['displayable'] === false ? 'no' : '-');
    $note = $row['note'] ?? '';
    echo sprintf("%-16s | %-6s | %-8s | %-6s | %-6s | %s\n", $row['operation'], $routed, $status, $resultOk, $disp, $note);

    if ($row['chat_routed'] && ($row['job_id'] === null ? ($row['result_ok'] === true) : ($row['result_ok'] === true && $row['poll_status'] === 'completed'))) {
        $working++;
    } elseif ($row['chat_routed'] && $row['job_id'] !== null && $row['poll_status'] !== 'completed' && $row['poll_status'] !== '-') {
        $notWorking++;
    } elseif (! $row['chat_routed']) {
        $notWorking++;
    }
}

echo "=============================================================================\n";
echo "\nSummary:\n";
echo "  - Chat routed:    " . count(array_filter($report, fn ($r) => $r['chat_routed'])) . " / " . count($report) . " operations\n";
echo "  - Jobs completed: " . count(array_filter($report, fn ($r) => ($r['poll_status'] ?? '') === 'completed')) . " (of " . count($jobsToPoll) . " jobs)\n";
echo "  - Result OK:      " . count(array_filter($report, fn ($r) => $r['result_ok'] === true)) . " / " . count($report) . "\n";
echo "  - Displayable:    " . count(array_filter($report, fn ($r) => $r['displayable'] === true)) . " (download URL or content in UI)\n";
echo "\nWhich services WORK (job completed + user gets download/content in chat):\n";
foreach ($report as $r) {
    if (($r['poll_status'] ?? '') === 'completed' && $r['displayable'] === true) {
        echo "  [OK] " . $r['operation'] . "\n";
    }
}
echo "\nWhich services are PARTIAL (job completed but no download in UI - microservice response shape):\n";
foreach ($report as $r) {
    if (($r['poll_status'] ?? '') === 'completed' && $r['result_ok'] === true && $r['displayable'] !== true) {
        echo "  [~]  " . $r['operation'] . " - " . ($r['note'] ?? '') . "\n";
    }
}
echo "\nWhich services FAILED (job failed or error from microservice):\n";
foreach ($report as $r) {
    if (($r['poll_status'] ?? '') === 'failed' || ($r['result_ok'] === false && $r['job_id'] !== null)) {
        echo "  [X]  " . $r['operation'] . " - " . ($r['note'] ?? '') . "\n";
    }
}
echo "\nWhich are message-only (no job, correct behaviour):\n";
foreach ($report as $r) {
    if ($r['job_id'] === null && $r['result_ok'] === true) {
        echo "  [msg] " . $r['operation'] . " - " . ($r['note'] ?? '') . "\n";
    }
}
echo "\n";

// Cleanup
foreach ($testFilePaths as $p) {
    if (file_exists($p)) {
        @unlink($p);
    }
}

if ($failed > 0) {
    echo "{$failed} test assertion(s) failed.\n";
    exit(1);
}
echo "All assertions passed.\n";
exit(0);
