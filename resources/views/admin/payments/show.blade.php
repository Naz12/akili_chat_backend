@extends('layouts.admin')
@section('title', 'Payment Details')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold text-primary"><i class="fas fa-money-bill-wave me-2"></i>Payment Details</h2>
        <a href="{{ route('admin.payments.index') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Payments
        </a>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Payment Information</h5>
                </div>
                <div class="card-body">
                    <table class="table table-borderless">
                        <tr>
                            <th width="200">Payment ID:</th>
                            <td>{{ $payment->id }}</td>
                        </tr>
                        <tr>
                            <th>Reference:</th>
                            <td><code>{{ $payment->reference }}</code></td>
                        </tr>
                        <tr>
                            <th>Transaction ID:</th>
                            <td>
                                @if ($payment->transaction_id)
                                    <code>{{ $payment->transaction_id }}</code>
                                @else
                                    <span class="text-muted">Not available</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>User:</th>
                            <td>
                                @if ($payment->user)
                                    <a href="{{ route('admin.payments.user', $payment->user) }}" class="text-decoration-none">
                                        {{ $payment->user->name }} ({{ $payment->user->email }})
                                    </a>
                                @else
                                    <span class="text-muted">Guest</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>Amount:</th>
                            <td>
                                <strong class="text-primary">{{ number_format($payment->amount, 2) }} {{ strtoupper($payment->currency) }}</strong>
                            </td>
                        </tr>
                        <tr>
                            <th>Provider:</th>
                            <td>
                                <span class="badge bg-info">{{ ucfirst($payment->provider) }}</span>
                            </td>
                        </tr>
                        <tr>
                            <th>Status:</th>
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
                        </tr>
                        <tr>
                            <th>Created At:</th>
                            <td>{{ $payment->created_at->format('Y-m-d H:i:s') }}</td>
                        </tr>
                        <tr>
                            <th>Updated At:</th>
                            <td>{{ $payment->updated_at->format('Y-m-d H:i:s') }}</td>
                        </tr>
                    </table>
                </div>
            </div>

            @if ($payment->metadata)
                <div class="card shadow-sm mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">Metadata</h5>
                    </div>
                    <div class="card-body">
                        <pre class="bg-light p-3 rounded">{{ json_encode($payment->metadata, JSON_PRETTY_PRINT) }}</pre>
                    </div>
                </div>
            @endif
        </div>

        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0">Actions</h5>
                </div>
                <div class="card-body">
                    @if ($payment->status === 'pending')
                        <form method="POST" action="{{ route('admin.payments.verify', $payment) }}" class="mb-3">
                            @csrf
                            <button type="submit" class="btn btn-info w-100">
                                <i class="fas fa-check-circle me-2"></i>Verify Payment
                            </button>
                        </form>
                    @endif

                    @if ($payment->status === 'success')
                        <button type="button" class="btn btn-warning w-100" 
                                data-bs-toggle="modal" 
                                data-bs-target="#refundModal">
                            <i class="fas fa-undo me-2"></i>Refund Payment
                        </button>

                        <!-- Refund Modal -->
                        <div class="modal fade" id="refundModal" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Refund Payment</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <form method="POST" action="{{ route('admin.payments.refund', $payment) }}">
                                        @csrf
                                        <div class="modal-body">
                                            <p>Payment: <strong>{{ number_format($payment->amount, 2) }} {{ strtoupper($payment->currency) }}</strong></p>
                                            <div class="mb-3">
                                                <label for="refund_amount" class="form-label">Refund Amount (leave empty for full refund)</label>
                                                <input type="number" 
                                                       name="amount" 
                                                       id="refund_amount" 
                                                       class="form-control" 
                                                       step="0.01" 
                                                       min="0.01" 
                                                       max="{{ $payment->amount }}"
                                                       placeholder="Full refund">
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-warning">Process Refund</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endif

                    @if ($payment->status === 'refunded')
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            This payment has been refunded.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

