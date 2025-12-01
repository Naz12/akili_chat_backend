@extends('layouts.admin')
@section('title', 'Grace Period Management')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold text-primary">
            <i class="fas fa-clock me-2"></i>Subscriptions in Grace Period
        </h2>
        <a href="{{ route('admin.subscriptions.index') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Subscriptions
        </a>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if ($subscriptions->count())
        <div class="table-responsive card shadow-sm">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>User</th>
                        <th>Plan</th>
                        <th>Grace Period Ends</th>
                        <th>Days Remaining</th>
                        <th>Failure Count</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($subscriptions as $sub)
                        @php
                            $daysRemaining = $sub->grace_period_ends_at->diffInDays(now());
                            $isExpiringSoon = $daysRemaining <= 1;
                        @endphp
                        <tr class="{{ $isExpiringSoon ? 'table-warning' : '' }}">
                            <td>
                                {{ $sub->user->name }}
                                <br>
                                <small class="text-muted">{{ $sub->user->email }}</small>
                            </td>
                            <td>{{ $sub->plan->name }}</td>
                            <td>
                                {{ $sub->grace_period_ends_at->format('Y-m-d H:i') }}
                            </td>
                            <td>
                                <span class="badge bg-{{ $isExpiringSoon ? 'danger' : 'warning' }}">
                                    {{ $daysRemaining }} days
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-danger">{{ $sub->payment_failure_count }}</span>
                            </td>
                            <td>
                                @if ($sub->is_active)
                                    <span class="badge bg-success">Active</span>
                                @else
                                    <span class="badge bg-secondary">Inactive</span>
                                @endif
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-outline-primary" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#extendModal{{ $sub->id }}">
                                        Extend
                                    </button>
                                    <form method="POST" action="{{ route('admin.subscriptions.processGrace', $sub) }}" 
                                        class="d-inline"
                                        onsubmit="return confirm('Process grace period expiration? This will downgrade to free plan if not paid.');">
                                        @csrf
                                        <button type="submit" class="btn btn-outline-danger">
                                            Process
                                        </button>
                                    </form>
                                </div>

                                <!-- Extend Grace Period Modal -->
                                <div class="modal fade" id="extendModal{{ $sub->id }}" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form method="POST" action="{{ route('admin.subscriptions.extendGrace', $sub) }}">
                                                @csrf
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Extend Grace Period</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p><strong>User:</strong> {{ $sub->user->name }}</p>
                                                    <p><strong>Plan:</strong> {{ $sub->plan->name }}</p>
                                                    <p><strong>Current Grace Period Ends:</strong> {{ $sub->grace_period_ends_at->format('Y-m-d H:i') }}</p>
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label">Extend by (days)</label>
                                                        <input type="number" name="days" class="form-control" 
                                                            value="3" min="1" max="30" required>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-primary">Extend Grace Period</button>
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
            {{ $subscriptions->links() }}
        </div>
    @else
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-1"></i> No subscriptions in grace period.
        </div>
    @endif
@endsection

