@extends('layouts.admin')
@section('title', 'Subscription Plans')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1" style="color: #1e293b;">
                <i class="fas fa-layer-group me-2" style="color: #6366f1;"></i>Subscription Plans
            </h2>
            <p class="text-muted mb-0" style="font-size: 0.9rem;">Manage subscription tiers and pricing</p>
        </div>
        <div class="d-flex gap-2">
            <x-export-button 
                route="{{ route('admin.plans.export') }}"
                title="Export Plans"
                :currentCount="$plans->count()"
                :totalCount="$plans->count()"
                :hasFilters="false"
            />
            <a href="{{ route('admin.plans.create') }}" class="btn btn-primary">
                <i class="fas fa-plus-circle me-2"></i> Add Plan
            </a>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Price</th>
                            <th>Tokens / Day Limit</th>
                            <th>Engine</th>
                            <th>Region</th>
                            <th>Currency</th>
                            <th>Ads</th>
                            <th>Subscribers</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($plans as $plan)
                            <tr>
                                <td>
                                    <strong style="color: #1e293b;">{{ $plan->name }}</strong>
                                    @if ($plan->is_default)
                                        <span class="badge bg-primary ms-2">Default</span>
                                    @elseif ($plan->monthly_price == 0)
                                        <span class="badge bg-success ms-2">Free</span>
                                    @else
                                        <span class="badge bg-warning text-dark ms-2">Paid</span>
                                    @endif
                                </td>

                                <td>
                                    <strong style="color: #6366f1; font-size: 1.1rem;">{{ $plan->monthly_price }}</strong> 
                                    <small class="text-muted">{{ $plan->currency ?? 'ETB' }}</small>
                                </td>

                                <td>
                                    <div>
                                        <span class="d-block fw-semibold" style="color: #1e293b;">
                                            <i class="fas fa-coins me-1" style="font-size: 0.8rem; color: #6366f1;"></i>
                                            {{ number_format($plan->max_tokens) }}
                                        </span>
                                        <span class="text-muted small">
                                            <i class="fas fa-calendar-day me-1" style="font-size: 0.7rem;"></i>
                                            {{ $plan->daily_message_limit }} msgs/day
                                        </span>
                                    </div>
                                </td>

                                <td>
                                    @if ($plan->engine)
                                        <div>
                                            <span class="fw-semibold d-block" style="color: #1e293b;">{{ $plan->engine->name }}</span>
                                            <small class="text-muted">{{ $plan->engine->provider }}</small>
                                        </div>
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
                                        <span class="badge bg-success"><i class="fas fa-check me-1"></i>Enabled</span>
                                    @else
                                        <span class="badge bg-secondary"><i class="fas fa-times me-1"></i>Disabled</span>
                                    @endif
                                </td>

                                <td>
                                    <span class="badge" style="background-color: #f1f5f9; color: #1e293b; font-size: 0.9rem;">
                                        <i class="fas fa-users me-1" style="font-size: 0.8rem;"></i>
                                        {{ $plan->subscriptions_count ?? 0 }}
                                    </span>
                                </td>

                                <td class="text-center">
                                    <a href="{{ route('admin.plans.edit', $plan) }}" class="btn btn-sm btn-warning me-1">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <form method="POST" action="{{ route('admin.plans.destroy', $plan) }}" class="d-inline"
                                        onsubmit="return confirm('Are you sure you want to delete this plan?')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-danger" type="submit">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
