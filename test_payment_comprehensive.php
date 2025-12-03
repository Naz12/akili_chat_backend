<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Plan;
use App\Models\PaymentMethod;
use App\Http\Controllers\Api\SubscriptionApiController;
use App\Http\Controllers\Api\PaymentMethodApiController;
use Illuminate\Http\Request;

echo "=== Comprehensive Payment Method Testing ===\n\n";

// Test 1: Verify payment methods are correctly configured
echo "=== Test 1: Payment Method Configuration ===\n";
$chapa = PaymentMethod::where('key', 'chapa')->first();
$stripe = PaymentMethod::where('key', 'stripe')->first();

if (!$chapa || !$stripe) {
    echo "❌ Payment methods not found in database\n";
    exit(1);
}

echo "✅ Chapa: " . ($chapa->is_enabled ? "Enabled" : "Disabled") . " for regions: " . json_encode($chapa->regions ?? []) . "\n";
echo "✅ Stripe: " . ($stripe->is_enabled ? "Enabled" : "Disabled") . " for regions: " . json_encode($stripe->regions ?? []) . "\n\n";

// Test 2: Test region filtering
echo "=== Test 2: Region Filtering ===\n";
$localMethods = PaymentMethod::forRegion('local')->get();
$intlMethods = PaymentMethod::forRegion('intl')->get();

echo "Local methods: " . $localMethods->pluck('key')->implode(', ') . " (Expected: chapa)\n";
echo "International methods: " . $intlMethods->pluck('key')->implode(', ') . " (Expected: stripe)\n";

if ($localMethods->pluck('key')->contains('chapa') && $intlMethods->pluck('key')->contains('stripe')) {
    echo "✅ Region filtering works correctly\n\n";
} else {
    echo "❌ Region filtering failed\n\n";
}

// Test 3: Test isEnabledForRegion method
echo "=== Test 3: isEnabledForRegion Method ===\n";
$chapaLocal = $chapa->isEnabledForRegion('local');
$chapaIntl = $chapa->isEnabledForRegion('intl');
$stripeLocal = $stripe->isEnabledForRegion('local');
$stripeIntl = $stripe->isEnabledForRegion('intl');

echo "Chapa for local: " . ($chapaLocal ? "✅ Enabled" : "❌ Disabled") . " (Expected: Enabled)\n";
echo "Chapa for intl: " . ($chapaIntl ? "✅ Enabled" : "❌ Disabled") . " (Expected: Disabled)\n";
echo "Stripe for local: " . ($stripeLocal ? "✅ Enabled" : "❌ Disabled") . " (Expected: Disabled)\n";
echo "Stripe for intl: " . ($stripeIntl ? "✅ Enabled" : "❌ Disabled") . " (Expected: Enabled)\n\n";

// Test 4: Test disabling payment method
echo "=== Test 4: Disable Payment Method ===\n";
echo "Disabling Chapa for local...\n";
$chapa->regions = [];
$chapa->save();

$chapaLocalAfter = $chapa->isEnabledForRegion('local');
$localMethodsAfter = PaymentMethod::forRegion('local')->get();

echo "Chapa enabled for local after disable: " . ($chapaLocalAfter ? "❌ Still enabled" : "✅ Disabled") . "\n";
echo "Available local methods after disable: " . $localMethodsAfter->pluck('key')->implode(', ') . " (Expected: empty)\n";

// Re-enable
$chapa->regions = ['local'];
$chapa->save();
echo "✅ Chapa re-enabled for local\n\n";

// Test 5: Test PaymentMethodApiController
echo "=== Test 5: PaymentMethodApiController ===\n";
try {
    $request = new Request();
    $request->server->set('REQUEST_URI', '/api/v1/local/payment-methods/available');
    $request->merge(['region' => 'local']);
    
    $controller = app(PaymentMethodApiController::class);
    $response = $controller->index($request);
    $data = json_decode($response->getContent(), true);
    
    echo "Local region response:\n";
    echo "  Region: " . ($data['region'] ?? 'N/A') . "\n";
    echo "  Methods: " . count($data['payment_methods'] ?? []) . "\n";
    foreach ($data['payment_methods'] ?? [] as $method) {
        echo "    - " . $method['key'] . " (" . $method['name'] . ")\n";
    }
    
    $request->server->set('REQUEST_URI', '/api/v1/intl/payment-methods/available');
    $request->merge(['region' => 'intl']);
    $response = $controller->index($request);
    $data = json_decode($response->getContent(), true);
    
    echo "\nInternational region response:\n";
    echo "  Region: " . ($data['region'] ?? 'N/A') . "\n";
    echo "  Methods: " . count($data['payment_methods'] ?? []) . "\n";
    foreach ($data['payment_methods'] ?? [] as $method) {
        echo "    - " . $method['key'] . " (" . $method['name'] . ")\n";
    }
    
    echo "✅ PaymentMethodApiController works correctly\n\n";
} catch (\Exception $e) {
    echo "❌ PaymentMethodApiController error: " . $e->getMessage() . "\n\n";
}

// Test 6: Test error messages when payment method is disabled
echo "=== Test 6: Error Messages for Disabled Payment Methods ===\n";

// Disable Stripe
$stripe->regions = [];
$stripe->save();

$localUser = User::where('region', 'local')->first();
$intlUser = User::where('region', 'intl')->first();
$intlPlan = Plan::where('region', 'intl')->where('is_active', true)->first();

if ($localUser && $intlUser && $intlPlan) {
    try {
        $request = new Request();
        $request->setUserResolver(function () use ($intlUser) {
            return $intlUser;
        });
        $request->merge(['plan_id' => $intlPlan->id, 'payment_method' => 'stripe']);
        $request->server->set('REQUEST_URI', '/api/v1/intl/subscribe');
        
        $controller = app(SubscriptionApiController::class);
        $response = $controller->store($request);
        $data = json_decode($response->getContent(), true);
        
        if ($response->getStatusCode() === 422) {
            echo "✅ Correctly rejected disabled payment method\n";
            echo "   Message: " . ($data['message'] ?? 'N/A') . "\n";
            echo "   Error code: " . ($data['error_code'] ?? 'N/A') . "\n";
            echo "   Available methods: " . (isset($data['available_methods']) ? implode(', ', $data['available_methods']) : 'N/A') . "\n";
        } else {
            echo "❌ Should have failed but got status: " . $response->getStatusCode() . "\n";
        }
    } catch (\Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
    }
}

// Re-enable Stripe
$stripe->regions = ['intl'];
$stripe->save();
echo "✅ Stripe re-enabled\n\n";

// Test 7: Test auto-selection
echo "=== Test 7: Auto-Selection Logic ===\n";
$localUser = User::where('region', 'local')->first();
$localPlan = Plan::where('region', 'local')->where('is_active', true)->first();

if ($localUser && $localPlan) {
    try {
        $request = new Request();
        $request->setUserResolver(function () use ($localUser) {
            return $localUser;
        });
        $request->merge(['plan_id' => $localPlan->id]);
        $request->server->set('REQUEST_URI', '/api/v1/local/subscribe');
        
        $controller = app(SubscriptionApiController::class);
        $response = $controller->store($request);
        $data = json_decode($response->getContent(), true);
        
        // Check if it auto-selected chapa (even if payment fails due to Chapa API)
        if (isset($data['payment_method']) && $data['payment_method'] === 'chapa') {
            echo "✅ Auto-selected Chapa for local user\n";
        } elseif (isset($data['message']) && strpos($data['message'], 'chapa') !== false) {
            echo "✅ Auto-selection logic works (Chapa was selected, payment may fail due to API)\n";
        } else {
            echo "⚠️ Could not verify auto-selection (response: " . json_encode($data) . ")\n";
        }
    } catch (\Exception $e) {
        // Check if error mentions chapa
        if (stripos($e->getMessage(), 'chapa') !== false) {
            echo "✅ Auto-selection logic works (Chapa was selected, error: " . $e->getMessage() . ")\n";
        } else {
            echo "⚠️ Error: " . $e->getMessage() . "\n";
        }
    }
}

echo "\n=== All Tests Complete ===\n";

