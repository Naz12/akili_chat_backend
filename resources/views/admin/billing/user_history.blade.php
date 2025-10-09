@extends('layouts.admin')
@section('title', 'User Billing History')

@section('content')
    <div class="mb-4">
        <h2 class="fw-bold text-primary">
            <i class="fas fa-user-clock me-2"></i>Billing History for {{ $user->name }}
        </h2>
        <p class="text-muted mb-1">{{ $user->email }}</p>
        <a href="{{ route('admin.billing.index') }}" class="btn btn-sm btn-secondary mt-2">
            <i class="fas fa-arrow-left"></i> Back to Billing Overview
        </a>
    </div>

    @if ($subscriptions->count())
        <div class="card shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Plan</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th>Tokens Used</th>
                                <th>Status</th>
                                <th>Auto Renew</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($subscriptions as $sub)
                                <tr>
                                    <td>{{ $sub->plan->name ?? 'N/A' }}</td>
                                    <td>{{ \Carbon\Carbon::parse($sub->start_date)->format('Y-m-d') }}</td>
                                    <td>{{ \Carbon\Carbon::parse($sub->end_date)->format('Y-m-d') }}</td>
                                    <td>{{ $sub->tokens_used }}</td>
                                    <td>
                                        @if ($sub->end_date >= now())
                                            <span class="badge bg-success">Active</span>
                                        @else
                                            <span class="badge bg-secondary">Expired</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge bg-{{ $sub->auto_renew ? 'primary' : 'secondary' }}">
                                            {{ $sub->auto_renew ? 'Yes' : 'No' }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="mt-3">
            {{ $subscriptions->links() }}
        </div>

        {{-- Token Adjustment History --}}
        <h4 class="mt-5">Token Adjustment History</h4>
        <div class="table-responsive">
            <table class="table table-bordered table-sm">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Plan</th>
                        <th>Old Tokens</th>
                        <th>New Tokens</th>
                        <th>Changed By</th>
                        <th>Reason</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($adjustments as $adjustment)
                        <tr>
                            <td>{{ $adjustment->created_at->format('Y-m-d H:i') }}</td>
                            <td>{{ $adjustment->subscription->plan->name ?? 'N/A' }}</td>
                            <td>{{ $adjustment->old_tokens }}</td>
                            <td>{{ $adjustment->new_tokens }}</td>
                            <td>{{ $adjustment->admin->name ?? 'Unknown' }}</td>
                            <td>{{ $adjustment->reason ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted">No token adjustments found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @else
        <div class="alert alert-info mt-3">
            <i class="fas fa-info-circle me-1"></i> This user has no subscription history.
        </div>
    @endif
@endsection
