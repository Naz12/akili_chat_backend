<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payment\PaymentManager;
use App\Services\ExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class PaymentAdminController extends Controller
{
    protected PaymentManager $paymentManager;

    public function __construct(PaymentManager $paymentManager)
    {
        $this->paymentManager = $paymentManager;
    }

    /**
     * Display a paginated list of all payments.
     */
    public function index(Request $request)
    {
        $query = Payment::with('user')->latest();

        // Filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('provider')) {
            $query->where('provider', $request->provider);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $payments = $query->paginate(20);

        return view('admin.payments.index', compact('payments'));
    }

    /**
     * Display the specified payment.
     */
    public function show(Payment $payment)
    {
        $payment->load('user');
        return view('admin.payments.show', compact('payment'));
    }

    /**
     * Display all payments for a specific user.
     */
    public function userPayments(User $user)
    {
        $payments = Payment::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('admin.payments.user', compact('user', 'payments'));
    }

    /**
     * Manually verify a payment.
     */
    public function verify(Payment $payment)
    {
        try {
            $this->paymentManager->verifyPayment($payment->reference);

            return redirect()
                ->route('admin.payments.show', $payment)
                ->with('success', 'Payment verified successfully.');
        } catch (\Exception $e) {
            Log::error('Payment verification failed', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->route('admin.payments.show', $payment)
                ->with('error', 'Verification failed: ' . $e->getMessage());
        }
    }

    /**
     * Process a refund for a payment.
     */
    public function refund(Request $request, Payment $payment)
    {
        $request->validate([
            'amount' => 'nullable|numeric|min:0.01|max:' . $payment->amount,
        ]);

        try {
            $amount = $request->filled('amount') ? (float) $request->amount : null;
            $this->paymentManager->refundPayment($payment, $amount);

            return redirect()
                ->route('admin.payments.show', $payment)
                ->with('success', 'Payment refunded successfully.');
        } catch (\Exception $e) {
            Log::error('Payment refund failed', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->route('admin.payments.show', $payment)
                ->with('error', 'Refund failed: ' . $e->getMessage());
        }
    }

    /**
     * Display payment statistics.
     */
    public function stats()
    {
        $totalPayments = Payment::count();
        $successfulPayments = Payment::where('status', 'success')->count();
        $pendingPayments = Payment::where('status', 'pending')->count();
        $failedPayments = Payment::where('status', 'failed')->count();

        $totalRevenue = Payment::where('status', 'success')->sum('amount');
        $stripeRevenue = Payment::where('provider', 'stripe')
            ->where('status', 'success')
            ->sum('amount');
        $chapaRevenue = Payment::where('provider', 'chapa')
            ->where('status', 'success')
            ->sum('amount');

        $stats = [
            'total_payments' => $totalPayments,
            'successful_payments' => $successfulPayments,
            'pending_payments' => $pendingPayments,
            'failed_payments' => $failedPayments,
            'success_rate' => $totalPayments > 0 
                ? round(($successfulPayments / $totalPayments) * 100, 2) 
                : 0,
            'total_revenue' => $totalRevenue,
            'stripe_revenue' => $stripeRevenue,
            'chapa_revenue' => $chapaRevenue,
        ];

        return view('admin.payments.stats', compact('stats'));
    }

    /**
     * Display webhook logs for payments.
     */
    public function webhooks()
    {
        // This can be enhanced to show payment-specific webhook logs
        return redirect()->route('admin.webhooks.index');
    }

    /**
     * Export payments to Excel or PDF
     */
    public function export(Request $request)
    {
        try {
            $format = $request->get('format', 'excel'); // excel or pdf
            $range = $request->get('range', 'current'); // current, all, filtered

            $query = Payment::with('user')->latest();

            // Apply filters if exporting filtered data
            if ($range === 'filtered' || $range === 'current') {
                if ($request->filled('status')) {
                    $query->where('status', $request->status);
                }
                if ($request->filled('provider')) {
                    $query->where('provider', $request->provider);
                }
                if ($request->filled('user_id')) {
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
                $payments = $query->paginate(20);
                $data = $payments->items();
            } elseif ($range === 'custom') {
                $limit = min((int) $request->get('limit', 100), 10000); // Max 10,000
                $data = $query->limit($limit)->get();
            } else {
                $data = $query->get();
            }

            // Prepare data for export
            $exportData = Collection::make($data)->map(function ($payment) {
                return [
                    'ID' => $payment->id,
                    'User Name' => $payment->user->name ?? 'N/A',
                    'User Email' => $payment->user->email ?? 'N/A',
                    'Amount' => number_format($payment->amount, 2),
                    'Currency' => strtoupper($payment->currency),
                    'Provider' => ucfirst($payment->provider),
                    'Status' => ucfirst(str_replace('_', ' ', $payment->status)),
                    'Reference' => $payment->reference,
                    'Transaction ID' => $payment->transaction_id ?? '-',
                    'Date' => $payment->created_at->format('Y-m-d H:i:s'),
                ];
            });

            $headers = ['ID', 'User Name', 'User Email', 'Amount', 'Currency', 'Provider', 'Status', 'Reference', 'Transaction ID', 'Date'];
            $filename = 'payments_' . $range . '_' . now()->format('Y-m-d_H-i-s');

            if ($format === 'pdf') {
                return ExportService::exportToPdf($exportData, $headers, 'Payments Export', $filename);
            }

            return ExportService::exportToExcel($exportData, $headers, $filename);
        } catch (\Exception $e) {
            \Log::error('Export error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return redirect()->back()->with('error', 'Export failed: ' . $e->getMessage());
        }
    }
}
