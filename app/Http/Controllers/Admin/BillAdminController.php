<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use App\Models\Subscription;
use App\Models\Bill;
use App\Models\Payment;
use App\Services\ExportService;
use Illuminate\Http\Request;
use App\Models\TokenUsageLog;
use App\Models\TokenAdjustment;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

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
                // Handle 'overdue' status specially - it's a calculated status
                if ($request->status === 'overdue') {
                    // Overdue bills are pending bills with due_date < now()
                    // OR bills that have been explicitly marked as overdue
                    $query->where(function($q) {
                        $q->where(function($subQ) {
                            $subQ->where('status', 'pending')
                                 ->where('due_date', '<', now());
                        })->orWhere('status', 'overdue');
                    });
                } else {
                    $query->where('status', $request->status);
                }
            }

            if ($request->has('type') && $request->type) {
                $query->where('type', $request->type);
            }

            if ($request->has('user_id') && $request->user_id) {
                $query->where('user_id', $request->user_id);
            }

            $bills = $query->paginate(20);

            // Statistics - pending should exclude overdue bills
            $overdueCount = Bill::where(function($q) {
                $q->where('status', 'pending')->where('due_date', '<', now())
                  ->orWhere('status', 'overdue');
            })->count();
            
            $pendingCount = Bill::where('status', 'pending')
                ->where(function($q) {
                    $q->whereNull('due_date')
                      ->orWhere('due_date', '>=', now());
                })
                ->count();
            
            $billStats = [
                'total' => Bill::count(),
                'pending' => $pendingCount,
                'paid' => Bill::where('status', 'paid')->count(),
                'overdue' => $overdueCount,
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
            // Handle 'overdue' status specially - it's a calculated status
            if ($request->status === 'overdue') {
                // Overdue bills are pending bills with due_date < now()
                // OR bills that have been explicitly marked as overdue
                $query->where(function($q) {
                    $q->where(function($subQ) {
                        $subQ->where('status', 'pending')
                             ->where('due_date', '<', now());
                    })->orWhere('status', 'overdue');
                });
            } else {
                $query->where('status', $request->status);
            }
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

    /**
     * Export bills to Excel or PDF
     */
    public function exportBills(Request $request)
    {
        try {
            $format = $request->get('format', 'excel');
            $range = $request->get('range', 'current');

            $query = Bill::with(['user', 'subscription.plan', 'payment'])->latest();

            // Apply filters if exporting filtered data
            if ($range === 'filtered' || $range === 'current') {
                if ($request->has('status') && $request->status) {
                    if ($request->status === 'overdue') {
                        $query->where(function($q) {
                            $q->where(function($subQ) {
                                $subQ->where('status', 'pending')
                                     ->where('due_date', '<', now());
                            })->orWhere('status', 'overdue');
                        });
                    } else {
                        $query->where('status', $request->status);
                    }
                }
                if ($request->has('type') && $request->type) {
                    $query->where('type', $request->type);
                }
                if ($request->has('user_id') && $request->user_id) {
                    $query->where('user_id', $request->user_id);
                }
            }

            // Apply date filters (works with all ranges)
            if ($request->filled('date_from')) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }
            if ($request->filled('date_to')) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            // Get data based on range
            if ($range === 'current') {
                $bills = $query->paginate(20);
                $data = $bills->items();
            } elseif ($range === 'custom') {
                $limit = min((int) $request->get('limit', 100), 10000); // Max 10,000
                $data = $query->limit($limit)->get();
            } else {
                $data = $query->get();
            }

            // Prepare data for export
            $exportData = Collection::make($data)->map(function ($bill) {
                return [
                    'ID' => $bill->id,
                    'User Name' => $bill->user->name ?? 'N/A',
                    'User Email' => $bill->user->email ?? 'N/A',
                    'Type' => ucfirst(str_replace('_', ' ', $bill->type)),
                    'Status' => ucfirst($bill->status),
                    'Amount' => number_format($bill->amount, 2),
                    'Currency' => $bill->currency,
                    'Due Date' => $bill->due_date ? $bill->due_date->format('Y-m-d') : '-',
                    'Paid At' => $bill->paid_at ? $bill->paid_at->format('Y-m-d H:i:s') : '-',
                    'Plan' => $bill->subscription->plan->name ?? 'N/A',
                ];
            });

            $headers = ['ID', 'User Name', 'User Email', 'Type', 'Status', 'Amount', 'Currency', 'Due Date', 'Paid At', 'Plan'];
            $filename = 'bills_' . $range . '_' . now()->format('Y-m-d_H-i-s');

            if ($format === 'pdf') {
                return ExportService::exportToPdf($exportData, $headers, 'Bills Export', $filename);
            }

            return ExportService::exportToExcel($exportData, $headers, $filename);
        } catch (\Exception $e) {
            Log::error('Bills export error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return redirect()->back()->with('error', 'Export failed: ' . $e->getMessage());
        }
    }

    /**
     * Export subscriptions to Excel or PDF
     */
    public function exportSubscriptions(Request $request)
    {
        try {
            $format = $request->get('format', 'excel');
            $range = $request->get('range', 'current');

            $query = Subscription::with(['user', 'plan'])->latest();

            // Apply date filters (works with all ranges)
            if ($request->filled('date_from')) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }
            if ($request->filled('date_to')) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            // Get data based on range
            if ($range === 'current') {
                $subscriptions = $query->paginate(20);
                $data = $subscriptions->items();
            } elseif ($range === 'custom') {
                $limit = min((int) $request->get('limit', 100), 10000); // Max 10,000
                $data = $query->limit($limit)->get();
            } else {
                $data = $query->get();
            }

            // Prepare data for export
            $exportData = Collection::make($data)->map(function ($subscription) {
                $metadata = $subscription->metadata ?? [];
                return [
                    'ID' => $subscription->id,
                    'User Name' => $subscription->user->name ?? 'N/A',
                    'User Email' => $subscription->user->email ?? 'N/A',
                    'Plan' => $subscription->plan->name ?? 'N/A',
                    'Start Date' => $subscription->start_date ? $subscription->start_date->format('Y-m-d') : 'N/A',
                    'End Date' => $subscription->end_date ? $subscription->end_date->format('Y-m-d') : 'N/A',
                    'Tokens Used' => number_format($subscription->tokens_used ?? 0),
                    'Status' => $subscription->is_active ? 'Active' : 'Inactive',
                    'Auto Renew' => $subscription->auto_renew ? 'Yes' : 'No',
                    'Payment Method' => ucfirst($metadata['payment_method'] ?? 'N/A'),
                ];
            });

            $headers = ['ID', 'User Name', 'User Email', 'Plan', 'Start Date', 'End Date', 'Tokens Used', 'Status', 'Auto Renew', 'Payment Method'];
            $filename = 'subscriptions_' . $range . '_' . now()->format('Y-m-d_H-i-s');

            if ($format === 'pdf') {
                return ExportService::exportToPdf($exportData, $headers, 'Subscriptions Export', $filename);
            }

            return ExportService::exportToExcel($exportData, $headers, $filename);
        } catch (\Exception $e) {
            Log::error('Subscriptions export error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return redirect()->back()->with('error', 'Export failed: ' . $e->getMessage());
        }
    }
}