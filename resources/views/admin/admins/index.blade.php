@extends('layouts.admin')
@section('title', 'Admin Users')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 fw-bold mb-1" style="color: #1e293b;">
                <i class="fas fa-users-cog me-2" style="color: #6366f1;"></i>Admin Users
            </h1>
            <p class="text-muted mb-0" style="font-size: 0.9rem;">Manage admin users, roles, and permissions</p>
        </div>
        <div class="d-flex gap-2">
            <x-export-button 
                route="{{ route('admin.admins.export') }}"
                title="Export Admin Users"
                :currentCount="$admins->count()"
                :totalCount="\App\Models\User::whereHas('roles')->orWhere('role', 'admin')->count()"
                :hasFilters="request()->hasAny(['search', 'role', 'status'])"
            />
            @if(auth()->user()->hasPermission('admins.create') || auth()->user()->isSuperAdmin())
            <a href="{{ route('admin.admins.create') }}" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Create Admin
            </a>
            @endif
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.admins.index') }}" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" value="{{ request('search') }}" placeholder="Name or email">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Role</label>
                    <select name="role" class="form-select">
                        <option value="">All Roles</option>
                        @foreach($roles as $role)
                            <option value="{{ $role->slug }}" {{ request('role') === $role->slug ? 'selected' : '' }}>
                                {{ $role->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-filter me-1"></i>Filter
                    </button>
                    <a href="{{ route('admin.admins.index') }}" class="btn btn-secondary">
                        <i class="fas fa-times me-1"></i>Clear
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Admin Users Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Roles</th>
                            <th>Status</th>
                            <th>Last Login</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($admins as $admin)
                            <tr>
                                <td>
                                    <strong>{{ $admin->name }}</strong>
                                    @if($admin->isSuperAdmin())
                                        <span class="badge bg-danger ms-1">Super Admin</span>
                                    @endif
                                </td>
                                <td>{{ $admin->email }}</td>
                                <td>
                                    @foreach($admin->roles as $role)
                                        <span class="badge bg-primary me-1">{{ $role->name }}</span>
                                    @endforeach
                                    @if($admin->roles->isEmpty() && $admin->role === 'admin')
                                        <span class="badge bg-secondary">Legacy Admin</span>
                                    @endif
                                </td>
                                <td>
                                    @if($admin->is_active)
                                        <span class="badge bg-success">Active</span>
                                    @else
                                        <span class="badge bg-secondary">Inactive</span>
                                    @endif
                                </td>
                                <td>
                                    {{ $admin->last_login_at ? $admin->last_login_at->diffForHumans() : 'Never' }}
                                </td>
                                <td>{{ $admin->created_at->format('M d, Y') }}</td>
                                <td>
                                    <div class="btn-group" role="group">
                                        @if(auth()->user()->hasPermission('admins.view') || auth()->user()->isSuperAdmin())
                                        <a href="{{ route('admin.admins.show', $admin) }}" class="btn btn-sm btn-info" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        @endif
                                        @if((auth()->user()->hasPermission('admins.edit') || auth()->user()->isSuperAdmin()) && auth()->id() !== $admin->id)
                                        <a href="{{ route('admin.admins.edit', $admin) }}" class="btn btn-sm btn-primary" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        @endif
                                        @if((auth()->user()->hasPermission('admins.delete') || auth()->user()->isSuperAdmin()) && auth()->id() !== $admin->id)
                                            @if($admin->is_active)
                                                <form action="{{ route('admin.admins.deactivate', $admin) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to deactivate this admin?');">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-warning" title="Deactivate">
                                                        <i class="fas fa-ban"></i>
                                                    </button>
                                                </form>
                                            @else
                                                <form action="{{ route('admin.admins.activate', $admin) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-success" title="Activate">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                </form>
                                            @endif
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    <i class="fas fa-users fa-2x mb-2"></i>
                                    <p>No admin users found</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="mt-4">
                {{ $admins->links() }}
            </div>
        </div>
    </div>
</div>
@endsection

