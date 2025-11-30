@extends('layouts.admin')
@section('title', 'Payment Statistics')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold text-primary"><i class="fas fa-chart-bar me-2"></i>Payment Statistics</h2>
        <a href="{{ route('admin.payments.index') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Payments
        </a>
    </div>

    <div class="row mb-4">
        <!-- Total Payments -->
        <div class="col-md-3">
            <div class="card shadow-sm border-primary">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Total Payments</h6>
                            <h3 class="mb-0 text-primary">{{ number_format($stats['total_payments']) }}</h3>
                        </div>
                        <div class="text-primary" style="font-size: 2.5rem;">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Successful Payments -->
        <div class="col-md-3">
            <div class="card shadow-sm border-success">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Successful</h6>
                            <h3 class="mb-0 text-success">{{ number_format($stats['successful_payments']) }}</h3>
                        </div>
                        <div class="text-success" style="font-size: 2.5rem;">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pending Payments -->
        <div class="col-md-3">
            <div class="card shadow-sm border-warning">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Pending</h6>
                            <h3 class="mb-0 text-warning">{{ number_format($stats['pending_payments']) }}</h3>
                        </div>
                        <div class="text-warning" style="font-size: 2.5rem;">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Failed Payments -->
        <div class="col-md-3">
            <div class="card shadow-sm border-danger">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Failed</h6>
                            <h3 class="mb-0 text-danger">{{ number_format($stats['failed_payments']) }}</h3>
                        </div>
                        <div class="text-danger" style="font-size: 2.5rem;">
                            <i class="fas fa-times-circle"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <!-- Success Rate -->
        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Success Rate</h5>
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <div class="progress" style="height: 30px;">
                                <div class="progress-bar bg-success" 
                                     role="progressbar" 
                                     style="width: {{ $stats['success_rate'] }}%"
                                     aria-valuenow="{{ $stats['success_rate'] }}" 
                                     aria-valuemin="0" 
                                     aria-valuemax="100">
                                    {{ $stats['success_rate'] }}%
                                </div>
                            </div>
                        </div>
                    </div>
                    <p class="text-muted mt-2 mb-0">
                        {{ $stats['successful_payments'] }} out of {{ $stats['total_payments'] }} payments
                    </p>
                </div>
            </div>
        </div>

        <!-- Total Revenue -->
        <div class="col-md-4">
            <div class="card shadow-sm border-primary">
                <div class="card-body">
                    <h5 class="card-title">Total Revenue</h5>
                    <h2 class="text-primary mb-0">{{ number_format($stats['total_revenue'], 2) }}</h2>
                    <p class="text-muted mb-0">From successful payments</p>
                </div>
            </div>
        </div>

        <!-- Revenue by Provider -->
        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Revenue by Provider</h5>
                    <div class="mb-2">
                        <div class="d-flex justify-content-between mb-1">
                            <span>Stripe:</span>
                            <strong>{{ number_format($stats['stripe_revenue'], 2) }}</strong>
                        </div>
                        <div class="progress mb-3" style="height: 8px;">
                            <div class="progress-bar bg-info" 
                                 style="width: {{ $stats['total_revenue'] > 0 ? ($stats['stripe_revenue'] / $stats['total_revenue']) * 100 : 0 }}%">
                            </div>
                        </div>
                    </div>
                    <div>
                        <div class="d-flex justify-content-between mb-1">
                            <span>Chapa:</span>
                            <strong>{{ number_format($stats['chapa_revenue'], 2) }}</strong>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar bg-warning" 
                                 style="width: {{ $stats['total_revenue'] > 0 ? ($stats['chapa_revenue'] / $stats['total_revenue']) * 100 : 0 }}%">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Links -->
    <div class="card shadow-sm">
        <div class="card-header bg-light">
            <h5 class="mb-0">Quick Actions</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <a href="{{ route('admin.payments.index', ['status' => 'success']) }}" 
                       class="btn btn-outline-success w-100">
                        <i class="fas fa-check-circle me-2"></i>View Successful
                    </a>
                </div>
                <div class="col-md-3">
                    <a href="{{ route('admin.payments.index', ['status' => 'failed']) }}" 
                       class="btn btn-outline-danger w-100">
                        <i class="fas fa-times-circle me-2"></i>View Failed
                    </a>
                </div>
                <div class="col-md-3">
                    <a href="{{ route('admin.payments.index', ['status' => 'pending']) }}" 
                       class="btn btn-outline-warning w-100">
                        <i class="fas fa-clock me-2"></i>View Pending
                    </a>
                </div>
                <div class="col-md-3">
                    <a href="{{ route('admin.webhooks.index') }}" 
                       class="btn btn-outline-info w-100">
                        <i class="fas fa-plug me-2"></i>View Webhooks
                    </a>
                </div>
            </div>
        </div>
    </div>
@endsection

