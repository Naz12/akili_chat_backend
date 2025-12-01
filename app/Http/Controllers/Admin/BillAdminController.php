<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use App\Models\Subscription;
use App\Models\Bill;
use App\Models\Payment;
use Illuminate\Http\Request;
use App\Models\TokenUsageLog;
use App\Models\TokenAdjustment;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;

class BillAdminController extends Controller
{
    /**
     * Display a paginated list of all subscriptions and bills.
     */
    public function index(Request $request)
    {
        $tab = $request->get('tab', 'subscriptions'); // subscriptions or bills

        if ($tab === 'bills') {
            $query = Bill::with(['user', 'subscription.plan', 'payment'])->latest();

            // Filters
            if ($request->has('status') && $request->status) {
                $query->where('status', $request->status);
            }

            if ($request->has('type') && $request->type) {
                $query->where('type', $request->type);
            }

            if ($request->has('user_id') && $request->user_id) {
                $query->where('user_id', $request->user_id);
            }

            $bills = $query->paginate(20);

            // Statistics
            $billStats = [
                'total' => Bill::count(),
                'pending' => Bill::where('status', 'pending')->count(),
                'paid' => Bill::where('status', 'paid')->count(),
                'overdue' => Bill::where('status', 'pending')->where('due_date', '<', now())->count(),
                'renewal' => Bill::where('type', 'renewal')->count(),
                'renewal_failed' => Bill::where('type', 'renewal_failed')->count(),
                'total_amount_pending' => Bill::where('status', 'pending')->sum('amount'),
                'total_amount_paid' => Bill::where('status', 'paid')->sum('amount'),
            ];

            return view('admin.billing.index', compact('bills', 'billStats', 'tab'));
        }

        // Default: subscriptions
        $subscriptions = Subscription::with(['user', 'plan'])
            ->latest()
            ->paginate(20);

        return view('admin.billing.index', compact('subscriptions', 'tab'));
    }

    /**
     * List all bills
     */
    public function bills(Request $request)
    {
        $query = Bill::with(['user', 'subscription.plan', 'payment'])->latest();

        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        if ($request->has('type') && $request->type) {
            $query->where('type', $request->type);
        }

        $bills = $query->paginate(50);

        return view('admin.bills.index', compact('bills'));
    }

    /**
     * Show bill details
     */
    public function showBill(Bill $bill)
    {
        $bill->load(['user', 'subscription.plan', 'payment']);
        return view('admin.bills.show', compact('bill'));
    }

    /**
     * Mark bill as paid manually
     */
    public function markAsPaid(Request $request, Bill $bill)
    {
        $request->validate([
            'payment_reference' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        // Create a payment record if reference provided
        $payment = null;
        if ($request->payment_reference) {
            $payment = Payment::create([
                'reference' => $request->payment_reference,
                'user_id' => $bill->user_id,
                'amount' => $bill->amount,
                'currency' => $bill->currency,
                'provider' => $bill->metadata['payment_method'] ?? 'manual',
                'status' => 'success',
                'metadata' => [
                    'bill_id' => $bill->id,
                    'admin_id' => auth()->id(),
                    'notes' => $request->notes,
                    'marked_paid_by' => auth()->user()->name,
                ],
            ]);
        }

        $bill->update([
            'status' => 'paid',
            'paid_at' => now(),
            'payment_id' => $payment?->id,
            'metadata' => array_merge($bill->metadata ?? [], [
                'marked_paid_by' => auth()->user()->name,
                'marked_paid_at' => now()->toIso8601String(),
                'notes' => $request->notes,
            ]),
        ]);

        Log::info('Bill marked as paid by admin', [
            'bill_id' => $bill->id,
            'admin_id' => auth()->id(),
        ]);

        return redirect()->route('admin.bills.index')
            ->with('success', 'Bill marked as paid successfully.');
    }

    /**
     * Cancel a bill
     */
    public function cancel(Bill $bill)
    {
        if ($bill->status === 'paid') {
            return back()->with('error', 'Cannot cancel a paid bill.');
        }

        $bill->update([
            'status' => 'cancelled',
            'metadata' => array_merge($bill->metadata ?? [], [
                'cancelled_by' => auth()->user()->name,
                'cancelled_at' => now()->toIso8601String(),
            ]),
        ]);

        Log::info('Bill cancelled by admin', [
            'bill_id' => $bill->id,
            'admin_id' => auth()->id(),
        ]);

        return back()->with('success', 'Bill cancelled successfully.');
    }

    /**
     * Show a specific user's billing and subscription history.
     */
    public function userHistory(User $user)
    {
        $subscriptions = $user->subscriptions()
            ->with('plan')
            ->orderByDesc('start_date')
            ->paginate(10);
    
        $adjustments = TokenAdjustment::whereIn('subscription_id', $subscriptions->pluck('id'))
            ->with('admin', 'subscription.plan')
            ->latest()
            ->get();

        // Get user's bills
        $bills = Bill::where('user_id', $user->id)
            ->with(['subscription.plan', 'payment'])
            ->latest()
            ->get();
    
        return view('admin.billing.user_history', compact('user', 'subscriptions', 'adjustments', 'bills'));
    }

    /**
     * Show the form to manually adjust a subscription’s token usage.
     */
    public function editUsage(Subscription $subscription)
    {
        return view('admin.billing.adjust', compact('subscription'));
    }

    /**
     * Process token adjustment for a subscription.
     */
        public function updateUsage(Request $request, Subscription $subscription)
    {
        $request->validate([
            'tokens_used' => 'required|integer|min:0',
            'reason' => 'nullable|string|max:255'
        ]);

        // Only log if the tokens are actually being changed
        if ($subscription->tokens_used !== (int) $request->tokens_used) {
            TokenUsageLog::create([
                'subscription_id'  => $subscription->id,
                'admin_id'         => auth()->id(), // assumes admin is authenticated via guard
                'previous_tokens'  => $subscription->tokens_used,
                'new_tokens'       => (int) $request->tokens_used,
                'reason'           => $request->reason,
            ]);
        }

        $subscription->tokens_used = (int) $request->tokens_used;
        $subscription->save();

        return redirect()
            ->route('admin.billing.userHistory', $subscription->user_id)
            ->with('success', 'Subscription usage updated and logged successfully.');
    }
}