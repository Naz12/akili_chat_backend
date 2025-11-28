<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payment\PaymentManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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
}
