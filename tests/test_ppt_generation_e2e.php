#!/usr/bin/env php
<?php
/**
 * End-to-end test: PPT generation (outline, content, ppt).
 * Run: php tests/test_ppt_generation_e2e.php
 *
 * Use PPT_E2E_USE_MOCK=1 to fake the PPT service (no real HTTP calls). Otherwise
 * requires PRESENTATION_MICROSERVICE_URL; content/ppt paths are optional.
 * Asserts: outline is generated; content and ppt when the service returns them.
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\PresentationService;
use Illuminate\Support\Facades\Http;

$topic = 'Presidents of the United States of America';
$numSlides = 5; // small number for fast test
$useMock = (bool) (getenv('PPT_E2E_USE_MOCK') ?: env('PPT_E2E_USE_MOCK', false));
$baseUrl = config('services.presentation.url');
$failures = [];
$passed = 0;

echo "=== PPT Generation E2E Test ===\n\n";

// --- 0. Optional mock for PPT service ---
if ($useMock) {
    if (empty($baseUrl)) {
        $baseUrl = 'https://ppt.akmicroservice.com';
        config(['services.presentation.url' => $baseUrl]);
    }
    $mockOutline = [
        ['title' => 'Introduction'],
        ['title' => 'George Washington'],
        ['title' => 'Abraham Lincoln'],
        ['title' => 'Modern Presidents'],
        ['title' => 'Summary'],
    ];
    $mockContent = array_map(fn ($s) => ['title' => $s['title'], 'body' => 'Content for ' . $s['title']], $mockOutline);
    $mockPptUrl = ['ppt_url' => 'https://example.com/generated.pptx', 'file_url' => 'https://example.com/generated.pptx'];
    Http::fake([
        $baseUrl . '/*' => Http::sequence()
            ->push(['outline' => $mockOutline, 'outline_id' => 'mock-1'], 200)                    // step 2: outline
            ->push(['content' => $mockContent, 'slides' => $mockContent], 200)                     // step 3: content
            ->push(['outline' => $mockOutline, 'outline_id' => 'mock-1'], 200)                    // step 4: full outline
            ->push(['content' => $mockContent, 'slides' => $mockContent], 200)                     // step 4: full content
            ->push($mockPptUrl, 200)                                                               // step 4: full ppt
            ->push(['outline' => $mockOutline, 'outline_id' => 'mock-1'], 200),                    // step 5: chat outline
    ]);
    echo "(Mock mode: PPT service responses faked)\n\n";
}

// --- 1. Config ---
echo "--- 1. Configuration ---\n";
if (empty($baseUrl)) {
    echo "❌ PRESENTATION_MICROSERVICE_URL is not set. Set it in .env or use PPT_E2E_USE_MOCK=1.\n";
    exit(1);
}
echo "✓ PPT URL: " . $baseUrl . "\n";
echo "  Outline path: " . (config('services.presentation.outline_path') ?: '/outline') . "\n";
echo "  Content path: " . (config('services.presentation.content_path') ?: '(none)') . "\n";
echo "  PPT path: " . (config('services.presentation.ppt_path') ?: '(none)') . "\n\n";

// --- 2. Outline (required) ---
echo "--- 2. Outline generation ---\n";
$service = app(PresentationService::class);
$outlineResult = $service->generateOutline($topic, $numSlides);

if (!$outlineResult['success']) {
    $failures[] = 'Outline: ' . ($outlineResult['message'] ?? 'failed');
    echo "❌ Outline failed: " . ($outlineResult['message'] ?? 'unknown') . "\n";
} else {
    $outline = $outlineResult['outline'] ?? null;
    $hasOutline = is_array($outline) ? count($outline) > 0 : !empty($outline);
    if (!$hasOutline) {
        $failures[] = 'Outline: success but outline empty';
        echo "❌ Outline returned success but outline is empty.\n";
    } else {
        $passed++;
        $count = is_array($outline) ? count($outline) : 1;
        echo "✓ Outline generated ({$count} items)\n";
        if (is_array($outline) && count($outline) <= 5) {
            foreach ($outline as $i => $item) {
                $title = is_array($item) ? ($item['title'] ?? $item['slide'] ?? json_encode($item)) : (string) $item;
                echo "  " . ($i + 1) . ". " . substr($title, 0, 60) . (strlen($title) > 60 ? '...' : '') . "\n";
            }
        }
    }
}

// --- 3. Content (optional: only assert if endpoint is configured and returns success) ---
echo "\n--- 3. Content generation ---\n";
$contentPath = config('services.presentation.content_path');
if (empty($contentPath)) {
    echo "⊘ Content path not set; skipping.\n";
} else {
    $contentResult = $service->generateContent($topic, $outlineResult);
    if (!$contentResult['success']) {
        echo "⊘ Content step failed or not implemented: " . ($contentResult['message'] ?? '') . "\n";
    } else {
        $content = $contentResult['content'] ?? $contentResult['slides'] ?? null;
        $hasContent = is_array($content) ? count($content) > 0 : !empty($content);
        if ($hasContent) {
            $passed++;
            echo "✓ Content generated\n";
        } else {
            $failures[] = 'Content: success but content empty';
            echo "❌ Content returned success but content is empty.\n";
        }
    }
}

// --- 4. Full pipeline (outline -> content -> ppt) ---
echo "\n--- 4. Full pipeline (outline → content → ppt) ---\n";
$fullResult = $service->generateFull($topic, $numSlides);
if (!$fullResult['success']) {
    echo "❌ Full pipeline failed: " . ($fullResult['message'] ?? '') . "\n";
    $failures[] = 'Full pipeline: ' . ($fullResult['message'] ?? 'failed');
} else {
    echo "✓ Full pipeline completed\n";
    $hasOutline = !empty($fullResult['outline']);
    $hasContent = array_key_exists('content', $fullResult) && $fullResult['content'] !== null;
    $hasPpt = !empty($fullResult['ppt_url']);
    if ($hasOutline) {
        echo "  - Outline: " . (is_array($fullResult['outline']) ? count($fullResult['outline']) . ' slides' : 'yes') . "\n";
    }
    if ($hasContent) {
        $passed++;
        echo "  - Content: yes\n";
    }
    if ($hasPpt) {
        $passed++;
        echo "  - PPT URL: " . $fullResult['ppt_url'] . "\n";
    }
    if (!$hasOutline) {
        $failures[] = 'Full pipeline: outline missing';
    }
}

// --- 5. Chat API (POST /chat with ppt message) ---
echo "\n--- 5. Chat API (PPT via POST /chat) ---\n";
try {
    $payload = [
        'message' => 'Generate a ppt about ' . $topic . ' with ' . $numSlides . ' slides',
        'session_id' => null,
    ];
    $request = Illuminate\Http\Request::create(
        '/api/v1/local/chat',
        'POST',
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'application/json'],
        json_encode($payload)
    );
    $request->headers->set('Content-Type', 'application/json');
    $request->headers->set('Accept', 'application/json');
    // Simulate JSON body so $request->input() works
    $request->merge($payload);
    // No auth = guest; ensure guest can be resolved
    $controller = app(\App\Http\Controllers\Api\AIChatApiController::class);
    $response = $controller->handleChat($request);
    $code = $response->getStatusCode();
    $data = json_decode($response->getContent(), true);
    if ($code !== 200) {
        $failures[] = "Chat API returned {$code}: " . ($data['error'] ?? $response->getContent());
        echo "❌ Chat API returned {$code}\n";
    } else {
        $reply = $data['reply'] ?? '';
        $hasOutlineInReply = stripos($reply, 'outline') !== false || stripos($reply, 'Slide') !== false || preg_match('/\d+\.\s+\w+/', $reply);
        if ($hasOutlineInReply && !empty($data['session_id'])) {
            $passed++;
            echo "✓ Chat API returned 200 with outline in reply and session_id\n";
        } else {
            $failures[] = 'Chat API: reply missing outline or session_id';
            echo "❌ Chat API 200 but reply missing outline or session_id\n";
        }
    }
} catch (\Throwable $e) {
    $failures[] = 'Chat API exception: ' . $e->getMessage();
    echo "❌ Chat API exception: " . $e->getMessage() . "\n";
}

// --- Summary ---
echo "\n=== Summary ===\n";
echo "Passed: {$passed}\n";
if (count($failures) > 0) {
    echo "Failures:\n";
    foreach ($failures as $f) {
        echo "  - " . $f . "\n";
    }
    exit(1);
}
echo "All PPT E2E checks passed.\n";
exit(0);
