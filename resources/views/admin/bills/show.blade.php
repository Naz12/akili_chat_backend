@extends('layouts.admin')
@section('title', 'Bill Details')

@section('content')
    <div class="mb-4">
        <a href="{{ route('admin.bills.index') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Bills
        </a>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">
                        <i class="fas fa-file-invoice-dollar me-2"></i>Bill Details #{{ $bill->id }}
                    </h4>
                </div>
                <div class="card-body">
                    <table class="table table-borderless">
                        <tr>
                            <th width="200">Bill ID:</th>
                            <td>{{ $bill->id }}</td>
                        </tr>
                        <tr>
                            <th>Type:</th>
                            <td>
                                <span class="badge bg-info">{{ ucfirst(str_replace('_', ' ', $bill->type)) }}</span>
                            </td>
                        </tr>
                        <tr>
                            <th>Status:</th>
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
                        </tr>
                        <tr>
                            <th>Amount:</th>
                            <td><strong>{{ number_format($bill->amount, 2) }} {{ $bill->currency }}</strong></td>
                        </tr>
                        <tr>
                            <th>Due Date:</th>
                            <td>
                                {{ $bill->due_date->format('Y-m-d H:i:s') }}
                                @if ($bill->status === 'pending')
                                    <br>
                                    <small class="text-muted">
                                        {{ $bill->due_date->diffForHumans() }}
                                    </small>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>Paid At:</th>
                            <td>{{ $bill->paid_at ? $bill->paid_at->format('Y-m-d H:i:s') : 'Not paid' }}</td>
                        </tr>
                        <tr>
                            <th>Description:</th>
                            <td>{{ $bill->description ?? 'N/A' }}</td>
                        </tr>
                        <tr>
                            <th>Created At:</th>
                            <td>{{ $bill->created_at->format('Y-m-d H:i:s') }}</td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- User Information -->
            <div class="card shadow-sm mt-4">
                <div class="card-header">
                    <h5 class="mb-0">User Information</h5>
                </div>
                <div class="card-body">
                    <table class="table table-borderless">
                        <tr>
                            <th width="200">User:</th>
                            <td>
                                <a href="{{ route('admin.billing.userHistory', $bill->user_id) }}">
                                    {{ $bill->user->name ?? 'N/A' }}
                                </a>
                            </td>
                        </tr>
                        <tr>
                            <th>Email:</th>
                            <td>{{ $bill->user->email ?? 'N/A' }}</td>
                        </tr>
                        <tr>
                            <th>User ID:</th>
                            <td>{{ $bill->user_id }}</td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Subscription Information -->
            @if ($bill->subscription)
            <div class="card shadow-sm mt-4">
                <div class="card-header">
                    <h5 class="mb-0">Subscription Information</h5>
                </div>
                <div class="card-body">
                    <table class="table table-borderless">
                        <tr>
                            <th width="200">Subscription ID:</th>
                            <td>{{ $bill->subscription->id }}</td>
                        </tr>
                        <tr>
                            <th>Plan:</th>
                            <td>{{ $bill->subscription->plan->name ?? 'N/A' }}</td>
                        </tr>
                        <tr>
                            <th>Start Date:</th>
                            <td>{{ $bill->subscription->start_date->format('Y-m-d') }}</td>
                        </tr>
                        <tr>
                            <th>End Date:</th>
                            <td>{{ $bill->subscription->end_date->format('Y-m-d') }}</td>
                        </tr>
                        <tr>
                            <th>Is Active:</th>
                            <td>
                                <span class="badge bg-{{ $bill->subscription->is_active ? 'success' : 'secondary' }}">
                                    {{ $bill->subscription->is_active ? 'Yes' : 'No' }}
                                </span>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
            @endif

            <!-- Payment Information -->
            @if ($bill->payment)
            <div class="card shadow-sm mt-4">
                <div class="card-header">
                    <h5 class="mb-0">Payment Information</h5>
                </div>
                <div class="card-body">
                    <table class="table table-borderless">
                        <tr>
                            <th width="200">Payment ID:</th>
                            <td>{{ $bill->payment->id }}</td>
                        </tr>
                        <tr>
                            <th>Reference:</th>
                            <td>{{ $bill->payment->reference }}</td>
                        </tr>
                        <tr>
                            <th>Provider:</th>
                            <td>{{ ucfirst($bill->payment->provider) }}</td>
                        </tr>
                        <tr>
                            <th>Status:</th>
                            <td>
                                <span class="badge bg-{{ $bill->payment->status === 'success' ? 'success' : 'warning' }}">
                                    {{ ucfirst($bill->payment->status) }}
                                </span>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
            @endif

            <!-- Metadata -->
            @if ($bill->metadata)
            <div class="card shadow-sm mt-4">
                <div class="card-header">
                    <h5 class="mb-0">Metadata</h5>
                </div>
                <div class="card-body">
                    <pre class="bg-light p-3 rounded">{{ json_encode($bill->metadata, JSON_PRETTY_PRINT) }}</pre>
                </div>
            </div>
            @endif
        </div>

        <div class="col-md-4">
            <!-- Actions -->
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="mb-0">Actions</h5>
                </div>
                <div class="card-body">
                    @if ($bill->status === 'pending')
                        <button type="button" class="btn btn-success w-100 mb-2" 
                            data-bs-toggle="modal" 
                            data-bs-target="#markPaidModal">
                            <i class="fas fa-check me-2"></i>Mark as Paid
                        </button>
                        
                        <form method="POST" action="{{ route('admin.bills.cancel', $bill) }}" 
                            onsubmit="return confirm('Are you sure you want to cancel this bill?');">
                            @csrf
                            <button type="submit" class="btn btn-danger w-100">
                                <i class="fas fa-times me-2"></i>Cancel Bill
                            </button>
                        </form>
                    @endif
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="card shadow-sm mt-3">
                <div class="card-header">
                    <h5 class="mb-0">Quick Info</h5>
                </div>
                <div class="card-body">
                    <p class="mb-2">
                        <strong>Days Until Due:</strong><br>
                        @if ($bill->status === 'pending')
                            @if ($bill->due_date < now())
                                <span class="text-danger">Overdue by {{ $bill->due_date->diffInDays(now()) }} days</span>
                            @else
                                {{ $bill->due_date->diffInDays(now()) }} days
                            @endif
                        @else
                            N/A
                        @endif
                    </p>
                    <p class="mb-2">
                        <strong>Can Pay:</strong><br>
                        <span class="badge bg-{{ $bill->canBePaid() ? 'success' : 'danger' }}">
                            {{ $bill->canBePaid() ? 'Yes' : 'No' }}
                        </span>
                    </p>
                    <p class="mb-0">
                        <strong>Is Overdue:</strong><br>
                        <span class="badge bg-{{ $bill->isOverdue() ? 'danger' : 'success' }}">
                            {{ $bill->isOverdue() ? 'Yes' : 'No' }}
                        </span>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Mark as Paid Modal -->
    <div class="modal fade" id="markPaidModal" tabindex="-1">
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
@endsection

