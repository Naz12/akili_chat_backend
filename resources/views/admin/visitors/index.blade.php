@extends('layouts.admin')
@section('title', 'Visitors Analytics')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold text-primary">
            <i class="fas fa-users me-2"></i>Visitors Analytics
        </h2>
    </div>

    <!-- Statistics Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="text-muted">Total Visitors</h5>
                    <h3 class="mb-0">{{ number_format($stats['total_visitors']) }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="text-muted">Unique Visitors</h5>
                    <h3 class="mb-0">{{ number_format($stats['unique_visitors']) }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="text-success">Logged In</h5>
                    <h3 class="mb-0 text-success">{{ number_format($stats['logged_in_count']) }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="text-info">Guests</h5>
                    <h3 class="mb-0 text-info">{{ number_format($stats['guest_count']) }}</h3>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="text-muted">Today</h5>
                    <h3 class="mb-0">{{ number_format($stats['today_visitors']) }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="text-muted">This Week</h5>
                    <h3 class="mb-0">{{ number_format($stats['this_week_visitors']) }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="text-muted">This Month</h5>
                    <h3 class="mb-0">{{ number_format($stats['this_month_visitors']) }}</h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.visitors.index') }}" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Country</label>
                    <input type="text" name="country" class="form-control" value="{{ request('country') }}" placeholder="Country code">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Logged In</label>
                    <select name="is_logged_in" class="form-select">
                        <option value="">All</option>
                        <option value="1" {{ request('is_logged_in') === '1' ? 'selected' : '' }}>Logged In</option>
                        <option value="0" {{ request('is_logged_in') === '0' ? 'selected' : '' }}>Guests</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Device Type</label>
                    <select name="device_type" class="form-select">
                        <option value="">All</option>
                        <option value="mobile" {{ request('device_type') === 'mobile' ? 'selected' : '' }}>Mobile</option>
                        <option value="tablet" {{ request('device_type') === 'tablet' ? 'selected' : '' }}>Tablet</option>
                        <option value="desktop" {{ request('device_type') === 'desktop' ? 'selected' : '' }}>Desktop</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Date From</label>
                    <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Date To</label>
                    <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
                </div>
                <div class="col-md-12">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                    <a href="{{ route('admin.visitors.index') }}" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Clear
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="row">
        <!-- Visitors List -->
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="mb-0">Recent Visitors</h5>
                </div>
                <div class="card-body p-0">
                    @if ($visitors->count())
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>User</th>
                                        <th>Country</th>
                                        <th>Device</th>
                                        <th>Browser</th>
                                        <th>Page Views</th>
                                        <th>Last Visit</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($visitors as $visitor)
                                        <tr>
                                            <td>
                                                @if ($visitor->is_logged_in && $visitor->user)
                                                    <strong>{{ $visitor->user->name }}</strong>
                                                    <br>
                                                    <small class="text-muted">{{ $visitor->user->email }}</small>
                                                @else
                                                    <span class="text-muted">Guest</span>
                                                @endif
                                                <br>
                                                <small class="text-muted">{{ $visitor->ip_address }}</small>
                                            </td>
                                            <td>
                                                @if ($visitor->country)
                                                    <span class="badge bg-info">{{ $visitor->country }}</span>
                                                    <br>
                                                    <small class="text-muted">{{ $visitor->country_name }}</small>
                                                @else
                                                    <span class="text-muted">Unknown</span>
                                                @endif
                                            </td>
                                            <td>
                                                <i class="{{ $visitor->device_icon }}"></i>
                                                {{ ucfirst($visitor->device_type) }}
                                            </td>
                                            <td>
                                                {{ $visitor->browser }}
                                                @if ($visitor->browser_version)
                                                    <small class="text-muted">{{ $visitor->browser_version }}</small>
                                                @endif
                                            </td>
                                            <td>{{ $visitor->page_views }}</td>
                                            <td>
                                                {{ $visitor->last_visit_at->diffForHumans() }}
                                                <br>
                                                <small class="text-muted">{{ $visitor->last_visit_at->format('Y-m-d H:i') }}</small>
                                            </td>
                                            <td>
                                                <a href="{{ route('admin.visitors.show', $visitor) }}" class="btn btn-sm btn-outline-primary">
                                                    View
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="card-footer">
                            {{ $visitors->links() }}
                        </div>
                    @else
                        <div class="alert alert-info m-3">
                            <i class="fas fa-info-circle me-1"></i> No visitors found.
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Statistics Sidebar -->
        <div class="col-md-4">
            <!-- Top Countries -->
            <div class="card shadow-sm mb-3">
                <div class="card-header">
                    <h5 class="mb-0">Top Countries</h5>
                </div>
                <div class="card-body">
                    @if ($countryStats->count())
                        <ul class="list-unstyled mb-0">
                            @foreach ($countryStats as $stat)
                                <li class="d-flex justify-content-between align-items-center mb-2">
                                    <span>
                                        <strong>{{ $stat->country_name ?? $stat->country }}</strong>
                                        <small class="text-muted">({{ $stat->country }})</small>
                                    </span>
                                    <span class="badge bg-primary">{{ $stat->count }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-muted mb-0">No data available</p>
                    @endif
                </div>
            </div>

            <!-- Device Statistics -->
            <div class="card shadow-sm mb-3">
                <div class="card-header">
                    <h5 class="mb-0">Device Types</h5>
                </div>
                <div class="card-body">
                    @if ($deviceStats->count())
                        <ul class="list-unstyled mb-0">
                            @foreach ($deviceStats as $stat)
                                <li class="d-flex justify-content-between align-items-center mb-2">
                                    <span>{{ ucfirst($stat->device_type) }}</span>
                                    <span class="badge bg-info">{{ $stat->count }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-muted mb-0">No data available</p>
                    @endif
                </div>
            </div>

            <!-- Browser Statistics -->
            <div class="card shadow-sm mb-3">
                <div class="card-header">
                    <h5 class="mb-0">Top Browsers</h5>
                </div>
                <div class="card-body">
                    @if ($browserStats->count())
                        <ul class="list-unstyled mb-0">
                            @foreach ($browserStats as $stat)
                                <li class="d-flex justify-content-between align-items-center mb-2">
                                    <span>{{ $stat->browser }}</span>
                                    <span class="badge bg-success">{{ $stat->count }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-muted mb-0">No data available</p>
                    @endif
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="mb-0">Recent Activity (24h)</h5>
                </div>
                <div class="card-body">
                    @if ($recentActivity->count())
                        <ul class="list-unstyled mb-0">
                            @foreach ($recentActivity as $activity)
                                <li class="mb-2">
                                    <small class="text-muted">
                                        {{ $activity->last_activity_at->diffForHumans() }}
                                    </small>
                                    <br>
                                    <span>
                                        @if ($activity->is_logged_in && $activity->user)
                                            {{ $activity->user->name }}
                                        @else
                                            Guest
                                        @endif
                                    </span>
                                    <span class="badge bg-secondary ms-2">{{ $activity->country ?? 'Unknown' }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-muted mb-0">No recent activity</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

