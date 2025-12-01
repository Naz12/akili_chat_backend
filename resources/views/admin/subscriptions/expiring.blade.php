@extends('layouts.admin')
@section('title', 'Expiring Subscriptions')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold text-primary">
            <i class="fas fa-calendar-times me-2"></i>Expiring Subscriptions
        </h2>
        <a href="{{ route('admin.subscriptions.index') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Subscriptions
        </a>
    </div>

    <!-- Filter -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.subscriptions.expiring') }}" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Days Ahead</label>
                    <select name="days" class="form-select">
                        <option value="3" {{ request('days', 7) == 3 ? 'selected' : '' }}>3 days</option>
                        <option value="7" {{ request('days', 7) == 7 ? 'selected' : '' }}>7 days</option>
                        <option value="14" {{ request('days', 7) == 14 ? 'selected' : '' }}>14 days</option>
                        <option value="30" {{ request('days', 7) == 30 ? 'selected' : '' }}>30 days</option>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary">Filter</button>
                </div>
            </form>
        </div>
    </div>

    @if ($subscriptions->count())
        <div class="table-responsive card shadow-sm">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>User</th>
                        <th>Plan</th>
                        <th>End Date</th>
                        <th>Days Until Expiry</th>
                        <th>Auto Renew</th>
                        <th>Payment Method</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($subscriptions as $sub)
                        @php
                            $daysUntil = $sub->end_date->diffInDays(now());
                            $metadata = $sub->metadata ?? [];
                        @endphp
                        <tr class="{{ $daysUntil <= 3 ? 'table-warning' : '' }}">
                            <td>
                                {{ $sub->user->name }}
                                <br>
                                <small class="text-muted">{{ $sub->user->email }}</small>
                            </td>
                            <td>{{ $sub->plan->name }}</td>
                            <td>{{ $sub->end_date->format('Y-m-d') }}</td>
                            <td>
                                <span class="badge bg-{{ $daysUntil <= 3 ? 'danger' : 'warning' }}">
                                    {{ $daysUntil }} days
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-{{ $sub->auto_renew ? 'success' : 'secondary' }}">
                                    {{ $sub->auto_renew ? 'Yes' : 'No' }}
                                </span>
                            </td>
                            <td>
                                {{ ucfirst($metadata['payment_method'] ?? 'N/A') }}
                            </td>
                            <td>
                                <form method="POST" action="{{ route('admin.subscriptions.triggerRenewal', $sub) }}" 
                                    class="d-inline"
                                    onsubmit="return confirm('Trigger renewal for this subscription?');">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-primary">
                                        Trigger Renewal
                                    </button>
                                </form>
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
            <i class="fas fa-info-circle me-1"></i> No expiring subscriptions found in the next {{ $days }} days.
        </div>
    @endif
@endsection

