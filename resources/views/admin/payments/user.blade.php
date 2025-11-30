@extends('layouts.admin')
@section('title', 'User Payments')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold text-primary">
            <i class="fas fa-user me-2"></i>Payments for {{ $user->name }}
        </h2>
        <a href="{{ route('admin.payments.index') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to All Payments
        </a>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h5>User Information</h5>
            <p class="mb-0">
                <strong>Name:</strong> {{ $user->name }}<br>
                <strong>Email:</strong> {{ $user->email }}<br>
                <strong>User ID:</strong> {{ $user->id }}
            </p>
        </div>
    </div>

    @if ($payments->count())
        <div class="table-responsive card shadow-sm">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Amount</th>
                        <th>Provider</th>
                        <th>Status</th>
                        <th>Reference</th>
                        <th>Transaction ID</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($payments as $payment)
                        <tr>
                            <td>{{ $payment->id }}</td>
                            <td>
                                <strong>{{ number_format($payment->amount, 2) }}</strong>
                                <br>
                                <small class="text-muted">{{ strtoupper($payment->currency) }}</small>
                            </td>
                            <td>
                                <span class="badge bg-info">{{ ucfirst($payment->provider) }}</span>
                            </td>
                            <td>
                                @php
                                    $statusColors = [
                                        'pending' => 'warning',
                                        'success' => 'success',
                                        'failed' => 'danger',
                                        'refunded' => 'secondary',
                                        'retried_failed' => 'danger',
                                        'retried_success' => 'success',
                                    ];
                                    $color = $statusColors[$payment->status] ?? 'secondary';
                                @endphp
                                <span class="badge bg-{{ $color }}">{{ ucfirst(str_replace('_', ' ', $payment->status)) }}</span>
                            </td>
                            <td>
                                <code class="text-primary">{{ $payment->reference }}</code>
                            </td>
                            <td>
                                @if ($payment->transaction_id)
                                    <code class="text-muted">{{ $payment->transaction_id }}</code>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                {{ $payment->created_at->format('Y-m-d H:i') }}
                            </td>
                            <td>
                                <a href="{{ route('admin.payments.show', $payment) }}" 
                                   class="btn btn-sm btn-outline-primary" 
                                   title="View Details">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="mt-4">
            {{ $payments->links() }}
        </div>
    @else
        <div class="card shadow-sm">
            <div class="card-body text-center py-5">
                <i class="fas fa-money-bill-wave fa-3x text-muted mb-3"></i>
                <p class="text-muted">No payments found for this user.</p>
            </div>
        </div>
    @endif
@endsection

