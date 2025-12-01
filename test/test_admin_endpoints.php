<?php

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Subscription;
use App\Models\Bill;
use App\Models\Visitor;
use Illuminate\Support\Facades\Auth;

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Testing Admin Endpoints ===\n\n";

// Get admin user
$admin = User::where('role', 'admin')->first();
if (!$admin) {
    echo "❌ No admin user found. Please create an admin user first.\n";
    exit(1);
}

echo "✅ Admin user found: {$admin->name} ({$admin->email})\n\n";

// Get regular users
$users = User::where('role', '!=', 'admin')->orWhereNull('role')->limit(3)->get();
echo "✅ Found " . $users->count() . " regular users for testing\n\n";

// Test 1: Dashboard
echo "1. Testing Dashboard...\n";
try {
    Auth::login($admin);
    $controller = new \App\Http\Controllers\Admin\DashboardAdminController();
    $response = $controller->index();
    echo "   ✅ Dashboard loaded successfully\n";
} catch (\Exception $e) {
    echo "   ❌ Dashboard error: " . $e->getMessage() . "\n";
}

// Test 2: Bills Index
echo "\n2. Testing Bills Index...\n";
try {
    $controller = new \App\Http\Controllers\Admin\BillAdminController();
    $request = new \Illuminate\Http\Request(['tab' => 'bills']);
    $response = $controller->index($request);
    echo "   ✅ Bills index loaded successfully\n";
} catch (\Exception $e) {
    echo "   ❌ Bills index error: " . $e->getMessage() . "\n";
}

// Test 3: Bills List
echo "\n3. Testing Bills List...\n";
try {
    $request = new \Illuminate\Http\Request();
    $response = $controller->bills($request);
    echo "   ✅ Bills list loaded successfully\n";
} catch (\Exception $e) {
    echo "   ❌ Bills list error: " . $e->getMessage() . "\n";
}

// Test 4: Subscriptions Index
echo "\n4. Testing Subscriptions Index...\n";
try {
    $controller = new \App\Http\Controllers\Admin\SubscriptionAdminController();
    $response = $controller->index();
    echo "   ✅ Subscriptions index loaded successfully\n";
} catch (\Exception $e) {
    echo "   ❌ Subscriptions index error: " . $e->getMessage() . "\n";
}

// Test 5: Grace Period
echo "\n5. Testing Grace Period...\n";
try {
    $response = $controller->gracePeriod();
    echo "   ✅ Grace period page loaded successfully\n";
} catch (\Exception $e) {
    echo "   ❌ Grace period error: " . $e->getMessage() . "\n";
}

// Test 6: Payment Failures
echo "\n6. Testing Payment Failures...\n";
try {
    $response = $controller->paymentFailures();
    echo "   ✅ Payment failures page loaded successfully\n";
} catch (\Exception $e) {
    echo "   ❌ Payment failures error: " . $e->getMessage() . "\n";
}

// Test 7: Expiring Subscriptions
echo "\n7. Testing Expiring Subscriptions...\n";
try {
    $request = new \Illuminate\Http\Request(['days' => 7]);
    $response = $controller->expiring($request);
    echo "   ✅ Expiring subscriptions page loaded successfully\n";
} catch (\Exception $e) {
    echo "   ❌ Expiring subscriptions error: " . $e->getMessage() . "\n";
}

// Test 8: Stripe Subscriptions
echo "\n8. Testing Stripe Subscriptions...\n";
try {
    $response = $controller->stripeSubscriptions();
    echo "   ✅ Stripe subscriptions page loaded successfully\n";
} catch (\Exception $e) {
    echo "   ❌ Stripe subscriptions error: " . $e->getMessage() . "\n";
}

// Test 9: User History
echo "\n9. Testing User History...\n";
try {
    $user = $users->first();
    if ($user) {
        $controller = new \App\Http\Controllers\Admin\BillAdminController();
        $response = $controller->userHistory($user);
        echo "   ✅ User history loaded successfully for user: {$user->name}\n";
    } else {
        echo "   ⚠️  No users found to test\n";
    }
} catch (\Exception $e) {
    echo "   ❌ User history error: " . $e->getMessage() . "\n";
}

// Test 10: Visitors Index
echo "\n10. Testing Visitors Index...\n";
try {
    $controller = new \App\Http\Controllers\Admin\VisitorAdminController();
    $request = new \Illuminate\Http\Request();
    $response = $controller->index($request);
    echo "   ✅ Visitors index loaded successfully\n";
} catch (\Exception $e) {
    echo "   ❌ Visitors index error: " . $e->getMessage() . "\n";
}

// Test 11: Visitor Analytics
echo "\n11. Testing Visitor Analytics...\n";
try {
    $request = new \Illuminate\Http\Request(['days' => 30]);
    $response = $controller->analytics($request);
    echo "   ✅ Visitor analytics loaded successfully\n";
} catch (\Exception $e) {
    echo "   ❌ Visitor analytics error: " . $e->getMessage() . "\n";
}

// Test 12: Test with real subscription
echo "\n12. Testing with Real Subscription...\n";
try {
    $subscription = Subscription::with('user', 'plan')->first();
    if ($subscription) {
        echo "   ✅ Found subscription ID: {$subscription->id} for user: {$subscription->user->name}\n";
        
        // Test extend grace period
        $request = new \Illuminate\Http\Request(['days' => 3]);
        try {
            $controller = new \App\Http\Controllers\Admin\SubscriptionAdminController();
            $response = $controller->extendGracePeriod($request, $subscription);
            echo "   ✅ Extend grace period works\n";
        } catch (\Exception $e) {
            echo "   ⚠️  Extend grace period: " . $e->getMessage() . "\n";
        }
    } else {
        echo "   ⚠️  No subscriptions found\n";
    }
} catch (\Exception $e) {
    echo "   ❌ Subscription test error: " . $e->getMessage() . "\n";
}

// Test 13: Test with real bill
echo "\n13. Testing with Real Bill...\n";
try {
    $bill = Bill::with('user', 'subscription')->first();
    if ($bill) {
        echo "   ✅ Found bill ID: {$bill->id} for user: {$bill->user->name}\n";
        
        // Test show bill
        $controller = new \App\Http\Controllers\Admin\BillAdminController();
        $response = $controller->showBill($bill);
        echo "   ✅ Show bill works\n";
    } else {
        echo "   ⚠️  No bills found\n";
    }
} catch (\Exception $e) {
    echo "   ❌ Bill test error: " . $e->getMessage() . "\n";
}

// Test 14: Test with real visitor
echo "\n14. Testing with Real Visitor...\n";
try {
    $visitor = Visitor::with('user')->first();
    if ($visitor) {
        echo "   ✅ Found visitor ID: {$visitor->id}\n";
        
        // Test show visitor
        $controller = new \App\Http\Controllers\Admin\VisitorAdminController();
        $response = $controller->show($visitor);
        echo "   ✅ Show visitor works\n";
    } else {
        echo "   ⚠️  No visitors found\n";
    }
} catch (\Exception $e) {
    echo "   ❌ Visitor test error: " . $e->getMessage() . "\n";
}

echo "\n=== Testing Complete ===\n";

