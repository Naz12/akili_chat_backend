@extends('layouts.admin')
@section('title', 'Edit Admin')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 fw-bold mb-1" style="color: #1e293b;">
                <i class="fas fa-user-edit me-2" style="color: #6366f1;"></i>Edit Admin User
            </h1>
            <p class="text-muted mb-0" style="font-size: 0.9rem;">Update admin user information, roles, and permissions</p>
        </div>
        <a href="{{ route('admin.admins.index') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to List
        </a>
    </div>

    @if(auth()->id() === $admin->id)
    <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <strong>Note:</strong> You cannot change your own roles. Other fields can be updated.
    </div>
    @endif

    <form action="{{ route('admin.admins.update', $admin) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="row">
            <!-- Basic Information -->
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Basic Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $admin->name) }}" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email', $admin->email) }}" required>
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control @error('password') is-invalid @enderror" id="password" name="password" placeholder="Leave blank to keep current password">
                            @error('password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">Leave blank to keep current password</small>
                        </div>

                        <div class="mb-3">
                            <label for="password_confirmation" class="form-label">Confirm Password</label>
                            <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" placeholder="Leave blank to keep current password">
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" {{ old('is_active', $admin->is_active) ? 'checked' : '' }} {{ auth()->id() === $admin->id ? 'disabled' : '' }}>
                                <label class="form-check-label" for="is_active">
                                    Active
                                </label>
                                @if(auth()->id() === $admin->id)
                                    <small class="text-muted d-block">You cannot change your own status</small>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Role Assignment -->
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-user-tag me-2"></i>Role Assignment</h5>
                    </div>
                    <div class="card-body">
                        @if(!$canChangeRole)
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>You cannot change your own roles.
                            </div>
                        @endif
                        <p class="text-muted small mb-3">Selecting a role grants all permissions for that role.</p>
                        @foreach($roles as $role)
                            <div class="form-check mb-2">
                                <input class="form-check-input role-checkbox" type="checkbox" name="roles[]" value="{{ $role->id }}" id="role_{{ $role->id }}" 
                                    {{ in_array($role->id, old('roles', $admin->roles->pluck('id')->toArray())) ? 'checked' : '' }}
                                    {{ !$canChangeRole ? 'disabled' : '' }}>
                                <label class="form-check-label" for="role_{{ $role->id }}">
                                    <strong>{{ $role->name }}</strong>
                                    @if($role->is_super_admin)
                                        <span class="badge bg-danger ms-1">Super Admin</span>
                                    @endif
                                    <br>
                                    <small class="text-muted">{{ $role->description }}</small>
                                </label>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <!-- Custom Permissions -->
        <div class="card mb-4">
            <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-key me-2"></i>Custom Permissions</h5>
                <button type="button" class="btn btn-sm btn-light" onclick="selectAllPermissions()">
                    <i class="fas fa-check-double me-1"></i>Select All
                </button>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">Select individual permissions.</p>
                
                @foreach($modules as $moduleSlug => $moduleName)
                    @if(isset($permissions[$moduleSlug]) && $permissions[$moduleSlug]->count() > 0)
                        <div class="permission-module mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="mb-0">
                                    <i class="fas fa-folder me-2"></i>{{ $moduleName }}
                                </h6>
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="selectModulePermissions('{{ $moduleSlug }}')">
                                    Select All
                                </button>
                            </div>
                            <div class="row">
                                @foreach($permissions[$moduleSlug] as $permission)
                                    @php
                                        $hasViaRole = $admin->roles->flatMap->permissions->contains('id', $permission->id);
                                        $hasDirect = $admin->permissions->contains('id', $permission->id);
                                        $isChecked = in_array($permission->id, old('permissions', $admin->permissions->pluck('id')->toArray()));
                                    @endphp
                                    <div class="col-md-4 mb-2">
                                        <div class="form-check">
                                            <input class="form-check-input permission-checkbox" type="checkbox" name="permissions[]" value="{{ $permission->id }}" id="perm_{{ $permission->id }}" {{ $isChecked ? 'checked' : '' }}>
                                            <label class="form-check-label" for="perm_{{ $permission->id }}">
                                                {{ $permission->name }}
                                                @if($hasViaRole)
                                                    <span class="badge bg-success ms-1" title="Granted via role">Role</span>
                                                @endif
                                                @if($permission->description)
                                                    <br><small class="text-muted">{{ $permission->description }}</small>
                                                @endif
                                            </label>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            <hr>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>

        <!-- Submit Buttons -->
        <div class="d-flex justify-content-end gap-2">
            <a href="{{ route('admin.admins.index') }}" class="btn btn-secondary">
                <i class="fas fa-times me-2"></i>Cancel
            </a>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save me-2"></i>Update Admin
            </button>
        </div>
    </form>
</div>

@push('scripts')
<script>
function selectAllPermissions() {
    document.querySelectorAll('.permission-checkbox').forEach(cb => cb.checked = true);
}

function selectModulePermissions(moduleSlug) {
    document.querySelectorAll('.permission-checkbox').forEach(cb => {
        const label = cb.closest('.form-check').querySelector('label');
        if (label.textContent.includes(moduleSlug)) {
            cb.checked = true;
        }
    });
}
</script>
@endpush
@endsection

