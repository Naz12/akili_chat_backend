@extends('layouts.admin')
@section('title', 'Admin Dashboard')

@section('content')
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 text-primary fw-bold"><i class="fas fa-tachometer-alt me-2"></i>System Dashboard</h1>
        </div>

        <!-- Summary Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card border-start border-success shadow-sm h-100">
                    <div class="card-body">
                        <h6 class="text-muted">AI Engines</h6>
                        <h3 class="fw-bold">{{ $engineCount }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-start border-primary shadow-sm h-100">
                    <div class="card-body">
                        <h6 class="text-muted">Users</h6>
                        <h3 class="fw-bold">{{ $userCount }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-start border-info shadow-sm h-100">
                    <div class="card-body">
                        <h6 class="text-muted">Tokens Used (Today)</h6>
                        <h3 class="fw-bold">{{ number_format($tokensToday) }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-start border-warning shadow-sm h-100">
                    <div class="card-body">
                        <h6 class="text-muted">Active Plans</h6>
                        <h3 class="fw-bold">{{ $activePlans }}</h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Health & Status -->
        <div class="row g-3">
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header bg-light fw-bold">
                        <i class="fas fa-server me-1 text-success"></i> AI Engine Status
                    </div>
                    <div class="card-body">
                        <ul class="list-group">
                            @foreach ($engineStatuses as $engine)
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>{{ $engine['name'] }}</span>
                                    <span class="badge bg-{{ $engine['online'] ? 'success' : 'danger' }}">
                                        {{ $engine['online'] ? 'Online' : 'Offline' }}
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header bg-light fw-bold">
                        <i class="fas fa-clock me-1 text-info"></i> Latest Activity
                    </div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush">
                            @foreach ($recentLogs as $log)
                                <li class="list-group-item small">
                                    <i class="fas fa-terminal text-muted me-1"></i>
                                    {{ $log->message }}
                                    <span class="text-muted float-end">{{ $log->created_at->diffForHumans() }}</span>
                                </li>
                            @endforeach
                            @if (count($recentLogs) == 0)
                                <li class="list-group-item text-muted">No recent logs found.</li>
                            @endif
                        </ul>
                    </div>
                </div>
            </div>
        </div>

    </div>
@endsection
