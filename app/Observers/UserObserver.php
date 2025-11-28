<?php

namespace App\Observers;

use App\Models\User;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Support\Facades\Log;

class UserObserver
{
    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void
    {
        // Only assign free plan to regular users (not admins/staff)
        if ($user->role !== 'admin' && $user->role !== 'staff') {
            $this->assignFreePlan($user);
        }
    }

    /**
     * Assign free plan to newly created user based on their region.
     */
    protected function assignFreePlan(User $user): void
    {
        try {
            $region = $user->region ?? 'local';
            
            // Find the free plan for the user's region
            $freePlan = Plan::where('region', $region)
                ->where('monthly_price', 0)
                ->where('is_active', true)
                ->where('is_default', true)
                ->first();

            if (!$freePlan) {
                Log::warning('⚠️ No free plan found for region', [
                    'user_id' => $user->id,
                    'region' => $region,
                ]);
                return;
            }

            // Check if user already has a subscription (shouldn't happen, but safety check)
            $existingSubscription = Subscription::where('user_id', $user->id)
                ->where('is_active', true)
                ->first();

            if ($existingSubscription) {
                Log::info('User already has active subscription, skipping free plan assignment', [
                    'user_id' => $user->id,
                    'subscription_id' => $existingSubscription->id,
                ]);
                return;
            }

            // Create free subscription
            $subscription = Subscription::create([
                'user_id' => $user->id,
                'plan_id' => $freePlan->id,
                'start_date' => now(),
                'end_date' => now()->addDays(30),
                'tokens_used' => 0,
                'is_active' => true,
                'auto_renew' => false,
                'metadata' => [
                    'source' => 'auto_assigned_on_registration',
                    'assigned_at' => now()->toISOString(),
                ],
            ]);

            Log::info('✅ Free plan assigned to new user', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'region' => $region,
                'plan_id' => $freePlan->id,
                'plan_name' => $freePlan->name,
                'subscription_id' => $subscription->id,
            ]);
        } catch (\Exception $e) {
            Log::error('❌ Failed to assign free plan to new user', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
