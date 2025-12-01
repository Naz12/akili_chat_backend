@extends('layouts.admin')
@section('title', 'Dashboard')

@section('content')
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 fw-bold mb-1" style="color: #1e293b;">
                    <i class="fas fa-tachometer-alt me-2" style="color: #6366f1;"></i>System Dashboard
                </h1>
                <p class="text-muted mb-0" style="font-size: 0.9rem;">Overview of system metrics and performance</p>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card h-100 border-0" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="text-white-50 mb-2" style="font-size: 0.875rem; font-weight: 500;">AI Engines</h6>
                                <h2 class="fw-bold mb-0">{{ $engineCount }}</h2>
                            </div>
                            <div class="p-3 rounded-circle" style="background: rgba(255,255,255,0.2);">
                                <i class="fas fa-brain" style="font-size: 1.5rem;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card h-100 border-0" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="text-white-50 mb-2" style="font-size: 0.875rem; font-weight: 500;">Users</h6>
                                <h2 class="fw-bold mb-0">{{ $userCount }}</h2>
                            </div>
                            <div class="p-3 rounded-circle" style="background: rgba(255,255,255,0.2);">
                                <i class="fas fa-users" style="font-size: 1.5rem;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card h-100 border-0" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="text-white-50 mb-2" style="font-size: 0.875rem; font-weight: 500;">Tokens (Today)</h6>
                                <h2 class="fw-bold mb-0">{{ number_format($tokensToday) }}</h2>
                            </div>
                            <div class="p-3 rounded-circle" style="background: rgba(255,255,255,0.2);">
                                <i class="fas fa-coins" style="font-size: 1.5rem;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card h-100 border-0" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="text-white-50 mb-2" style="font-size: 0.875rem; font-weight: 500;">Active Plans</h6>
                                <h2 class="fw-bold mb-0">{{ $activePlans }}</h2>
                            </div>
                            <div class="p-3 rounded-circle" style="background: rgba(255,255,255,0.2);">
                                <i class="fas fa-layer-group" style="font-size: 1.5rem;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Additional Statistics Row -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card h-100 border-0" style="background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%); color: white;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="text-white-50 mb-2" style="font-size: 0.875rem; font-weight: 500;">Pending Bills</h6>
                                <h2 class="fw-bold mb-0">{{ $pendingBills ?? 0 }}</h2>
                                @if (isset($overdueBills) && $overdueBills > 0)
                                    <small class="text-white-50">({{ $overdueBills }} overdue)</small>
                                @endif
                            </div>
                            <div class="p-3 rounded-circle" style="background: rgba(255,255,255,0.2);">
                                <i class="fas fa-file-invoice" style="font-size: 1.5rem;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card h-100 border-0" style="background: linear-gradient(135deg, #ffa726 0%, #fb8c00 100%); color: white;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="text-white-50 mb-2" style="font-size: 0.875rem; font-weight: 500;">Grace Period</h6>
                                <h2 class="fw-bold mb-0">{{ $gracePeriodCount ?? 0 }}</h2>
                            </div>
                            <div class="p-3 rounded-circle" style="background: rgba(255,255,255,0.2);">
                                <i class="fas fa-clock" style="font-size: 1.5rem;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card h-100 border-0" style="background: linear-gradient(135deg, #ef5350 0%, #e53935 100%); color: white;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="text-white-50 mb-2" style="font-size: 0.875rem; font-weight: 500;">Payment Failures</h6>
                                <h2 class="fw-bold mb-0">{{ $paymentFailures ?? 0 }}</h2>
                            </div>
                            <div class="p-3 rounded-circle" style="background: rgba(255,255,255,0.2);">
                                <i class="fas fa-exclamation-triangle" style="font-size: 1.5rem;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card h-100 border-0" style="background: linear-gradient(135deg, #42a5f5 0%, #1e88e5 100%); color: white;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="text-white-50 mb-2" style="font-size: 0.875rem; font-weight: 500;">Today Visitors</h6>
                                <h2 class="fw-bold mb-0">{{ $todayVisitors ?? 0 }}</h2>
                                <small class="text-white-50">Total: {{ $totalVisitors ?? 0 }}</small>
                            </div>
                            <div class="p-3 rounded-circle" style="background: rgba(255,255,255,0.2);">
                                <i class="fas fa-users" style="font-size: 1.5rem;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Alerts Section -->
        @if (isset($overdueBills) && $overdueBills > 0)
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>
            <strong>Alert:</strong> {{ $overdueBills }} bill(s) are overdue. 
            <a href="{{ route('admin.billing.index', ['tab' => 'bills', 'status' => 'overdue']) }}" class="alert-link">View overdue bills</a>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        @endif

        @if (isset($gracePeriodCount) && $gracePeriodCount > 0)
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <i class="fas fa-clock me-2"></i>
            <strong>Alert:</strong> {{ $gracePeriodCount }} subscription(s) in grace period. 
            <a href="{{ route('admin.subscriptions.gracePeriod') }}" class="alert-link">Manage grace period</a>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        @endif

        @if (isset($expiringSubscriptions) && $expiringSubscriptions > 0)
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <i class="fas fa-calendar-times me-2"></i>
            <strong>Info:</strong> {{ $expiringSubscriptions }} subscription(s) expiring in the next 7 days. 
            <a href="{{ route('admin.subscriptions.expiring') }}" class="alert-link">View expiring subscriptions</a>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        @endif

        <!-- System Health & Status -->
        <div class="row g-4">
            <div class="col-md-6">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white border-bottom" style="padding: 1.25rem;">
                        <h5 class="mb-0 fw-semibold" style="color: #1e293b;">
                            <i class="fas fa-server me-2" style="color: #10b981;"></i> AI Engine Status
                        </h5>
                    </div>
                    <div class="card-body" style="padding: 1.25rem;">
                        <ul class="list-group list-group-flush">
                            @foreach ($engineStatuses as $engine)
                                <li class="list-group-item d-flex justify-content-between align-items-center px-0 border-0 py-3">
                                    <div class="d-flex align-items-center">
                                        <div class="p-2 rounded me-3" style="background-color: {{ $engine['online'] ? '#d1fae5' : '#fee2e2' }};">
                                            <i class="fas fa-{{ $engine['online'] ? 'check-circle' : 'exclamation-circle' }}" 
                                               style="color: {{ $engine['online'] ? '#10b981' : '#ef4444' }}; font-size: 1.25rem;"></i>
                                        </div>
                                        <div>
                                            <span class="fw-semibold" style="color: #1e293b;">{{ $engine['name'] }}</span>
                                            <small class="d-block text-muted" style="font-size: 0.8rem;">Last checked recently</small>
                                        </div>
                                    </div>
                                    <span class="badge {{ $engine['online'] ? 'bg-success' : 'bg-danger' }}" 
                                          style="padding: 6px 12px; border-radius: 20px;">
                                        {{ $engine['online'] ? 'Online' : 'Offline' }}
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white border-bottom" style="padding: 1.25rem;">
                        <h5 class="mb-0 fw-semibold" style="color: #1e293b;">
                            <i class="fas fa-clock me-2" style="color: #3b82f6;"></i> Latest Activity
                        </h5>
                    </div>
                    <div class="card-body" style="padding: 1.25rem;">
                        <ul class="list-group list-group-flush">
                            @foreach ($recentLogs as $log)
                                <li class="list-group-item px-0 border-0 py-3">
                                    <div class="d-flex">
                                        <div class="p-2 rounded me-3" style="background-color: #f1f5f9; height: fit-content;">
                                            <i class="fas fa-terminal" style="color: #6366f1; font-size: 0.875rem;"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <p class="mb-1" style="font-size: 0.875rem; color: #1e293b;">{{ $log->message }}</p>
                                            <span class="text-muted" style="font-size: 0.75rem;">
                                                <i class="fas fa-clock me-1" style="font-size: 0.7rem;"></i>
                                                {{ $log->created_at->diffForHumans() }}
                                            </span>
                                        </div>
                                    </div>
                                </li>
                            @endforeach
                            @if (count($recentLogs) == 0)
                                <li class="list-group-item px-0 border-0 text-center py-5">
                                    <i class="fas fa-inbox" style="font-size: 3rem; color: #cbd5e1;"></i>
                                    <p class="text-muted mt-3 mb-0">No recent logs found.</p>
                                </li>
                            @endif
                        </ul>
                    </div>
                </div>
            </div>
        </div>

    </div>
@endsection
