<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WebPushSubscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class WebPushApiController extends Controller
{
    /**
     * Subscribe to Web Push notifications
     */
    public function subscribe(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'endpoint' => 'required|string|max:500',
            'keys' => 'required|array',
            'keys.p256dh' => 'required|string|max:255',
            'keys.auth' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();

        try {
            // Check if subscription already exists
            $subscription = WebPushSubscription::where('user_id', $user->id)
                ->where('endpoint', $request->endpoint)
                ->first();

            if ($subscription) {
                // Update existing subscription
                $subscription->update([
                    'p256dh_key' => $request->keys['p256dh'],
                    'auth_key' => $request->keys['auth'],
                    'active' => true,
                    'user_agent' => $request->userAgent(),
                    'updated_at' => now(),
                ]);
            } else {
                // Create new subscription
                $subscription = WebPushSubscription::create([
                    'user_id' => $user->id,
                    'endpoint' => $request->endpoint,
                    'p256dh_key' => $request->keys['p256dh'],
                    'auth_key' => $request->keys['auth'],
                    'active' => true,
                    'user_agent' => $request->userAgent(),
                ]);
            }

            return response()->json([
                'status' => 'subscribed',
                'subscription_id' => $subscription->id,
            ], 201);
        } catch (\Exception $e) {
            Log::error('Web Push subscription failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Subscription failed',
                'message' => 'Unable to save subscription',
            ], 500);
        }
    }

    /**
     * Unsubscribe from Web Push notifications
     */
    public function unsubscribe(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'endpoint' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();

        try {
            $subscription = WebPushSubscription::where('user_id', $user->id)
                ->where('endpoint', $request->endpoint)
                ->first();

            if ($subscription) {
                $subscription->update(['active' => false]);
                return response()->json(['status' => 'unsubscribed']);
            }

            return response()->json(['status' => 'not_found'], 404);
        } catch (\Exception $e) {
            Log::error('Web Push unsubscribe failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Unsubscribe failed',
            ], 500);
        }
    }

    /**
     * Get user's Web Push subscriptions
     */
    public function subscriptions(Request $request)
    {
        $user = $request->user();

        $subscriptions = WebPushSubscription::where('user_id', $user->id)
            ->where('active', true)
            ->select('id', 'endpoint', 'created_at')
            ->get();

        return response()->json([
            'subscriptions' => $subscriptions,
            'count' => $subscriptions->count(),
        ]);
    }

    /**
     * Get VAPID public key for Web Push subscription
     */
    public function vapidPublicKey(Request $request)
    {
        $publicKey = config('services.webpush.vapid_public_key');
        
        if (!$publicKey) {
            return response()->json([
                'error' => 'VAPID public key not configured',
                'message' => 'Web Push is not available. Please contact support.',
            ], 503);
        }

        return response()->json([
            'vapid_public_key' => $publicKey,
        ]);
    }
}

