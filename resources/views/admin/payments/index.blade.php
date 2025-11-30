@extends('layouts.admin')
@section('title', 'Payments')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold text-primary"><i class="fas fa-money-bill-wave me-2"></i>Payments</h2>
        <a href="{{ route('admin.payments.stats') }}" class="btn btn-outline-primary">
            <i class="fas fa-chart-bar me-2"></i>View Statistics
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

    <!-- Filters -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.payments.index') }}" class="row g-3">
                <div class="col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select name="status" id="status" class="form-select">
                        <option value="">All Statuses</option>
                        <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="success" {{ request('status') === 'success' ? 'selected' : '' }}>Success</option>
                        <option value="failed" {{ request('status') === 'failed' ? 'selected' : '' }}>Failed</option>
                        <option value="refunded" {{ request('status') === 'refunded' ? 'selected' : '' }}>Refunded</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="provider" class="form-label">Provider</label>
                    <select name="provider" id="provider" class="form-select">
                        <option value="">All Providers</option>
                        <option value="stripe" {{ request('provider') === 'stripe' ? 'selected' : '' }}>Stripe</option>
                        <option value="chapa" {{ request('provider') === 'chapa' ? 'selected' : '' }}>Chapa</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="date_from" class="form-label">From Date</label>
                    <input type="date" name="date_from" id="date_from" class="form-control" value="{{ request('date_from') }}">
                </div>
                <div class="col-md-2">
                    <label for="date_to" class="form-label">To Date</label>
                    <input type="date" name="date_to" id="date_to" class="form-control" value="{{ request('date_to') }}">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter me-2"></i>Filter
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Payments Table -->
    @if ($payments->count())
        <div class="table-responsive card shadow-sm">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>User</th>
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
                                @if ($payment->user)
                                    <a href="{{ route('admin.payments.user', $payment->user) }}" class="text-decoration-none">
                                        {{ $payment->user->name }}
                                    </a>
                                    <br>
                                    <small class="text-muted">{{ $payment->user->email }}</small>
                                @else
                                    <span class="text-muted">Guest</span>
                                @endif
                            </td>
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
                                <div class="btn-group" role="group">
                                    <a href="{{ route('admin.payments.show', $payment) }}" 
                                       class="btn btn-sm btn-outline-primary" 
                                       title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    @if ($payment->status === 'pending')
                                        <form method="POST" action="{{ route('admin.payments.verify', $payment) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-info" title="Verify">
                                                <i class="fas fa-check-circle"></i>
                                            </button>
                                        </form>
                                    @endif
                                    @if ($payment->status === 'success')
                                        <button type="button" class="btn btn-sm btn-outline-warning" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#refundModal{{ $payment->id }}"
                                                title="Refund">
                                            <i class="fas fa-undo"></i>
                                        </button>
                                    @endif
                                </div>

                                <!-- Refund Modal -->
                                <div class="modal fade" id="refundModal{{ $payment->id }}" tabindex="-1">
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
                                                        <label for="refund_amount{{ $payment->id }}" class="form-label">Refund Amount (leave empty for full refund)</label>
                                                        <input type="number" 
                                                               name="amount" 
                                                               id="refund_amount{{ $payment->id }}" 
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
                <p class="text-muted">No payments found.</p>
            </div>
        </div>
    @endif
@endsection

