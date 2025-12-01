@extends('layouts.admin')
@section('title', 'Billing Overview')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold text-primary"><i class="fas fa-file-invoice-dollar me-2"></i>Billing Overview</h2>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-4" role="tablist">
        <li class="nav-item">
            <a class="nav-link {{ ($tab ?? 'subscriptions') === 'subscriptions' ? 'active' : '' }}" 
               href="{{ route('admin.billing.index', ['tab' => 'subscriptions']) }}">
                <i class="fas fa-credit-card me-2"></i>Subscriptions
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ ($tab ?? '') === 'bills' ? 'active' : '' }}" 
               href="{{ route('admin.billing.index', ['tab' => 'bills']) }}">
                <i class="fas fa-file-invoice me-2"></i>Bills
                @if (isset($billStats) && $billStats['pending'] > 0)
                    <span class="badge bg-warning ms-2">{{ $billStats['pending'] }}</span>
                @endif
            </a>
        </li>
    </ul>

    @if (($tab ?? 'subscriptions') === 'bills')
        <!-- Bills Tab -->
        @if (isset($bills) && $bills->count())
            <!-- Bill Statistics -->
            @if (isset($billStats))
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <h5 class="text-muted">Total Bills</h5>
                            <h3 class="mb-0">{{ $billStats['total'] }}</h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <h5 class="text-warning">Pending</h5>
                            <h3 class="mb-0 text-warning">{{ $billStats['pending'] }}</h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <h5 class="text-danger">Overdue</h5>
                            <h3 class="mb-0 text-danger">{{ $billStats['overdue'] }}</h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <h5 class="text-success">Paid</h5>
                            <h3 class="mb-0 text-success">{{ $billStats['paid'] }}</h3>
                        </div>
                    </div>
                </div>
            </div>
            @endif

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
                                </td>
                                <td>
                                    <span class="badge bg-info">{{ ucfirst(str_replace('_', ' ', $bill->type)) }}</span>
                                </td>
                                <td>
                                    @if ($bill->status === 'paid')
                                        <span class="badge bg-success">Paid</span>
                                    @elseif ($bill->status === 'pending' && $bill->due_date && $bill->due_date < now())
                                        <span class="badge bg-danger">Overdue</span>
                                    @else
                                        <span class="badge bg-warning">Pending</span>
                                    @endif
                                </td>
                                <td>{{ number_format($bill->amount, 2) }} {{ $bill->currency }}</td>
                                <td>{{ $bill->due_date ? $bill->due_date->format('Y-m-d') : 'N/A' }}</td>
                                <td>{{ $bill->subscription && $bill->subscription->plan ? $bill->subscription->plan->name : 'N/A' }}</td>
                                <td>
                                    <a href="{{ route('admin.bills.show', $bill) }}" class="btn btn-sm btn-outline-primary">
                                        View
                                    </a>
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
    @else
        <!-- Subscriptions Tab -->
    @if ($subscriptions->count())
        <div class="table-responsive card shadow-sm">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>User</th>
                        <th>Plan</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Tokens Used</th>
                        <th>Auto Renew</th>
                        <th>Payment Info</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($subscriptions as $sub)
                        <tr>
                            <td>{{ $sub->user->name }}<br><small class="text-muted">{{ $sub->user->email }}</small></td>
                            <td>{{ $sub->plan->name ?? 'N/A' }}</td>
                            <td>{{ \Carbon\Carbon::parse($sub->start_date)->format('Y-m-d') }}</td>
                            <td>{{ \Carbon\Carbon::parse($sub->end_date)->format('Y-m-d') }}</td>
                            <td>{{ $sub->tokens_used }}</td>
                            <td>
                                <span class="badge bg-{{ $sub->auto_renew ? 'success' : 'secondary' }}">
                                    {{ $sub->auto_renew ? 'Yes' : 'No' }}
                                </span>
                            </td>
                            <td>
                                @php
                                    $metadata = $sub->metadata ?? [];
                                    $stripeSubId = $metadata['stripe_subscription_id'] ?? null;
                                    $paymentMethod = $metadata['payment_method'] ?? null;
                                    $gracePeriod = $sub->grace_period_ends_at;
                                @endphp
                                
                                @if ($stripeSubId)
                                    <small class="d-block text-info">
                                        <i class="fas fa-credit-card"></i> Stripe: {{ substr($stripeSubId, 0, 20) }}...
                                    </small>
                                @endif
                                
                                @if ($paymentMethod)
                                    <small class="d-block text-muted">
                                        Payment: {{ ucfirst($paymentMethod) }}
                                    </small>
                                @endif
                                
                                @if ($gracePeriod)
                                    <small class="d-block text-warning">
                                        <i class="fas fa-exclamation-triangle"></i> Grace: {{ $gracePeriod->format('Y-m-d') }}
                                    </small>
                                @endif
                                
                                @if ($sub->payment_failure_count > 0)
                                    <small class="d-block text-danger">
                                        <i class="fas fa-times-circle"></i> Failures: {{ $sub->payment_failure_count }}
                                    </small>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('admin.billing.userHistory', $sub->user_id) }}"
                                    class="btn btn-sm btn-outline-primary">
                                    View History
                                </a>
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
            <i class="fas fa-circle-info me-1"></i> No subscriptions found.
        </div>
        @endif
    @endif
@endsection
