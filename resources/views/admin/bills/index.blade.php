@extends('layouts.admin')
@section('title', 'Bills Management')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold text-primary">
            <i class="fas fa-file-invoice-dollar me-2"></i>Bills Management
        </h2>
        <div class="d-flex gap-2">
            <x-export-button 
                route="{{ route('admin.bills.export') }}"
                title="Export Bills"
                :currentCount="$bills->count()"
                :totalCount="\App\Models\Bill::count()"
                :hasFilters="request()->hasAny(['status', 'type', 'user_id'])"
            />
            <a href="{{ route('admin.billing.index') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Billing
            </a>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.bills.index') }}" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Statuses</option>
                        <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="paid" {{ request('status') === 'paid' ? 'selected' : '' }}>Paid</option>
                        <option value="overdue" {{ request('status') === 'overdue' ? 'selected' : '' }}>Overdue</option>
                        <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Type</label>
                    <select name="type" class="form-select">
                        <option value="">All Types</option>
                        <option value="renewal" {{ request('type') === 'renewal' ? 'selected' : '' }}>Renewal</option>
                        <option value="renewal_failed" {{ request('type') === 'renewal_failed' ? 'selected' : '' }}>Renewal Failed</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">User ID</label>
                    <input type="number" name="user_id" class="form-control" value="{{ request('user_id') }}" placeholder="User ID">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                    <a href="{{ route('admin.bills.index') }}" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Clear
                    </a>
                </div>
            </form>
        </div>
    </div>

    @if ($bills->count())
        <div class="table-responsive card shadow-sm">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>User</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Amount</th>
                        <th>Due Date</th>
                        <th>Paid At</th>
                        <th>Plan</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($bills as $bill)
                        <tr>
                            <td>{{ $bill->id }}</td>
                            <td>
                                <a href="{{ route('admin.billing.userHistory', $bill->user_id) }}">
                                    {{ $bill->user->name ?? 'N/A' }}
                                </a>
                                <br>
                                <small class="text-muted">{{ $bill->user->email ?? 'N/A' }}</small>
                            </td>
                            <td>
                                <span class="badge bg-info">{{ ucfirst(str_replace('_', ' ', $bill->type)) }}</span>
                            </td>
                            <td>
                                @if ($bill->status === 'paid')
                                    <span class="badge bg-success">Paid</span>
                                @elseif ($bill->status === 'pending' && $bill->due_date < now())
                                    <span class="badge bg-danger">Overdue</span>
                                @elseif ($bill->status === 'pending')
                                    <span class="badge bg-warning">Pending</span>
                                @else
                                    <span class="badge bg-secondary">{{ ucfirst($bill->status) }}</span>
                                @endif
                            </td>
                            <td>
                                <strong>{{ number_format($bill->amount, 2) }} {{ $bill->currency }}</strong>
                            </td>
                            <td>
                                {{ $bill->due_date->format('Y-m-d') }}
                                @if ($bill->status === 'pending')
                                    <br>
                                    <small class="text-muted">
                                        {{ $bill->due_date->diffForHumans() }}
                                    </small>
                                @endif
                            </td>
                            <td>
                                {{ $bill->paid_at ? $bill->paid_at->format('Y-m-d H:i') : '-' }}
                            </td>
                            <td>
                                {{ $bill->subscription->plan->name ?? 'N/A' }}
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('admin.bills.show', $bill) }}" class="btn btn-outline-primary">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    @if ($bill->status === 'pending')
                                        <button type="button" class="btn btn-outline-success" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#markPaidModal{{ $bill->id }}">
                                            <i class="fas fa-check"></i>
                                        </button>
                                        <form method="POST" action="{{ route('admin.bills.cancel', $bill) }}" 
                                            class="d-inline" 
                                            onsubmit="return confirm('Are you sure you want to cancel this bill?');">
                                            @csrf
                                            <button type="submit" class="btn btn-outline-danger">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>

                                <!-- Mark as Paid Modal -->
                                <div class="modal fade" id="markPaidModal{{ $bill->id }}" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form method="POST" action="{{ route('admin.bills.markPaid', $bill) }}">
                                                @csrf
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Mark Bill as Paid</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p><strong>Bill ID:</strong> {{ $bill->id }}</p>
                                                    <p><strong>Amount:</strong> {{ $bill->amount }} {{ $bill->currency }}</p>
                                                    <p><strong>User:</strong> {{ $bill->user->name ?? 'N/A' }}</p>
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label">Payment Reference (Optional)</label>
                                                        <input type="text" name="payment_reference" class="form-control" 
                                                            placeholder="e.g., payment reference or transaction ID">
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label">Notes (Optional)</label>
                                                        <textarea name="notes" class="form-control" rows="3" 
                                                            placeholder="Add any notes about this payment..."></textarea>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-success">Mark as Paid</button>
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

        <div class="mt-3">
            {{ $bills->links() }}
        </div>
    @else
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-1"></i> No bills found.
        </div>
    @endif
@endsection

