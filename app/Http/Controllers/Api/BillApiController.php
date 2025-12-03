<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SubscriptionResource;
use App\Models\Subscription;
use App\Models\Plan;
use App\Models\Bill;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Services\Payment\PaymentManager;
use Illuminate\Http\Request;
use App\Traits\DetectsRegion;
use App\Traits\AddsCorsHeaders;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

class BillApiController extends Controller
{
    use DetectsRegion, AddsCorsHeaders;

    /**
     * Get current active subscription for authenticated user.
     */
    public function current(Request $request)
    {
        $user   = $request->user();
        $region = $this->getRegion($request);

        $subscription = Subscription::with(['plan.aiEngine'])
            ->where('user_id', $user->id)
            ->whereHas('plan', fn($q) => $q->where('region', $region))
            ->where('is_active', true)
            ->whereDate('end_date', '>=', now()) // Also check that subscription hasn't expired
            ->orderByDesc('end_date') // Get the most recent active subscription
            ->first();

        if (!$subscription) {
            // Return default plan structure instead of 404 to match SubscriptionApiController
            $defaultPlan = Plan::where('region', $region)
                ->where('is_default', true)
                ->with('aiEngine')
                ->first();

            if (!$defaultPlan) {
                $response = response()->json(['message' => 'No default plan is configured.'], 500);
                return $this->addCorsHeaders($response, $request);
            }

            $response = response()->json([
                'subscription' => null,
                'plan' => [
                    'id' => $defaultPlan->id,
                    'name' => $defaultPlan->name,
                    'max_tokens' => $defaultPlan->max_tokens,
                    'daily_message_limit' => $defaultPlan->daily_message_limit,
                    'ads_enabled' => $defaultPlan->ads_enabled,
                    'is_guest_mode' => true,
                    'engine' => [
                        'name' => optional($defaultPlan->aiEngine)->name,
                        'provider' => optional($defaultPlan->aiEngine)->provider,
                        'max_tokens' => optional($defaultPlan->aiEngine)->max_tokens,
                        'price_per_1k' => optional($defaultPlan->aiEngine)->price_per_1k,
                        'is_vision_support' => optional($defaultPlan->aiEngine)->is_vision_support,
                    ],
                ],
            ]);
            return $this->addCorsHeaders($response, $request);
        }

        $resource = new SubscriptionResource($subscription);
        $response = response()->json($resource->toArray($request));
        return $this->addCorsHeaders($response, $request);
    }

    /**
     * Get billing/subscription history for the authenticated user.
     */
    public function history(Request $request)
    {
        $user   = $request->user();
        $region = $this->getRegion($request);

        // Get pagination parameters
        $perPage = $request->integer('per_page', 10);
        $page = $request->integer('page', 1);

        // Validate per_page (max 100 to prevent performance issues)
        $perPage = min($perPage, 100);
        $perPage = max($perPage, 1);

        $subscriptions = Subscription::with('plan')
            ->where('user_id', $user->id)
            ->whereHas('plan', fn($q) => $q->where('region', $region))
            ->orderByDesc('start_date')
            ->paginate($perPage, ['*'], 'page', $page);

        // Return paginated response with history array and meta information
        return response()->json([
            'history' => SubscriptionResource::collection($subscriptions->items()),
            'meta' => [
                'current_page' => $subscriptions->currentPage(),
                'last_page' => $subscriptions->lastPage(),
                'per_page' => $subscriptions->perPage(),
                'total' => $subscriptions->total(),
                'from' => $subscriptions->firstItem(),
                'to' => $subscriptions->lastItem(),
            ],
        ]);
    }

    /**
     * Get remaining token stats for current user.
     */
    public function usage(Request $request)
    {
        $user   = $request->user();
        $region = $this->getRegion($request);

        $active = Subscription::with('plan')
            ->where('user_id', $user->id)
            ->whereHas('plan', fn($q) => $q->where('region', $region))
            ->where('is_active', true)
            ->whereDate('end_date', '>=', now()) // Ensure subscription hasn't expired
            ->orderByDesc('end_date') // Get the most recent active subscription
            ->first();

        if (!$active) {
            // Return default plan structure instead of 404
            $defaultPlan = Plan::where('region', $region)
                ->where('is_default', true)
                ->with('aiEngine')
                ->first();

            if (!$defaultPlan) {
                $response = response()->json(['message' => 'No default plan is configured.'], 500);
                return $this->addCorsHeaders($response, $request);
            }

            $response = response()->json([
                'plan'         => $defaultPlan->name ?? 'N/A',
                'tokens_used'  => 0,
                'tokens_limit' => $defaultPlan->max_tokens ?? 0,
                'remaining'    => $defaultPlan->max_tokens ?? 0,
            ]);
            return $this->addCorsHeaders($response, $request);
        }

        $response = response()->json([
            'plan'         => $active->plan->name ?? 'N/A',
            'tokens_used'  => $active->tokens_used ?? 0,
            'tokens_limit' => $active->plan->max_tokens ?? 0,
            'remaining'    => max(0, ($active->plan->max_tokens ?? 0) - ($active->tokens_used ?? 0)),
        ]);
        return $this->addCorsHeaders($response, $request);
    }

    /**
     * Toggle the auto-renew setting for the current user's active subscription.
     */
    public function toggleAutoRenew(Request $request)
    {
        $user   = $request->user();
        $region = $this->getRegion($request);

        $subscription = Subscription::with('plan')
            ->where('user_id', $user->id)
            ->whereHas('plan', fn($q) => $q->where('region', $region))
            ->where('is_active', true)
            ->first();

        if (!$subscription) {
            return response()->json(['message' => 'No active subscription.'], 404);
        }

        $subscription->auto_renew = !$subscription->auto_renew;
        $subscription->save();

        return response()->json([
            'message'    => 'Auto-renew setting updated.',
            'auto_renew' => $subscription->auto_renew,
        ]);
    }

    /**
     * List all invoices/bills for user
     */
    public function invoices(Request $request)
    {
        $user = $request->user();
        $region = $this->getRegion($request);

        // Get pagination parameters
        $perPage = $request->integer('per_page', 10);
        $page = $request->integer('page', 1);

        // Validate per_page (max 100 to prevent performance issues)
        $perPage = min($perPage, 100);
        $perPage = max($perPage, 1);

        // Get all bills for this user and region
        $bills = Bill::with(['subscription.plan', 'payment'])
            ->where('user_id', $user->id)
            ->whereHas('subscription.plan', fn($q) => $q->where('region', $region))
            ->get();

        // Get subscription IDs that already have bills (to avoid duplicates)
        $subscriptionIdsWithBills = $bills->pluck('subscription_id')->filter()->toArray();

        // Get all subscriptions for this user and region that don't have bills
        $subscriptions = Subscription::with('plan')
            ->where('user_id', $user->id)
            ->whereHas('plan', fn($q) => $q->where('region', $region))
            ->whereNotIn('id', $subscriptionIdsWithBills)
            ->get();

        // Combine bills and subscriptions into invoices
        $allInvoices = collect();

        // Add bills as invoices
        foreach ($bills as $bill) {
            $subscription = $bill->subscription;
            $plan = $subscription?->plan;
            
            $allInvoices->push([
                'id' => $bill->id,
                'bill_id' => $bill->id,
                'subscription_id' => $bill->subscription_id,
                'invoice_number' => 'INV-' . str_pad($bill->id, 8, '0', STR_PAD_LEFT),
                'plan_name' => $plan?->name ?? 'N/A',
                'amount' => $bill->amount,
                'currency' => $bill->currency,
                'status' => $bill->status,
                'payment_status' => $bill->payment?->status ?? 'unknown',
                'due_date' => $bill->due_date?->toIso8601String(),
                'paid_at' => $bill->paid_at?->toIso8601String(),
                'period_start' => $subscription?->start_date?->toIso8601String(),
                'period_end' => $subscription?->end_date?->toIso8601String(),
                'created_at' => $bill->created_at->toIso8601String(),
                'sort_date' => $bill->created_at->timestamp,
            ]);
        }

        // Add subscriptions without bills as invoices
        foreach ($subscriptions as $subscription) {
            // Find associated payment if exists
            $payment = \App\Models\Payment::where('user_id', $user->id)
                ->where(function($q) use ($subscription) {
                    $q->whereJsonContains('metadata->subscription_id', $subscription->id)
                      ->orWhere('reference', 'like', "%{$subscription->id}%");
                })
                ->orderByDesc('created_at')
                ->first();

            $allInvoices->push([
                'id' => $subscription->id,
                'bill_id' => null,
                'subscription_id' => $subscription->id,
                'invoice_number' => 'INV-' . str_pad($subscription->id, 8, '0', STR_PAD_LEFT),
                'plan_name' => $subscription->plan->name,
                'amount' => $subscription->plan->monthly_price,
                'currency' => $subscription->plan->currency ?? 'USD',
                'status' => $subscription->is_active ? 'paid' : 'cancelled',
                'payment_status' => $payment?->status ?? 'unknown',
                'period_start' => $subscription->start_date->toIso8601String(),
                'period_end' => $subscription->end_date->toIso8601String(),
                'created_at' => $subscription->created_at->toIso8601String(),
                'sort_date' => $subscription->created_at->timestamp,
            ]);
        }

        // Sort by created_at descending (most recent first)
        $allInvoices = $allInvoices->sortByDesc('sort_date')->values();

        // Manual pagination
        $total = $allInvoices->count();
        $lastPage = (int) ceil($total / $perPage);
        $offset = ($page - 1) * $perPage;
        $paginatedInvoices = $allInvoices->slice($offset, $perPage)->values();

        // Remove sort_date from response
        $invoices = $paginatedInvoices->map(function ($invoice) {
            unset($invoice['sort_date']);
            return $invoice;
        });

        return response()->json([
            'invoices' => $invoices,
            'meta' => [
                'current_page' => $page,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $total,
                'from' => $total > 0 ? $offset + 1 : null,
                'to' => $total > 0 ? min($offset + $perPage, $total) : null,
            ],
        ]);
    }

    /**
     * Get single invoice details
     */
    public function invoice(Request $request, $id)
    {
        $user = $request->user();
        $region = $this->getRegion($request);

        // Try to find bill first
        $bill = Bill::with(['subscription.plan', 'payment'])
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->whereHas('subscription.plan', fn($q) => $q->where('region', $region))
            ->first();

        // Fall back to subscription for backward compatibility
        if (!$bill) {
            $subscription = Subscription::with('plan')
                ->where('id', $id)
                ->where('user_id', $user->id)
                ->whereHas('plan', fn($q) => $q->where('region', $region))
                ->firstOrFail();

            // Find associated payment if exists
            $payment = \App\Models\Payment::where('user_id', $user->id)
                ->whereJsonContains('metadata->subscription_id', $subscription->id)
                ->orWhere('reference', 'like', "%{$subscription->id}%")
                ->first();

            return response()->json([
                'id' => $subscription->id,
                'bill_id' => null,
                'invoice_number' => 'INV-' . str_pad($subscription->id, 8, '0', STR_PAD_LEFT),
                'plan_name' => $subscription->plan->name,
                'amount' => $subscription->plan->monthly_price,
                'currency' => $subscription->plan->currency ?? 'USD',
                'status' => $subscription->is_active ? 'paid' : 'cancelled',
                'payment_status' => $payment?->status ?? 'unknown',
                'period_start' => $subscription->start_date->toIso8601String(),
                'period_end' => $subscription->end_date->toIso8601String(),
                'line_items' => [
                    [
                        'description' => "Subscription to {$subscription->plan->name}",
                        'quantity' => 1,
                        'unit_price' => $subscription->plan->monthly_price,
                        'total' => $subscription->plan->monthly_price,
                    ],
                ],
                'created_at' => $subscription->created_at->toIso8601String(),
            ]);
        }

        $subscription = $bill->subscription;
        $plan = $subscription?->plan;

        return response()->json([
            'id' => $bill->id,
            'bill_id' => $bill->id,
            'subscription_id' => $bill->subscription_id,
            'invoice_number' => 'INV-' . str_pad($bill->id, 8, '0', STR_PAD_LEFT),
            'plan_name' => $plan?->name ?? 'N/A',
            'amount' => $bill->amount,
            'currency' => $bill->currency,
            'status' => $bill->status,
            'payment_status' => $bill->payment?->status ?? 'unknown',
            'due_date' => $bill->due_date?->toIso8601String(),
            'paid_at' => $bill->paid_at?->toIso8601String(),
            'period_start' => $subscription?->start_date?->toIso8601String(),
            'period_end' => $subscription?->end_date?->toIso8601String(),
            'line_items' => [
                [
                    'description' => $bill->description ?? "Subscription to {$plan?->name}",
                    'quantity' => 1,
                    'unit_price' => $bill->amount,
                    'total' => $bill->amount,
                ],
            ],
            'created_at' => $bill->created_at->toIso8601String(),
        ]);
    }

    /**
     * Download invoice as PDF
     */
    public function downloadInvoice(Request $request, $id)
    {
        $user = $request->user();
        $region = $this->getRegion($request);

        // Try to find bill first
        $bill = Bill::with(['subscription.plan', 'payment'])
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->whereHas('subscription.plan', fn($q) => $q->where('region', $region))
            ->first();

        // Fall back to subscription for backward compatibility
        if (!$bill) {
            $subscription = Subscription::with('plan')
                ->where('id', $id)
                ->where('user_id', $user->id)
                ->whereHas('plan', fn($q) => $q->where('region', $region))
                ->first();

            if (!$subscription) {
                $response = response()->json([
                    'message' => 'Invoice not found.',
                    'error_code' => 'INVOICE_NOT_FOUND',
                ], 404);
                return $this->addCorsHeaders($response, $request);
            }

            // Find associated payment if exists
            $payment = \App\Models\Payment::where('user_id', $user->id)
                ->whereJsonContains('metadata->subscription_id', $subscription->id)
                ->orWhere('reference', 'like', "%{$subscription->id}%")
                ->first();

            $invoice = [
                'id' => $subscription->id,
                'bill_id' => null,
                'invoice_number' => 'INV-' . str_pad($subscription->id, 8, '0', STR_PAD_LEFT),
                'plan_name' => $subscription->plan->name,
                'amount' => $subscription->plan->monthly_price,
                'currency' => $subscription->plan->currency ?? 'USD',
                'status' => $subscription->is_active ? 'paid' : 'cancelled',
                'payment_status' => $payment?->status ?? 'unknown',
                'period_start' => $subscription->start_date->toIso8601String(),
                'period_end' => $subscription->end_date->toIso8601String(),
                'line_items' => [
                    [
                        'description' => "Subscription to {$subscription->plan->name}",
                        'quantity' => 1,
                        'unit_price' => $subscription->plan->monthly_price,
                        'total' => $subscription->plan->monthly_price,
                    ],
                ],
                'created_at' => $subscription->created_at->toIso8601String(),
            ];
        } else {
            $subscription = $bill->subscription;
            $plan = $subscription?->plan;

            $invoice = [
                'id' => $bill->id,
                'bill_id' => $bill->id,
                'subscription_id' => $bill->subscription_id,
                'invoice_number' => 'INV-' . str_pad($bill->id, 8, '0', STR_PAD_LEFT),
                'plan_name' => $plan?->name ?? 'N/A',
                'amount' => $bill->amount,
                'currency' => $bill->currency,
                'status' => $bill->status,
                'payment_status' => $bill->payment?->status ?? 'unknown',
                'due_date' => $bill->due_date?->toIso8601String(),
                'paid_at' => $bill->paid_at?->toIso8601String(),
                'period_start' => $subscription?->start_date?->toIso8601String(),
                'period_end' => $subscription?->end_date?->toIso8601String(),
                'line_items' => [
                    [
                        'description' => $bill->description ?? "Subscription to {$plan?->name}",
                        'quantity' => 1,
                        'unit_price' => $bill->amount,
                        'total' => $bill->amount,
                    ],
                ],
                'created_at' => $bill->created_at->toIso8601String(),
            ];
        }

        try {
            // Generate PDF
            $pdf = Pdf::loadView('invoices.invoice-pdf', [
                'invoice' => $invoice,
                'user' => $user,
            ])->setPaper('a4', 'portrait');

            $filename = 'invoice-' . $invoice['invoice_number'] . '.pdf';

            return $pdf->download($filename);
        } catch (\Exception $e) {
            Log::error('Failed to generate invoice PDF', [
                'invoice_id' => $id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $response = response()->json([
                'message' => 'Failed to generate invoice PDF. Please try again later.',
                'error_code' => 'PDF_GENERATION_FAILED',
            ], 500);
            return $this->addCorsHeaders($response, $request);
        }
    }

    /**
     * Get upcoming charges
     */
    public function upcomingCharges(Request $request)
    {
        $user = $request->user();
        $region = $this->getRegion($request);

        $subscription = Subscription::with('plan')
            ->where('user_id', $user->id)
            ->whereHas('plan', fn($q) => $q->where('region', $region))
            ->where('is_active', true)
            ->where('auto_renew', true)
            ->first();

        if (!$subscription) {
            // Return empty/null values instead of 404 to prevent frontend errors
            return response()->json([
                'next_charge_date' => null,
                'amount' => null,
                'currency' => null,
                'plan_name' => null,
                'payment_method' => null,
                'days_until_charge' => null,
                'has_upcoming_charge' => false,
            ]);
        }

        $plan = $subscription->plan;
        $nextRenewalDate = $subscription->end_date;
        $paymentMethod = $subscription->metadata['payment_method'] ?? null;

        return response()->json([
            'next_charge_date' => $nextRenewalDate->toIso8601String(),
            'amount' => $plan->monthly_price,
            'currency' => $plan->currency ?? 'USD',
            'plan_name' => $plan->name,
            'payment_method' => $paymentMethod,
            'days_until_charge' => now()->diffInDays($nextRenewalDate, false),
            'has_upcoming_charge' => true,
        ]);
    }

    /**
     * List user's saved payment methods
     */
    public function paymentMethods(Request $request)
    {
        $user = $request->user();
        $region = $this->getRegion($request);
        
        // Get available payment methods for this region
        $availableMethods = PaymentMethod::forRegion($region)->get()->map(function ($method) {
            return [
                'key' => $method->key,
                'name' => $method->name,
                'description' => $method->description,
            ];
        });
        
        // Get user's saved payment methods from subscription metadata
        $subscriptions = Subscription::where('user_id', $user->id)
            ->whereNotNull('metadata')
            ->get();

        $savedPaymentMethods = $subscriptions->map(function ($subscription) {
            $metadata = $subscription->metadata ?? [];
            $method = $metadata['payment_method'] ?? null;
            
            if ($method) {
                return [
                    'id' => $subscription->id . '_' . $method,
                    'type' => $method,
                    'is_default' => $subscription->is_active,
                    'last_used' => $subscription->updated_at->toIso8601String(),
                ];
            }
            return null;
        })->filter()->unique('type')->values();

        return response()->json([
            'available_methods' => $availableMethods,
            'saved_methods' => $savedPaymentMethods,
        ]);
    }

    /**
     * Add/save payment method
     */
    public function addPaymentMethod(Request $request)
    {
        $user = $request->user();
        $region = $this->getRegion($request);
        
        // Get available payment methods for this region
        $availableMethods = PaymentMethod::forRegion($region)->pluck('key')->toArray();
        
        $request->validate([
            'payment_method' => ['required', 'string', function ($attribute, $value, $fail) use ($availableMethods, $region) {
                $paymentMethod = PaymentMethod::where('key', $value)->first();
                if (!$paymentMethod) {
                    $fail("Payment method '{$value}' not found.");
                } elseif (!$paymentMethod->isEnabledForRegion($region)) {
                    $fail("Payment method '{$value}' is not available for your region. Available methods: " . implode(', ', $availableMethods ?: ['none']));
                }
            }],
            'payment_details' => 'sometimes|array',
        ]);

        $subscription = Subscription::where('user_id', $user->id)
            ->whereHas('plan', fn($q) => $q->where('region', $region))
            ->where('is_active', true)
            ->first();

        if ($subscription) {
            $metadata = $subscription->metadata ?? [];
            $metadata['payment_method'] = $request->payment_method;
            $metadata['payment_details'] = $request->payment_details ?? [];
            $subscription->metadata = $metadata;
            $subscription->save();
        }

        return response()->json([
            'message' => 'Payment method saved successfully',
            'payment_method' => $request->payment_method,
        ]);
    }

    /**
     * Remove payment method
     */
    public function removePaymentMethod(Request $request, $id)
    {
        // For now, just clear payment method from subscription metadata
        // In the future, this could remove from a separate payment_methods table
        return response()->json(['message' => 'Payment method removed']);
    }

    /**
     * Set default payment method
     */
    public function setDefaultPaymentMethod(Request $request, $id)
    {
        $user = $request->user();
        $region = $this->getRegion($request);

        $subscription = Subscription::where('user_id', $user->id)
            ->whereHas('plan', fn($q) => $q->where('region', $region))
            ->where('is_active', true)
            ->first();

        if ($subscription) {
            // Extract payment method from id (format: subscription_id_method)
            $parts = explode('_', $id);
            $method = end($parts);
            
            $metadata = $subscription->metadata ?? [];
            $metadata['payment_method'] = $method;
            $subscription->metadata = $metadata;
            $subscription->save();
        }

        return response()->json(['message' => 'Default payment method updated']);
    }

    /**
     * Get/update billing address
     */
    public function billingAddress(Request $request)
    {
        $user = $request->user();

        if ($request->isMethod('PUT')) {
            $request->validate([
                'address_line1' => 'sometimes|string',
                'address_line2' => 'sometimes|string',
                'city' => 'sometimes|string',
                'state' => 'sometimes|string',
                'postal_code' => 'sometimes|string',
                'country' => 'sometimes|string',
            ]);

            // Store in user metadata or separate billing_addresses table
            $metadata = $user->metadata ?? [];
            $metadata['billing_address'] = $request->only([
                'address_line1', 'address_line2', 'city', 'state', 'postal_code', 'country'
            ]);
            $user->metadata = $metadata;
            $user->save();

            return response()->json([
                'message' => 'Billing address updated',
                'billing_address' => $metadata['billing_address'],
            ]);
        }

        // GET - return billing address
        $billingAddress = $user->metadata['billing_address'] ?? null;
        return response()->json(['billing_address' => $billingAddress]);
    }

    /**
     * Get tax information
     */
    public function taxInformation(Request $request)
    {
        $user = $request->user();
        $country = $user->country;

        return response()->json([
            'country' => $country?->name ?? 'Unknown',
            'country_code' => $country?->code ?? null,
            'tax_rate' => 0, // TODO: Calculate based on country
            'tax_id' => $user->metadata['tax_id'] ?? null,
        ]);
    }

    /**
     * List all bills for the authenticated user
     */
    public function listBills(Request $request)
    {
        $user = $request->user();
        $region = $this->getRegion($request);
        $status = $request->query('status'); // optional filter: pending, paid, overdue

        $query = Bill::where('user_id', $user->id)
            ->with(['subscription.plan', 'payment'])
            ->whereHas('subscription.plan', fn($q) => $q->where('region', $region));

        if ($status) {
            $query->where('status', $status);
        }

        $bills = $query->orderByDesc('created_at')->get();

        $response = response()->json([
            'bills' => $bills->map(function ($bill) {
                return [
                    'id' => $bill->id,
                    'type' => $bill->type,
                    'status' => $bill->status,
                    'amount' => $bill->amount,
                    'currency' => $bill->currency,
                    'due_date' => $bill->due_date->toIso8601String(),
                    'paid_at' => $bill->paid_at?->toIso8601String(),
                    'description' => $bill->description,
                    'plan_name' => $bill->subscription?->plan?->name,
                    'is_overdue' => $bill->isOverdue(),
                    'can_pay' => $bill->canBePaid(),
                    'created_at' => $bill->created_at->toIso8601String(),
                ];
            }),
        ]);

        return $this->addCorsHeaders($response, $request);
    }

    /**
     * Get pending bills (bills that need payment)
     */
    public function pendingBills(Request $request)
    {
        $user = $request->user();
        $region = $this->getRegion($request);

        $bills = Bill::where('user_id', $user->id)
            ->where('status', 'pending')
            ->with(['subscription.plan', 'payment'])
            ->whereHas('subscription.plan', fn($q) => $q->where('region', $region))
            ->orderBy('due_date', 'asc')
            ->get();

        // Mark overdue bills
        foreach ($bills as $bill) {
            if ($bill->isOverdue()) {
                $bill->markAsOverdue();
            }
        }

        $response = response()->json([
            'pending_bills' => $bills->map(function ($bill) {
                return [
                    'id' => $bill->id,
                    'type' => $bill->type,
                    'status' => $bill->status,
                    'amount' => $bill->amount,
                    'currency' => $bill->currency,
                    'due_date' => $bill->due_date->toIso8601String(),
                    'description' => $bill->description,
                    'plan_name' => $bill->subscription?->plan?->name,
                    'is_overdue' => $bill->isOverdue(),
                    'can_pay' => $bill->canBePaid(),
                    'days_until_due' => now()->diffInDays($bill->due_date, false),
                    'created_at' => $bill->created_at->toIso8601String(),
                ];
            }),
        ]);

        return $this->addCorsHeaders($response, $request);
    }

    /**
     * Get single bill details
     */
    public function getBill(Request $request, $id)
    {
        $user = $request->user();
        $region = $this->getRegion($request);

        $bill = Bill::where('id', $id)
            ->where('user_id', $user->id)
            ->with(['subscription.plan', 'payment'])
            ->whereHas('subscription.plan', fn($q) => $q->where('region', $region))
            ->firstOrFail();

        $response = response()->json([
            'bill' => [
                'id' => $bill->id,
                'type' => $bill->type,
                'status' => $bill->status,
                'amount' => $bill->amount,
                'currency' => $bill->currency,
                'due_date' => $bill->due_date->toIso8601String(),
                'paid_at' => $bill->paid_at?->toIso8601String(),
                'description' => $bill->description,
                'plan_name' => $bill->subscription?->plan?->name,
                'subscription_id' => $bill->subscription_id,
                'payment_id' => $bill->payment_id,
                'is_overdue' => $bill->isOverdue(),
                'can_pay' => $bill->canBePaid(),
                'metadata' => $bill->metadata,
                'created_at' => $bill->created_at->toIso8601String(),
                'updated_at' => $bill->updated_at->toIso8601String(),
            ],
        ]);

        return $this->addCorsHeaders($response, $request);
    }

    /**
     * Pay a bill (create payment and return checkout URL)
     */
    public function payBill(Request $request, $id)
    {
        $user = $request->user();
        $region = $this->getRegion($request);
        $paymentMethod = $request->input('payment_method'); // optional override

        $bill = Bill::where('id', $id)
            ->where('user_id', $user->id)
            ->with(['subscription.plan'])
            ->whereHas('subscription.plan', fn($q) => $q->where('region', $region))
            ->firstOrFail();

        if (!$bill->canBePaid()) {
            $response = response()->json([
                'message' => $bill->isOverdue() 
                    ? 'This bill is overdue. Please contact support.' 
                    : 'This bill cannot be paid.',
            ], 422);
            return $this->addCorsHeaders($response, $request);
        }

        $subscription = $bill->subscription;
        $plan = $subscription->plan;

        // Determine payment method
        $method = $paymentMethod 
            ?? $bill->metadata['payment_method'] 
            ?? $subscription->metadata['payment_method'] 
            ?? null;

        // Auto-select payment method if not provided
        if (!$method) {
            $enabledMethods = PaymentMethod::forRegion($region)->get();
            if ($enabledMethods->isEmpty()) {
                $response = response()->json([
                    'message' => 'No payment methods are available for your region. Please contact support.',
                    'error_code' => 'NO_PAYMENT_METHODS_AVAILABLE',
                ], 422);
                return $this->addCorsHeaders($response, $request);
            }
            
            // Prefer stripe for intl, chapa for local, otherwise use first available
            if ($region === 'intl') {
                $preferredMethod = $enabledMethods->firstWhere('key', 'stripe') ?? $enabledMethods->first();
            } else {
                $preferredMethod = $enabledMethods->firstWhere('key', 'chapa') ?? $enabledMethods->first();
            }
            $method = $preferredMethod->key;
        }

        // Validate payment method is enabled for this region
        $paymentMethodModel = PaymentMethod::where('key', $method)->first();
        
        if (!$paymentMethodModel) {
            $response = response()->json([
                'message' => 'Payment method not found. Please use a valid payment method.',
                'error_code' => 'PAYMENT_METHOD_NOT_FOUND',
            ], 422);
            return $this->addCorsHeaders($response, $request);
        }
        
        if (!$paymentMethodModel->isEnabledForRegion($region)) {
            $availableMethods = PaymentMethod::forRegion($region)->pluck('key')->toArray();
            $response = response()->json([
                'message' => "Payment method '{$method}' is not available for your region. Available methods: " . implode(', ', $availableMethods ?: ['none']),
                'error_code' => 'PAYMENT_METHOD_DISABLED',
                'available_methods' => $availableMethods,
            ], 422);
            return $this->addCorsHeaders($response, $request);
        }

        try {
            $paymentManager = app(PaymentManager::class);

            // Create payment
            $payment = $paymentManager->initiatePayment($user, [
                'amount' => $bill->amount,
                'currency' => $bill->currency,
                'provider' => $method,
                'metadata' => [
                    'plan_id' => $plan->id,
                    'subscription_id' => $subscription->id,
                    'bill_id' => $bill->id,
                    'type' => 'bill_payment',
                    'description' => $bill->description ?? "Payment for {$plan->name}",
                ],
                'description' => $bill->description ?? "Payment for {$plan->name}",
            ]);

            // Link payment to bill (will be marked as paid when webhook succeeds)
            $bill->update([
                'payment_id' => $payment->id,
                'metadata' => array_merge($bill->metadata ?? [], [
                    'payment_reference' => $payment->reference,
                    'payment_method' => $method,
                ]),
            ]);

            Log::info('Bill payment initiated', [
                'bill_id' => $bill->id,
                'payment_id' => $payment->id,
                'payment_reference' => $payment->reference,
                'method' => $method,
            ]);

            $response = response()->json([
                'message' => 'Payment initiated successfully',
                'payment_id' => $payment->id,
                'payment_reference' => $payment->reference,
                'checkout_url' => $payment->gateway_response ? json_decode($payment->gateway_response, true)['checkout_url'] ?? null : null,
                'bill_id' => $bill->id,
            ]);

            return $this->addCorsHeaders($response, $request);
        } catch (\Exception $e) {
            Log::error('Failed to initiate bill payment', [
                'bill_id' => $bill->id,
                'error' => $e->getMessage(),
            ]);

            $response = response()->json([
                'message' => 'Failed to initiate payment: ' . $e->getMessage(),
            ], 500);

            return $this->addCorsHeaders($response, $request);
        }
    }
}