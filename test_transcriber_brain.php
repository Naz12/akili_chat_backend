#!/usr/bin/env php
<?php
require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;
use App\Models\User;
use App\Services\BrainOrchestrator;

$ytUrl = 'https://www.youtube.com/watch?v=x9B02pFKpJo';
$transcriberUrl = rtrim((string) config('services.brain.transcriber_url'), '/');
$clientKey = (string) config('services.brain.transcriber_client_key');

echo "=== Transcriber /duration test ===\n";
try {
    $resp = Http::withHeaders(['X-Client-Key' => $clientKey])->timeout(15)->get($transcriberUrl.'/duration', ['url' => $ytUrl]);
    echo "Status: ". $resp->status() ."\n";
    echo "Body: ". substr($resp->body(), 0, 200) ."...\n\n";
} catch (Throwable $e) {
    echo "Transcriber call failed: ".$e->getMessage()."\n\n";
}

echo "=== BrainOrchestrator YouTube test ===\n";
$user = User::first();
if (!$user) { echo "No user found in DB.\n"; exit(1); }
$brain = new BrainOrchestrator();
$result = $brain->processYouTube($ytUrl, $user, 'Summarize the key points in 8 concise bullets.');
if ($result && isset($result['content'])) {
    echo "Result length: ". strlen($result['content']) ."\n";
    echo "Preview: \n". substr($result['content'], 0, 500) ."\n";
    echo "Trace steps: ". count($result['trace'] ?? []) ."\n";
} else {
    echo "BrainOrchestrator returned no content.\n";
}
