@extends('layouts.admin')
@section('title', 'Audit Logs')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 fw-bold mb-1" style="color: #1e293b;">
                <i class="fas fa-history me-2" style="color: #6366f1;"></i>Audit Logs
            </h1>
            <p class="text-muted mb-0" style="font-size: 0.9rem;">Track all admin operations and changes</p>
        </div>
        <x-export-button 
            route="{{ route('admin.audit-logs.export') }}"
            title="Export Audit Logs"
            :currentCount="$logs->count()"
            :totalCount="\App\Models\AdminActivityLog::count()"
            :hasFilters="request()->hasAny(['admin_id', 'action', 'date_from', 'date_to', 'model_type'])"
        />
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.audit-logs.index') }}" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Admin</label>
                    <select name="admin_id" class="form-select">
                        <option value="">All Admins</option>
                        @foreach($admins as $admin)
                            <option value="{{ $admin->id }}" {{ request('admin_id') == $admin->id ? 'selected' : '' }}>
                                {{ $admin->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Action</label>
                    <input type="text" name="action" class="form-control" value="{{ request('action') }}" placeholder="Search action">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Date From</label>
                    <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Date To</label>
                    <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-filter me-1"></i>Filter
                    </button>
                    <a href="{{ route('admin.audit-logs.index') }}" class="btn btn-secondary">
                        <i class="fas fa-times me-1"></i>Clear
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Audit Logs Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Admin</th>
                            <th>Action</th>
                            <th>Model</th>
                            <th>Description</th>
                            <th>IP Address</th>
                            <th>Timestamp</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($logs as $log)
                            <tr>
                                <td>
                                    <strong>{{ $log->admin->name }}</strong><br>
                                    <small class="text-muted">{{ $log->admin->email }}</small>
                                </td>
                                <td>
                                    <span class="badge bg-primary">{{ $log->action }}</span>
                                </td>
                                <td>
                                    @if($log->model_type)
                                        <small>{{ class_basename($log->model_type) }}</small>
                                        @if($log->model_id)
                                            <br><small class="text-muted">ID: {{ $log->model_id }}</small>
                                        @endif
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    {{ Str::limit($log->description ?? '-', 50) }}
                                </td>
                                <td>
                                    <small>{{ $log->ip_address ?? '-' }}</small>
                                </td>
                                <td>
                                    {{ $log->created_at->format('M d, Y H:i') }}<br>
                                    <small class="text-muted">{{ $log->created_at->diffForHumans() }}</small>
                                </td>
                                <td>
                                    <a href="{{ route('admin.audit-logs.show', $log) }}" class="btn btn-sm btn-info" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    <i class="fas fa-history fa-2x mb-2"></i>
                                    <p>No audit logs found</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="mt-4">
                {{ $logs->links() }}
            </div>
        </div>
    </div>
</div>
@endsection

