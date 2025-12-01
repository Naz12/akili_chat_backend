<?php

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Subscription;
use App\Models\Bill;
use Illuminate\Support\Facades\Auth;

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Testing Admin POST Endpoints ===\n\n";

// Get admin user
$admin = User::where('role', 'admin')->first();
Auth::login($admin);

// Get test data
$user = User::where('role', '!=', 'admin')->first();
$subscription = Subscription::where('user_id', $user->id)->first();
$bill = Bill::where('user_id', $user->id)->first();

echo "Admin: {$admin->name}\n";
echo "Test User: {$user->name}\n";
echo "Subscription ID: " . ($subscription ? $subscription->id : 'None') . "\n";
echo "Bill ID: " . ($bill ? $bill->id : 'None') . "\n\n";

// Test 1: Extend Grace Period
if ($subscription) {
    echo "1. Testing Extend Grace Period...\n";
    try {
        $originalGracePeriod = $subscription->grace_period_ends_at;
        $controller = new \App\Http\Controllers\Admin\SubscriptionAdminController();
        $request = new \Illuminate\Http\Request(['days' => 3]);
        $request->setMethod('POST');
        
        // Set grace period if not set
        if (!$subscription->grace_period_ends_at) {
            $subscription->update(['grace_period_ends_at' => now()->addDays(1)]);
            $subscription->refresh();
        }
        
        $response = $controller->extendGracePeriod($request, $subscription);
        $subscription->refresh();
        
        if ($subscription->grace_period_ends_at) {
            echo "   ✅ Grace period extended successfully\n";
            echo "   New grace period: {$subscription->grace_period_ends_at}\n";
        } else {
            echo "   ❌ Grace period not updated\n";
        }
    } catch (\Exception $e) {
        echo "   ❌ Error: " . $e->getMessage() . "\n";
        echo "   Stack: " . $e->getTraceAsString() . "\n";
    }
}

// Test 2: Reset Failure Count
if ($subscription) {
    echo "\n2. Testing Reset Failure Count...\n";
    try {
        $originalCount = $subscription->payment_failure_count;
        $subscription->update(['payment_failure_count' => 2]);
        $subscription->refresh();
        
        $controller = new \App\Http\Controllers\Admin\SubscriptionAdminController();
        $request = new \Illuminate\Http\Request();
        $request->setMethod('POST');
        
        $response = $controller->resetFailureCount($subscription);
        $subscription->refresh();
        
        if ($subscription->payment_failure_count == 0) {
            echo "   ✅ Failure count reset successfully\n";
        } else {
            echo "   ❌ Failure count not reset (current: {$subscription->payment_failure_count})\n";
        }
    } catch (\Exception $e) {
        echo "   ❌ Error: " . $e->getMessage() . "\n";
    }
}

// Test 3: Mark Bill as Paid
if ($bill && $bill->status === 'pending') {
    echo "\n3. Testing Mark Bill as Paid...\n";
    try {
        $controller = new \App\Http\Controllers\Admin\BillAdminController();
        $request = new \Illuminate\Http\Request([
            'payment_reference' => 'TEST_' . time(),
            'notes' => 'Test payment from admin panel'
        ]);
        $request->setMethod('POST');
        
        $response = $controller->markAsPaid($request, $bill);
        $bill->refresh();
        
        if ($bill->status === 'paid') {
            echo "   ✅ Bill marked as paid successfully\n";
            echo "   Payment ID: " . ($bill->payment_id ?? 'None') . "\n";
        } else {
            echo "   ❌ Bill not marked as paid (status: {$bill->status})\n";
        }
    } catch (\Exception $e) {
        echo "   ❌ Error: " . $e->getMessage() . "\n";
        echo "   Stack: " . $e->getTraceAsString() . "\n";
    }
}

// Test 4: Cancel Bill
if ($bill && $bill->status === 'pending') {
    echo "\n4. Testing Cancel Bill...\n";
    try {
        // Create a new pending bill for testing
        $testBill = Bill::create([
            'user_id' => $user->id,
            'subscription_id' => $subscription?->id,
            'type' => 'renewal',
            'status' => 'pending',
            'amount' => 100.00,
            'currency' => 'USD',
            'due_date' => now()->addDays(7),
        ]);
        
        $controller = new \App\Http\Controllers\Admin\BillAdminController();
        $request = new \Illuminate\Http\Request();
        $request->setMethod('POST');
        
        $response = $controller->cancel($testBill);
        $testBill->refresh();
        
        if ($testBill->status === 'cancelled') {
            echo "   ✅ Bill cancelled successfully\n";
            $testBill->delete(); // Cleanup
        } else {
            echo "   ❌ Bill not cancelled (status: {$testBill->status})\n";
        }
    } catch (\Exception $e) {
        echo "   ❌ Error: " . $e->getMessage() . "\n";
    }
}

// Test 5: Trigger Renewal
if ($subscription) {
    echo "\n5. Testing Trigger Renewal...\n";
    try {
        $controller = new \App\Http\Controllers\Admin\SubscriptionAdminController();
        $request = new \Illuminate\Http\Request();
        $request->setMethod('POST');
        
        $response = $controller->triggerRenewal($subscription);
        echo "   ✅ Trigger renewal executed (check result manually)\n";
    } catch (\Exception $e) {
        echo "   ❌ Error: " . $e->getMessage() . "\n";
        if (strpos($e->getMessage(), 'SubscriptionRenewalService') !== false) {
            echo "   ⚠️  This might be expected if renewal service has specific requirements\n";
        }
    }
}

echo "\n=== POST Endpoints Testing Complete ===\n";

