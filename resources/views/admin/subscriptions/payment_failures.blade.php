@extends('layouts.admin')
@section('title', 'Payment Failures')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold text-primary">
            <i class="fas fa-exclamation-triangle me-2"></i>Payment Failures
        </h2>
        <a href="{{ route('admin.subscriptions.index') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Subscriptions
        </a>
    </div>

    @if ($subscriptions->count())
        <div class="table-responsive card shadow-sm">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>User</th>
                        <th>Plan</th>
                        <th>Failure Count</th>
                        <th>Grace Period</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($subscriptions as $sub)
                        <tr>
                            <td>
                                {{ $sub->user->name }}
                                <br>
                                <small class="text-muted">{{ $sub->user->email }}</small>
                            </td>
                            <td>{{ $sub->plan->name }}</td>
                            <td>
                                <span class="badge bg-danger">{{ $sub->payment_failure_count }}</span>
                            </td>
                            <td>
                                @if ($sub->grace_period_ends_at)
                                    {{ $sub->grace_period_ends_at->format('Y-m-d') }}
                                    <br>
                                    <small class="text-muted">
                                        {{ $sub->grace_period_ends_at->diffForHumans() }}
                                    </small>
                                @else
                                    <span class="text-muted">No grace period</span>
                                @endif
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
                                    <a href="{{ route('admin.billing.userHistory', $sub->user_id) }}" 
                                        class="btn btn-outline-primary">
                                        View Bills
                                    </a>
                                    <form method="POST" action="{{ route('admin.subscriptions.resetFailure', $sub) }}" 
                                        class="d-inline"
                                        onsubmit="return confirm('Reset payment failure count?');">
                                        @csrf
                                        <button type="submit" class="btn btn-outline-success">
                                            Reset
                                        </button>
                                    </form>
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
            <i class="fas fa-info-circle me-1"></i> No payment failures found.
        </div>
    @endif
@endsection

