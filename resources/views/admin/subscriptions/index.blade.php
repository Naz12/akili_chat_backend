@extends('layouts.admin')
@section('title', 'Subscriptions')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0">Subscriptions</h2>
        <div class="d-flex gap-2">
            <x-export-button 
                route="{{ route('admin.subscriptions.export') }}"
                title="Export Subscriptions"
                :currentCount="$subscriptions->count()"
                :totalCount="\App\Models\Subscription::count()"
                :hasFilters="false"
            />
            <a href="{{ route('admin.subscriptions.create') }}" class="btn btn-primary">Assign New Subscription</a>
        </div>
    </div>

    <table class="table table-bordered table-hover align-middle">
        <thead class="table-dark">
            <tr>
                <th>User</th>
                <th>Plan</th>
                <th>Region / Currency</th>
                <th>Price / Quotas</th>
                <th>Period</th>
                <th>Tokens Used</th>
                <th>Ads</th>
                <th>Status</th>
                <th>Payment Info</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($subscriptions as $s)
                <tr>
                    <td>
                        {{ $s->user->name }}
                        <br>
                        <small class="text-muted">{{ $s->user->email }}</small>
                    </td>

                    <td>
                        {{ $s->plan->name }}
                        @if ($s->plan->is_default)
                            <span class="badge bg-primary ms-1">Default</span>
                        @elseif ($s->plan->monthly_price == 0)
                            <span class="badge bg-success ms-1">Free</span>
                        @else
                            <span class="badge bg-warning text-dark ms-1">Paid</span>
                        @endif
                    </td>

                    <td>
                        <span class="badge bg-{{ $s->plan->region === 'intl' ? 'info' : 'secondary' }}">
                            {{ ucfirst($s->plan->region) }}
                        </span>
                        <br>
                        {{ config('currencies')[$s->plan->currency] ?? $s->plan->currency }}
                    </td>

                    <td>
                        <div><strong>{{ $s->plan->monthly_price }}</strong> {{ $s->plan->currency }}</div>
                        <small class="text-muted">
                            Max Tokens: {{ $s->plan->max_tokens }}<br>
                            Daily Limit: {{ $s->plan->daily_message_limit }}
                        </small>
                    </td>

                    <td>
                        {{ $s->start_date }} <br> → {{ $s->end_date }}
                    </td>

                    <td>
                        {{ number_format($s->tokens_used) }}
                    </td>

                    <td>
                        @if ($s->plan->ads_enabled)
                            <span class="badge bg-success">Yes</span>
                        @else
                            <span class="badge bg-secondary">No</span>
                        @endif
                    </td>

                    <td>
                        @php
                            $today = \Carbon\Carbon::now();
                            $end = \Carbon\Carbon::parse($s->end_date);
                        @endphp
                        @if ($s->is_active && $today->lte($end))
                            <span class="badge bg-success">Active</span>
                        @elseif ($s->is_active)
                            <span class="badge bg-warning">Active (Expired)</span>
                        @else
                            <span class="badge bg-danger">Inactive</span>
                        @endif
                        <br>
                        <small class="text-muted">
                            Auto Renew: {{ $s->auto_renew ? 'Yes' : 'No' }}
                        </small>
                        @if ($s->grace_period_ends_at)
                            <br>
                            <small class="text-warning">
                                <i class="fas fa-exclamation-triangle"></i> Grace: {{ $s->grace_period_ends_at->format('Y-m-d') }}
                            </small>
                        @endif
                        @if ($s->payment_failure_count > 0)
                            <br>
                            <small class="text-danger">
                                <i class="fas fa-times-circle"></i> Failures: {{ $s->payment_failure_count }}
                            </small>
                        @endif
                    </td>

                    <td>
                        @php
                            $metadata = $s->metadata ?? [];
                            $stripeSubId = $metadata['stripe_subscription_id'] ?? null;
                            $paymentMethod = $metadata['payment_method'] ?? null;
                        @endphp
                        
                        @if ($stripeSubId)
                            <small class="d-block">
                                <i class="fab fa-stripe text-primary"></i> 
                                <a href="https://dashboard.stripe.com/subscriptions/{{ $stripeSubId }}" 
                                   target="_blank" 
                                   class="text-decoration-none">
                                    {{ substr($stripeSubId, 0, 20) }}...
                                </a>
                            </small>
                        @endif
                        
                        @if ($paymentMethod)
                            <small class="d-block text-muted">
                                Payment: {{ ucfirst($paymentMethod) }}
                            </small>
                        @endif
                        
                        @if (isset($metadata['renewed_from_subscription_id']))
                            <small class="d-block text-info">
                                <i class="fas fa-sync"></i> Renewed from #{{ $metadata['renewed_from_subscription_id'] }}
                            </small>
                        @endif
                    </td>

                    <td>
                        <a href="{{ route('admin.subscriptions.edit', $s) }}" class="btn btn-sm btn-warning">Edit</a>
                        <form method="POST" action="{{ route('admin.subscriptions.destroy', $s) }}" class="d-inline">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-danger"
                                onclick="return confirm('Delete this subscription?')">Delete</button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
