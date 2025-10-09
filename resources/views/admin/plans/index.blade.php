@extends('layouts.admin')

@section('content')
    <h2>Subscription Plans</h2>
    <a href="{{ route('admin.plans.create') }}" class="btn btn-primary mb-3">Add Plan</a>

    <table class="table table-bordered table-striped">
        <thead class="table-dark">
            <tr>
                <th>Name</th>
                <th>Price</th>
                <th>Tokens / Day Limit</th>
                <th>Engine</th>
                <th>Region</th>
                <th>Currency</th>
                <th>Ads</th>
                <th>Subscribers</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($plans as $plan)
                <tr>
                    <td>
                        {{ $plan->name }}
                        @if ($plan->is_default)
                            <span class="badge bg-primary ms-2">Default</span>
                        @elseif ($plan->monthly_price == 0)
                            <span class="badge bg-success ms-2">Free</span>
                        @else
                            <span class="badge bg-warning text-dark ms-2">Paid</span>
                        @endif
                    </td>

                    <td>
                        <strong>{{ $plan->monthly_price }}</strong> {{ $plan->currency ?? 'ETB' }}
                    </td>

                    <td>
                        <span class="d-block">Max: {{ $plan->max_tokens }}</span>
                        <span class="text-muted small">Daily: {{ $plan->daily_message_limit }}</span>
                    </td>

                    <td>
                        @if ($plan->engine)
                            <span class="fw-bold">{{ $plan->engine->name }}</span>
                            <br>
                            <small class="text-muted">{{ $plan->engine->provider }}</small>
                        @else
                            <span class="text-muted">N/A</span>
                        @endif
                    </td>

                    <td>
                        <span class="badge bg-{{ $plan->region === 'intl' ? 'info' : 'secondary' }}">
                            {{ ucfirst($plan->region) }}
                        </span>
                    </td>

                    <td>
                        {{ config('currencies')[$plan->currency] ?? $plan->currency }}
                    </td>

                    <td>
                        @if ($plan->ads_enabled)
                            <span class="badge bg-success">Enabled</span>
                        @else
                            <span class="badge bg-secondary">Disabled</span>
                        @endif
                    </td>

                    <td>
                        {{ $plan->subscriptions_count ?? 0 }}
                    </td>

                    <td>
                        <a href="{{ route('admin.plans.edit', $plan) }}" class="btn btn-sm btn-warning">Edit</a>
                        <form method="POST" action="{{ route('admin.plans.destroy', $plan) }}" class="d-inline">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-danger" onclick="return confirm('Delete?')">Delete</button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
