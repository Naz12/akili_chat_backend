<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Plan;
use App\Services\Payment\PaymentManager;
use App\Traits\AddsCorsHeaders;
use App\Traits\DetectsRegion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PaymentApiController extends Controller
{
    use AddsCorsHeaders, DetectsRegion;

    protected PaymentManager $paymentManager;

    public function __construct(PaymentManager $paymentManager)
    {
        $this->paymentManager = $paymentManager;
    }

    /**
     * Create a new payment.
     */
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'plan_id' => 'nullable|exists:plans,id',
            'amount' => 'required_without:plan_id|numeric|min:0.01',
            'currency' => 'required|string|in:USD,ETB',
            'provider' => 'required|string|in:stripe,chapa',
            'description' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            $response = response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
            return $this->addCorsHeaders($response, $request);
        }

        try {
            $user = $request->user();
            $region = $this->getRegion($request);

            // Determine amount and currency
            $amount = $request->amount;
            $currency = $request->currency;

            if ($request->filled('plan_id')) {
                $plan = Plan::where('id', $request->plan_id)
                    ->where('region', $region)
                    ->firstOrFail();

                if ($plan->monthly_price <= 0) {
                    $response = response()->json([
                        'message' => 'Free plan does not require payment.',
                    ], 400);
                    return $this->addCorsHeaders($response, $request);
                }

                $amount = $plan->monthly_price;
                $currency = $plan->currency ?? $currency;
            }

            // Prepare metadata - ensure plan_id is integer if provided
            $metadata = [
                'plan_id' => $request->filled('plan_id') ? (int) $request->plan_id : null,
                'description' => $request->description ?? ($request->filled('plan_id') ? "Subscription to {$plan->name}" : 'Payment'),
            ];

            // Create payment with optional frontend redirect URLs
            $payment = $this->paymentManager->initiatePayment($user, [
                'amount' => $amount,
                'currency' => $currency,
                'provider' => $request->provider,
                'metadata' => $metadata,
                'description' => $metadata['description'],
                'success_url' => $request->success_url ?? config('app.frontend_url', config('app.url')) . '/payment/success',
                'cancel_url' => $request->cancel_url ?? config('app.frontend_url', config('app.url')) . '/payment/cancel',
                'return_url' => $request->return_url ?? config('app.frontend_url', config('app.url')) . '/payment/success',
            ]);

            // Get checkout URL from gateway response
            $gatewayResponse = json_decode($payment->gateway_response, true);
            $checkoutUrl = $gatewayResponse['checkout_url'] ?? null;

            $response = response()->json([
                'message' => 'Payment created successfully',
                'payment' => [
                    'id' => $payment->id,
                    'reference' => $payment->reference,
                    'amount' => $payment->amount,
                    'currency' => $payment->currency,
                    'provider' => $payment->provider,
                    'status' => $payment->status,
                    'checkout_url' => $checkoutUrl,
                    'created_at' => $payment->created_at->toISOString(),
                ],
            ], 201);

            return $this->addCorsHeaders($response, $request);
        } catch (\Exception $e) {
            Log::error('Payment creation failed', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
            ]);

            $response = response()->json([
                'message' => 'Failed to create payment: ' . $e->getMessage(),
            ], 500);

            return $this->addCorsHeaders($response, $request);
        }
    }

    /**
     * Get payment status.
     */
    public function status(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'reference' => 'required|string',
        ]);

        if ($validator->fails()) {
            $response = response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
            return $this->addCorsHeaders($response, $request);
        }

        try {
            $payment = Payment::where('reference', $request->reference)->firstOrFail();

            // Ensure user can only access their own payments
            if ($payment->user_id !== $request->user()->id) {
                $response = response()->json([
                    'message' => 'Unauthorized access to payment',
                ], 403);
                return $this->addCorsHeaders($response, $request);
            }

            $paymentStatus = $this->paymentManager->getPaymentStatus($request->reference);

            $response = response()->json([
                'payment' => $paymentStatus,
            ]);

            return $this->addCorsHeaders($response, $request);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            $response = response()->json([
                'message' => 'Payment not found',
            ], 404);
            return $this->addCorsHeaders($response, $request);
        } catch (\Exception $e) {
            Log::error('Payment status check failed', [
                'reference' => $request->reference,
                'error' => $e->getMessage(),
            ]);

            $response = response()->json([
                'message' => 'Failed to get payment status: ' . $e->getMessage(),
            ], 500);

            return $this->addCorsHeaders($response, $request);
        }
    }

    /**
     * Verify payment.
     */
    public function verify(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'reference' => 'required|string',
        ]);

        if ($validator->fails()) {
            $response = response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
            return $this->addCorsHeaders($response, $request);
        }

        try {
            $payment = Payment::where('reference', $request->reference)->firstOrFail();

            // Ensure user can only verify their own payments
            if ($payment->user_id !== $request->user()->id) {
                $response = response()->json([
                    'message' => 'Unauthorized access to payment',
                ], 403);
                return $this->addCorsHeaders($response, $request);
            }

            $payment = $this->paymentManager->verifyPayment($request->reference);

            $response = response()->json([
                'verified' => $payment->isSuccessful(),
                'payment' => [
                    'id' => $payment->id,
                    'reference' => $payment->reference,
                    'status' => $payment->status,
                    'transaction_id' => $payment->transaction_id,
                ],
            ]);

            return $this->addCorsHeaders($response, $request);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            $response = response()->json([
                'message' => 'Payment not found',
            ], 404);
            return $this->addCorsHeaders($response, $request);
        } catch (\Exception $e) {
            Log::error('Payment verification failed', [
                'reference' => $request->reference,
                'error' => $e->getMessage(),
            ]);

            $response = response()->json([
                'message' => 'Verification failed: ' . $e->getMessage(),
            ], 500);

            return $this->addCorsHeaders($response, $request);
        }
    }

    /**
     * Get user's payment history.
     */
    public function history(Request $request)
    {
        try {
            $user = $request->user();

            $filters = [];
            if ($request->filled('status')) {
                $filters['status'] = $request->status;
            }
            if ($request->filled('provider')) {
                $filters['provider'] = $request->provider;
            }
            if ($request->filled('date_from')) {
                $filters['date_from'] = $request->date_from;
            }
            if ($request->filled('date_to')) {
                $filters['date_to'] = $request->date_to;
            }

            $payments = $this->paymentManager->getUserPayments($user, $filters);

            $response = response()->json([
                'data' => $payments->map(function ($payment) {
                    return [
                        'id' => $payment->id,
                        'reference' => $payment->reference,
                        'amount' => $payment->amount,
                        'currency' => $payment->currency,
                        'provider' => $payment->provider,
                        'status' => $payment->status,
                        'transaction_id' => $payment->transaction_id,
                        'created_at' => $payment->created_at->toISOString(),
                    ];
                }),
            ]);

            return $this->addCorsHeaders($response, $request);
        } catch (\Exception $e) {
            Log::error('Payment history retrieval failed', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
            ]);

            $response = response()->json([
                'message' => 'Failed to retrieve payment history: ' . $e->getMessage(),
            ], 500);

            return $this->addCorsHeaders($response, $request);
        }
    }
}
