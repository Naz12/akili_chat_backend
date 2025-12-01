@extends('layouts.admin')
@section('title', 'Stripe Subscriptions')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold text-primary">
            <i class="fab fa-stripe me-2"></i>Stripe Subscriptions
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
                        <th>Stripe Subscription ID</th>
                        <th>Auto Renew</th>
                        <th>End Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($subscriptions as $sub)
                        @php
                            $metadata = $sub->metadata ?? [];
                            $stripeSubId = $metadata['stripe_subscription_id'] ?? null;
                        @endphp
                        <tr>
                            <td>
                                {{ $sub->user->name }}
                                <br>
                                <small class="text-muted">{{ $sub->user->email }}</small>
                            </td>
                            <td>{{ $sub->plan->name }}</td>
                            <td>
                                @if ($stripeSubId)
                                    <a href="https://dashboard.stripe.com/subscriptions/{{ $stripeSubId }}" 
                                       target="_blank" 
                                       class="text-decoration-none">
                                        <i class="fab fa-stripe"></i> {{ $stripeSubId }}
                                        <i class="fas fa-external-link-alt ms-1"></i>
                                    </a>
                                @else
                                    <span class="text-muted">N/A</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-{{ $sub->auto_renew ? 'success' : 'secondary' }}">
                                    {{ $sub->auto_renew ? 'Yes' : 'No' }}
                                </span>
                            </td>
                            <td>{{ $sub->end_date->format('Y-m-d') }}</td>
                            <td>
                                @if ($sub->is_active)
                                    <span class="badge bg-success">Active</span>
                                @else
                                    <span class="badge bg-secondary">Inactive</span>
                                @endif
                            </td>
                            <td>
                                <a href="https://dashboard.stripe.com/subscriptions/{{ $stripeSubId }}" 
                                   target="_blank" 
                                   class="btn btn-sm btn-outline-primary">
                                    <i class="fab fa-stripe"></i> View in Stripe
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
            <i class="fas fa-info-circle me-1"></i> No Stripe subscriptions found.
        </div>
    @endif
@endsection

