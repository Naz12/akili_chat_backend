@extends('layouts.admin')
@section('title', 'Admin Details')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 fw-bold mb-1" style="color: #1e293b;">
                <i class="fas fa-user me-2" style="color: #6366f1;"></i>{{ $admin->name }}
            </h1>
            <p class="text-muted mb-0" style="font-size: 0.9rem;">Admin user details and activity</p>
        </div>
        <div>
            @if((auth()->user()->hasPermission('admins.edit') || auth()->user()->isSuperAdmin()) && auth()->id() !== $admin->id)
            <a href="{{ route('admin.admins.edit', $admin) }}" class="btn btn-primary">
                <i class="fas fa-edit me-2"></i>Edit
            </a>
            @endif
            <a href="{{ route('admin.admins.index') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back
            </a>
        </div>
    </div>

    <div class="row">
        <!-- Admin Information -->
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Information</h5>
                </div>
                <div class="card-body">
                    <table class="table table-borderless">
                        <tr>
                            <th width="40%">Name:</th>
                            <td>{{ $admin->name }}</td>
                        </tr>
                        <tr>
                            <th>Email:</th>
                            <td>{{ $admin->email }}</td>
                        </tr>
                        <tr>
                            <th>Status:</th>
                            <td>
                                @if($admin->is_active)
                                    <span class="badge bg-success">Active</span>
                                @else
                                    <span class="badge bg-secondary">Inactive</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>Roles:</th>
                            <td>
                                @foreach($admin->roles as $role)
                                    <span class="badge bg-primary me-1">{{ $role->name }}</span>
                                @endforeach
                                @if($admin->roles->isEmpty() && $admin->role === 'admin')
                                    <span class="badge bg-secondary">Legacy Admin</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>Last Login:</th>
                            <td>{{ $admin->last_login_at ? $admin->last_login_at->format('M d, Y H:i') : 'Never' }}</td>
                        </tr>
                        <tr>
                            <th>Created:</th>
                            <td>{{ $admin->created_at->format('M d, Y H:i') }}</td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Permissions -->
            <div class="card mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-key me-2"></i>Permissions</h5>
                </div>
                <div class="card-body">
                    @php
                        $allPermissions = $admin->getAllPermissions()->groupBy('module');
                    @endphp
                    @foreach($allPermissions as $module => $perms)
                        <h6 class="mt-3">{{ config('rbac.permissions.modules.' . $module, $module) }}</h6>
                        <div class="d-flex flex-wrap gap-2">
                            @foreach($perms as $perm)
                                <span class="badge bg-info">{{ $perm->name }}</span>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Activity Logs -->
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Activity</h5>
                </div>
                <div class="card-body">
                    @if($activityLogs->count() > 0)
                        <div class="list-group list-group-flush">
                            @foreach($activityLogs as $log)
                                <div class="list-group-item px-0">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <strong>{{ $log->action }}</strong>
                                            @if($log->description)
                                                <p class="mb-1 text-muted small">{{ $log->description }}</p>
                                            @endif
                                            <small class="text-muted">{{ $log->created_at->diffForHumans() }}</small>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-muted text-center py-4">No activity logs found</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

