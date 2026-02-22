#!/usr/bin/env php
<?php
/**
 * Test diagram result flow from CLI (same code path as the controller).
 * Run: php test-diagram-result-cli.php <job_id>
 * Example: php test-diagram-result-cli.php e5892b7a-31ca-4f77-aab6-3fd68c70f2b2
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$jobId = $argv[1] ?? null;
if (! $jobId) {
    echo "Usage: php test-diagram-result-cli.php <job_id>\n";
    exit(1);
}

$client = app(\App\Services\Tools\DiagramMicroserviceClient::class);
$result = $client->getJobResult($jobId);

if (! $result['success']) {
    echo "Microservice error: " . ($result['error'] ?? 'unknown') . "\n";
    exit(1);
}

$data = $result['data'] ?? [];
$inner = is_array($data) && isset($data['data']) ? $data['data'] : $data;
$downloadUrl = $inner['download_url'] ?? $inner['image_url'] ?? $data['download_url'] ?? $data['image_url'] ?? null;
$downloadUrl = is_string($downloadUrl) ? trim($downloadUrl) : null;
if ($downloadUrl === '') {
    $downloadUrl = null;
}

echo "data keys: " . implode(', ', array_keys(is_array($data) ? $data : [])) . "\n";
echo "download_url: " . ($downloadUrl ?: '(empty)') . "\n";

$apiKey = config('services.diagram.api_key');
$hasKey = ! empty($apiKey);
echo "config services.diagram.api_key: " . ($hasKey ? 'set (' . strlen($apiKey) . ' chars)' : 'EMPTY') . "\n";

if (! $downloadUrl) {
    echo "No download URL in response. Frontend will show 'no image'.\n";
    exit(0);
}

$headers = $hasKey ? ['X-API-Key' => $apiKey] : [];
$imageResponse = \Illuminate\Support\Facades\Http::withHeaders($headers)->timeout(60)->get($downloadUrl);

echo "Fetch status: " . $imageResponse->status() . ", body size: " . strlen($imageResponse->body()) . "\n";

if ($imageResponse->successful()) {
    echo "SUCCESS: Backend would return file_id. If frontend still fails, the live request may be using different code or config (e.g. opcache, different docroot).\n";
} else {
    echo "FAIL: Download returned " . $imageResponse->status() . ". Fix: ensure X-API-Key is sent (API key is " . ($hasKey ? 'set' : 'MISSING') . ").\n";
}
