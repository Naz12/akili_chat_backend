<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

echo "=== COMPREHENSIVE ENDPOINT TESTING ===\n\n";

$testUser = User::where('email', 'test3@example.com')->first();
if (!$testUser) {
    echo "❌ Test user not found\n";
    exit(1);
}

echo "Test User: {$testUser->email} (ID: {$testUser->id}, Region: {$testUser->region})\n\n";

$results = [];
$region = $testUser->region;
$token = auth('api')->login($testUser);

// Helper to test endpoint
function testEndpoint($name, $controller, $method, $request, $expectedStatus = 200, $extraParams = []) {
    global $results;
    try {
        $params = array_merge([$request], $extraParams);
        $response = call_user_func_array([$controller, $method], $params);
        
        $statusCode = 200;
        if ($response instanceof \Illuminate\Http\JsonResponse) {
            $statusCode = $response->getStatusCode();
        } elseif (method_exists($response, 'getStatusCode')) {
            $statusCode = $response->getStatusCode();
        }
        
        if ($statusCode === $expectedStatus || ($expectedStatus === 200 && $statusCode < 300)) {
            echo "✅ $name - Status: $statusCode\n";
            $results[$name] = ['status' => 'pass', 'code' => $statusCode];
            return true;
        } else {
            echo "❌ $name - Status: $statusCode (expected $expectedStatus)\n";
            $results[$name] = ['status' => 'fail', 'code' => $statusCode];
            return false;
        }
    } catch (\Exception $e) {
        $msg = $e->getMessage();
        if (strpos($msg, '404') !== false || strpos($msg, 'not found') !== false) {
            if ($expectedStatus === 404) {
                echo "✅ $name - Status: 404 (expected)\n";
                $results[$name] = ['status' => 'pass', 'code' => 404];
                return true;
            }
        }
        echo "❌ $name - Error: " . substr($msg, 0, 100) . "\n";
        $results[$name] = ['status' => 'fail', 'error' => $msg];
        return false;
    }
}

// Helper to create authenticated request
function createAuthRequest($method, $uri, $token, $data = null, $user = null) {
    global $testUser;
    $request = Request::create($uri, $method, $data ?? []);
    $request->headers->set('Authorization', "Bearer $token");
    JWTAuth::setToken($token);
    // Set user resolver so $request->user() works
    $request->setUserResolver(function() use ($user, $testUser) {
        return $user ?? $testUser;
    });
    return $request;
}

// Helper to create guest request
function createGuestRequest($method, $uri, $data = null) {
    return Request::create($uri, $method, $data ?? []);
}

echo "=== AUTHENTICATED USER TESTS ===\n\n";

// Chat Sessions
$sessionsRequest = createAuthRequest('GET', "/api/v1/$region/chat/sessions", $token, null, $testUser);
$sessionsController = app(\App\Http\Controllers\Api\AiChatHistoryApiController::class);
testEndpoint('GET /chat/sessions (auth)', $sessionsController, 'sessions', $sessionsRequest);

// Chat Sessions with messages
$sessionsWithMsgRequest = createAuthRequest('GET', "/api/v1/$region/chat/sessions?with_messages=true", $token, null, $testUser);
testEndpoint('GET /chat/sessions?with_messages=true (auth)', $sessionsController, 'sessions', $sessionsWithMsgRequest);

// Get a session ID for testing
$sessionsResponse = $sessionsController->sessions($sessionsRequest);
$sessionsData = json_decode($sessionsResponse->getContent(), true);
$testSessionId = $sessionsData[0]['id'] ?? null;

if ($testSessionId) {
    // Chat Messages
    $messagesRequest = createAuthRequest('GET', "/api/v1/$region/chat/messages/$testSessionId", $token, null, $testUser);
    testEndpoint('GET /chat/messages/{id} (auth)', $sessionsController, 'messages', $messagesRequest, 200, [$testSessionId]);
    
    // Rename Session
    $renameRequest = createAuthRequest('PUT', "/api/v1/$region/chat/sessions/$testSessionId", $token, ['title' => 'Test Rename'], $testUser);
    testEndpoint('PUT /chat/sessions/{id} (auth)', $sessionsController, 'rename', $renameRequest, 200, [$testSessionId]);
}

// User Profile
$userRequest = createAuthRequest('GET', "/api/v1/$region/user", $token, null, $testUser);
$userController = app(\App\Http\Controllers\Api\UserApiController::class);
testEndpoint('GET /user (auth)', $userController, 'profile', $userRequest);

// Plans
$plansRequest = createAuthRequest('GET', "/api/v1/$region/plans", $token, null, $testUser);
$plansController = app(\App\Http\Controllers\Api\PlanApiController::class);
testEndpoint('GET /plans (auth)', $plansController, 'index', $plansRequest);

// Subscription
$subRequest = createAuthRequest('GET', "/api/v1/$region/subscription", $token, null, $testUser);
$subController = app(\App\Http\Controllers\Api\SubscriptionApiController::class);
try {
    $subResponse = $subController->current($subRequest);
    if ($subResponse instanceof \Illuminate\Http\Resources\Json\JsonResource || 
        (method_exists($subResponse, 'getStatusCode') && $subResponse->getStatusCode() < 300)) {
        echo "✅ GET /subscription (auth) - Status: 200\n";
        $results['GET /subscription (auth)'] = ['status' => 'pass', 'code' => 200];
    } else {
        echo "❌ GET /subscription (auth) - Failed\n";
        $results['GET /subscription (auth)'] = ['status' => 'fail'];
    }
} catch (\Exception $e) {
    if (strpos($e->getMessage(), '404') !== false || strpos($e->getMessage(), 'not found') !== false) {
        echo "✅ GET /subscription (auth) - Status: 404 (no subscription)\n";
        $results['GET /subscription (auth)'] = ['status' => 'pass', 'code' => 404];
    } else {
        echo "❌ GET /subscription (auth) - Error: " . substr($e->getMessage(), 0, 100) . "\n";
        $results['GET /subscription (auth)'] = ['status' => 'fail'];
    }
}

// Billing
$billingRequest = createAuthRequest('GET', "/api/v1/$region/billing/current", $token, null, $testUser);
$billingController = app(\App\Http\Controllers\Api\BillApiController::class);
try {
    $billingResponse = $billingController->current($billingRequest);
    $statusCode = $billingResponse instanceof \Illuminate\Http\JsonResponse 
        ? $billingResponse->getStatusCode() 
        : ($billingResponse instanceof \Illuminate\Http\Resources\Json\JsonResource ? 200 : 200);
    if ($statusCode === 200 || $statusCode === 404) {
        echo "✅ GET /billing/current (auth) - Status: $statusCode\n";
        $results['GET /billing/current (auth)'] = ['status' => 'pass', 'code' => $statusCode];
    } else {
        echo "❌ GET /billing/current (auth) - Status: $statusCode\n";
        $results['GET /billing/current (auth)'] = ['status' => 'fail'];
    }
} catch (\Exception $e) {
    if (strpos($e->getMessage(), '404') !== false) {
        echo "✅ GET /billing/current (auth) - Status: 404 (no billing)\n";
        $results['GET /billing/current (auth)'] = ['status' => 'pass', 'code' => 404];
    } else {
        echo "❌ GET /billing/current (auth) - Error: " . substr($e->getMessage(), 0, 100) . "\n";
        $results['GET /billing/current (auth)'] = ['status' => 'fail'];
    }
}

// Billing upcoming charges
$upcomingRequest = createAuthRequest('GET', "/api/v1/$region/billing/upcoming-charges", $token, null, $testUser);
try {
    $upcomingResponse = $billingController->upcomingCharges($upcomingRequest);
    $statusCode = $upcomingResponse instanceof \Illuminate\Http\JsonResponse 
        ? $upcomingResponse->getStatusCode() 
        : 200;
    if ($statusCode === 200) {
        echo "✅ GET /billing/upcoming-charges (auth) - Status: $statusCode\n";
        $results['GET /billing/upcoming-charges (auth)'] = ['status' => 'pass', 'code' => $statusCode];
    } else {
        echo "❌ GET /billing/upcoming-charges (auth) - Status: $statusCode\n";
        $results['GET /billing/upcoming-charges (auth)'] = ['status' => 'fail'];
    }
} catch (\Exception $e) {
    echo "❌ GET /billing/upcoming-charges (auth) - Error: " . substr($e->getMessage(), 0, 100) . "\n";
    $results['GET /billing/upcoming-charges (auth)'] = ['status' => 'fail'];
}

// Token Usage
$usageRequest = createAuthRequest('GET', "/api/v1/$region/token-usage", $token);
$usageController = app(\App\Http\Controllers\Api\TokenUsageApiController::class);
try {
    $usageResponse = $usageController->index($usageRequest);
    $statusCode = $usageResponse instanceof \Illuminate\Http\JsonResponse 
        ? $usageResponse->getStatusCode() 
        : 200;
    if ($statusCode === 200) {
        echo "✅ GET /token-usage (auth) - Status: $statusCode\n";
        $results['GET /token-usage (auth)'] = ['status' => 'pass', 'code' => $statusCode];
    } else {
        echo "❌ GET /token-usage (auth) - Status: $statusCode\n";
        $results['GET /token-usage (auth)'] = ['status' => 'fail'];
    }
} catch (\Exception $e) {
    echo "❌ GET /token-usage (auth) - Error: " . substr($e->getMessage(), 0, 100) . "\n";
    $results['GET /token-usage (auth)'] = ['status' => 'fail'];
}

// Token Usage Stats
$statsRequest = createAuthRequest('GET', "/api/v1/$region/token-usage/stats", $token);
try {
    $statsResponse = $usageController->stats($statsRequest);
    $statusCode = $statsResponse instanceof \Illuminate\Http\JsonResponse 
        ? $statsResponse->getStatusCode() 
        : 200;
    if ($statusCode === 200) {
        echo "✅ GET /token-usage/stats (auth) - Status: $statusCode\n";
        $results['GET /token-usage/stats (auth)'] = ['status' => 'pass', 'code' => $statusCode];
    } else {
        echo "❌ GET /token-usage/stats (auth) - Status: $statusCode\n";
        $results['GET /token-usage/stats (auth)'] = ['status' => 'fail'];
    }
} catch (\Exception $e) {
    echo "❌ GET /token-usage/stats (auth) - Error: " . substr($e->getMessage(), 0, 100) . "\n";
    $results['GET /token-usage/stats (auth)'] = ['status' => 'fail'];
}

// Notifications
$notifRequest = createAuthRequest('GET', "/api/v1/$region/notifications", $token);
$notifController = app(\App\Http\Controllers\Api\NotificationApiController::class);
testEndpoint('GET /notifications (auth)', $notifController, 'index', $notifRequest);

// Check Version (public)
$versionRequest = createGuestRequest('GET', "/api/v1/$region/check-version");
$versionController = app(\App\Http\Controllers\Api\VersionApiController::class);
testEndpoint('GET /check-version (public)', $versionController, 'check', $versionRequest);

echo "\n=== GUEST USER TESTS ===\n\n";

// Chat Sessions (guest)
$guestSessionsRequest = createGuestRequest('GET', "/api/v1/$region/chat/sessions");
testEndpoint('GET /chat/sessions (guest)', $sessionsController, 'sessions', $guestSessionsRequest);

// Chat Messages (guest) - will fail if no guest session
if ($testSessionId) {
    $guestMessagesRequest = createGuestRequest('GET', "/api/v1/$region/chat/messages/$testSessionId");
    // This might fail if session belongs to user, which is expected
    testEndpoint('GET /chat/messages/{id} (guest)', $sessionsController, 'messages', $guestMessagesRequest, 200, [$testSessionId]);
}

echo "\n=== TEST SUMMARY ===\n";
$passed = 0;
$failed = 0;

foreach ($results as $test => $result) {
    if ($result['status'] === 'pass') {
        $passed++;
    } else {
        $failed++;
        echo "❌ $test failed\n";
    }
}

echo "\n✅ Passed: $passed\n";
echo "❌ Failed: $failed\n";
echo "Total: " . ($passed + $failed) . "\n\n";

if ($failed === 0) {
    echo "🎉 All endpoint tests passed!\n";
    exit(0);
} else {
    echo "⚠️  Some tests failed. Review above.\n";
    exit(1);
}
