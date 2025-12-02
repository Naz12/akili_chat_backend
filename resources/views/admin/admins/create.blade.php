@extends('layouts.admin')
@section('title', 'Create Admin')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 fw-bold mb-1" style="color: #1e293b;">
                <i class="fas fa-user-plus me-2" style="color: #6366f1;"></i>Create Admin User
            </h1>
            <p class="text-muted mb-0" style="font-size: 0.9rem;">Create a new admin user with custom roles and permissions</p>
        </div>
        <a href="{{ route('admin.admins.index') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to List
        </a>
    </div>

    <form action="{{ route('admin.admins.store') }}" method="POST">
        @csrf

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
                            <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name') }}" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email') }}" required>
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control @error('password') is-invalid @enderror" id="password" name="password" required>
                            @error('password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="password_confirmation" class="form-label">Confirm Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" required>
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}>
                                <label class="form-check-label" for="is_active">
                                    Active
                                </label>
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
                        <p class="text-muted small mb-3">Selecting a role grants all permissions for that role. You can also assign custom permissions below.</p>
                        @foreach($roles as $role)
                            <div class="form-check mb-2">
                                <input class="form-check-input role-checkbox" type="checkbox" name="roles[]" value="{{ $role->id }}" id="role_{{ $role->id }}" {{ in_array($role->id, old('roles', [])) ? 'checked' : '' }}>
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
                <p class="text-muted small mb-3">Select individual permissions. Permissions granted via roles are shown with a green checkmark.</p>
                
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
                                    <div class="col-md-4 mb-2">
                                        <div class="form-check">
                                            <input class="form-check-input permission-checkbox" type="checkbox" name="permissions[]" value="{{ $permission->id }}" id="perm_{{ $permission->id }}" data-permission-id="{{ $permission->id }}" {{ in_array($permission->id, old('permissions', [])) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="perm_{{ $permission->id }}">
                                                <span class="permission-name">{{ $permission->name }}</span>
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
                <i class="fas fa-save me-2"></i>Create Admin
            </button>
        </div>
    </form>
</div>

@push('scripts')
<script>
// Role-Permission mapping from backend
const rolePermissionsMap = @json($rolePermissionsMap ?? []);

// Track which permissions are checked via roles
const permissionsFromRoles = new Set();

function selectAllPermissions() {
    document.querySelectorAll('.permission-checkbox').forEach(cb => cb.checked = true);
}

function selectModulePermissions(moduleSlug) {
    // Select all permissions in a module
    document.querySelectorAll('.permission-module').forEach(module => {
        const moduleTitle = module.querySelector('h6').textContent.toLowerCase();
        if (moduleTitle.includes(moduleSlug.toLowerCase())) {
            module.querySelectorAll('.permission-checkbox').forEach(cb => {
                cb.checked = true;
            });
        }
    });
}

function updatePermissionsFromRoles() {
    // Get all checked roles
    const checkedRoles = Array.from(document.querySelectorAll('.role-checkbox:checked'))
        .map(cb => parseInt(cb.value));
    
    // Clear previous role-based permissions
    permissionsFromRoles.clear();
    
    // Collect all permissions from checked roles
    checkedRoles.forEach(roleId => {
        if (rolePermissionsMap[roleId]) {
            rolePermissionsMap[roleId].forEach(permId => {
                permissionsFromRoles.add(permId);
            });
        }
    });
    
    // Update permission checkboxes
    document.querySelectorAll('.permission-checkbox').forEach(cb => {
        const permId = parseInt(cb.value);
        const isFromRole = permissionsFromRoles.has(permId);
        const label = cb.closest('.form-check').querySelector('label');
        let badge = label.querySelector('.role-badge');
        
        if (isFromRole) {
            // Check the permission
            cb.checked = true;
            
            // Add or update role badge
            if (!badge) {
                badge = document.createElement('span');
                badge.className = 'badge bg-success ms-1 role-badge';
                badge.textContent = 'Role';
                badge.title = 'Granted via selected role';
                // Insert badge after the permission name span
                const permissionName = label.querySelector('.permission-name');
                if (permissionName && permissionName.nextSibling) {
                    label.insertBefore(badge, permissionName.nextSibling);
                } else if (permissionName) {
                    permissionName.parentNode.insertBefore(badge, permissionName.nextSibling);
                } else {
                    label.insertBefore(badge, label.firstChild);
                }
            }
        } else {
            // Remove role badge if exists (but don't uncheck if manually checked)
            if (badge) {
                badge.remove();
            }
        }
    });
}

// Add event listeners to role checkboxes
document.addEventListener('DOMContentLoaded', function() {
    // Initial update
    updatePermissionsFromRoles();
    
    // Listen for role checkbox changes
    document.querySelectorAll('.role-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            updatePermissionsFromRoles();
        });
    });
    
    // Listen for permission checkbox changes to update badges
    document.querySelectorAll('.permission-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const permId = parseInt(this.value);
            const isFromRole = permissionsFromRoles.has(permId);
            const label = this.closest('.form-check').querySelector('label');
            let badge = label.querySelector('.role-badge');
            
            if (isFromRole && this.checked) {
                // Add badge if permission is from role
                if (!badge) {
                    badge = document.createElement('span');
                    badge.className = 'badge bg-success ms-1 role-badge';
                    badge.textContent = 'Role';
                    badge.title = 'Granted via selected role';
                    label.insertBefore(badge, label.firstChild);
                }
            } else if (!isFromRole && badge) {
                // Remove badge if permission is not from role
                badge.remove();
            }
        });
    });
});
</script>
@endpush
@endsection

