#!/usr/bin/env php
<?php
// Quick integration test - config and connectivity only

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Brain Orchestration Quick Test ===\n\n";

// Test 1: Configuration
echo "--- Configuration ---\n";
$transcriberUrl = config('services.brain.transcriber_url');
$docServiceUrl = config('services.brain.doc_service_url');
$hmacSecret = config('services.brain.hmac_secret');

$configOk = true;
if ($transcriberUrl) {
    echo "✓ TRANSCRIBER_URL: {$transcriberUrl}\n";
} else {
    echo "❌ TRANSCRIBER_URL: NOT SET\n";
    $configOk = false;
}

if ($docServiceUrl) {
    echo "✓ DOC_SERVICE_URL: {$docServiceUrl}\n";
} else {
    echo "❌ DOC_SERVICE_URL: NOT SET\n";
    $configOk = false;
}

if ($hmacSecret) {
    echo "✓ DAGU_HMAC_SECRET: " . substr($hmacSecret, 0, 8) . "... (length: " . strlen($hmacSecret) . ")\n";
} else {
    echo "❌ DAGU_HMAC_SECRET: NOT SET\n";
    $configOk = false;
}

// Test 2: Service connectivity
echo "\n--- Service Connectivity ---\n";
$transcriberOk = false;
$docServiceOk = false;

if ($transcriberUrl) {
    try {
        $ch = curl_init($transcriberUrl . '/');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 2,
            CURLOPT_FOLLOWLOCATION => false,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        // Transcriber returns 401 when auth required - means it's online
        if ($httpCode >= 200 && $httpCode < 300) {
            echo "✓ Transcriber: ONLINE (HTTP {$httpCode})\n";
            $transcriberOk = true;
        } elseif ($httpCode === 401) {
            echo "✓ Transcriber: ONLINE (requires auth - HTTP 401)\n";
            $transcriberOk = true;
        } else {
            echo "⚠ Transcriber: responded with HTTP {$httpCode}\n";
        }
    } catch (\Throwable $e) {
        echo "❌ Transcriber: UNREACHABLE - " . $e->getMessage() . "\n";
    }
}

if ($docServiceUrl) {
    try {
        $ch = curl_init($docServiceUrl . '/health');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 2,
            CURLOPT_FOLLOWLOCATION => false,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            echo "✓ Doc-service: ONLINE (HTTP {$httpCode})\n";
            $docServiceOk = true;
        } else {
            echo "⚠ Doc-service: responded with HTTP {$httpCode}\n";
        }
    } catch (\Throwable $e) {
        echo "❌ Doc-service: UNREACHABLE - " . $e->getMessage() . "\n";
    }
}

// Test 3: Database table
echo "\n--- Database Schema ---\n";
try {
    $exists = \Illuminate\Support\Facades\Schema::hasTable('chat_session_docs');
    if ($exists) {
        $count = \Illuminate\Support\Facades\DB::table('chat_session_docs')->count();
        echo "✓ chat_session_docs table exists ({$count} records)\n";
    } else {
        echo "❌ chat_session_docs table NOT FOUND\n";
    }
} catch (\Throwable $e) {
    echo "❌ Database check failed: " . $e->getMessage() . "\n";
}

// Test 4: HMAC signature generation
echo "\n--- HMAC Test ---\n";
if ($hmacSecret) {
    $testData = "POST|/v1/test||" . time() . "|akili-backend|default";
    $sig = hash_hmac('sha256', $testData, $hmacSecret);
    echo "✓ HMAC generation working\n";
    echo "  Sample signature: " . substr($sig, 0, 16) . "...\n";
} else {
    echo "❌ Cannot test HMAC - secret not set\n";
}

// Summary
echo "\n=== Test Summary ===\n";
$allGood = $configOk && $transcriberOk && $docServiceOk;

if ($allGood) {
    echo "✅ All systems ready for brain orchestration!\n";
    echo "\nYou can now:\n";
    echo "  1. Send chat with YouTube URL for transcription + summary\n";
    echo "  2. Send chat with document attachment for ingestion + Q&A\n";
    echo "  3. Continue multi-turn chat over same document\n";
    echo "  4. Use stream=true for real-time SSE updates\n";
} else {
    echo "⚠ System partially ready\n";
    if (!$configOk) {
        echo "  - Fix .env configuration\n";
        echo "  - Run: php artisan config:clear\n";
    }
    if (!$transcriberOk) {
        echo "  - Check transcriber service is running on port 8001\n";
    }
    if (!$docServiceOk) {
        echo "  - Check doc-service is running on port 8012\n";
    }
}

echo "\n";
