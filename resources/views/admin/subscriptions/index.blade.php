@extends('layouts.admin')
@section('title', 'Subscriptions')

@section('content')
    <h2 class="mb-3">Subscriptions</h2>
    <a href="{{ route('admin.subscriptions.create') }}" class="btn btn-primary mb-3">Assign New Subscription</a>

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
                        @if ($today->lte($end))
                            <span class="badge bg-success">Active</span>
                        @else
                            <span class="badge bg-danger">Expired</span>
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
