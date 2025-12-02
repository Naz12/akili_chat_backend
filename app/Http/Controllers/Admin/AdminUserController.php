<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Services\AdminActivityLogService;
use App\Services\ExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Collection;

class AdminUserController extends Controller
{
    public function index(Request $request)
    {
        // Check permission
        if (!auth()->user()->hasPermission('admins.view') && !auth()->user()->isSuperAdmin()) {
            abort(403, 'You do not have permission to view admin users.');
        }
        $query = User::with(['roles', 'permissions'])
            ->where(function ($q) {
                $q->whereHas('roles')
                  ->orWhere('role', 'admin');
            });

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filter by role
        if ($request->filled('role')) {
            $query->whereHas('roles', function ($q) use ($request) {
                $q->where('slug', $request->role);
            });
        }

        // Filter by status
        if ($request->filled('status')) {
            if ($request->status === 'active') {
                $query->where('is_active', true);
            } elseif ($request->status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        $admins = $query->latest()->paginate(20);

        $roles = Role::all();

        return view('admin.admins.index', compact('admins', 'roles'));
    }

    public function create()
    {
        // Check permission
        if (!auth()->user()->hasPermission('admins.create') && !auth()->user()->isSuperAdmin()) {
            abort(403, 'You do not have permission to create admin users.');
        }
        $roles = Role::with('permissions')->get();
        $permissions = Permission::orderBy('module')->orderBy('name')->get()->groupBy('module');
        $modules = config('rbac.permissions.modules', []);

        // Create role-permission mapping for JavaScript
        $rolePermissionsMap = [];
        foreach ($roles as $role) {
            $rolePermissionsMap[$role->id] = $role->permissions->pluck('id')->toArray();
        }

        return view('admin.admins.create', compact('roles', 'permissions', 'modules', 'rolePermissionsMap'));
    }

    public function store(Request $request)
    {
        // Check permission
        if (!auth()->user()->hasPermission('admins.create') && !auth()->user()->isSuperAdmin()) {
            abort(403, 'You do not have permission to create admin users.');
        }
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'is_active' => 'boolean',
            'roles' => 'array',
            'roles.*' => 'exists:roles,id',
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $admin = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'is_active' => $request->has('is_active') ? (bool)$request->is_active : true,
            'role' => 'admin', // Legacy field
            'region' => 'local', // Default region
            'email_verified_at' => now(),
            'is_suspended' => 0,
            'country_id' => 1, // Default country
            'fcm_token' => '',
            'password_reset_code' => '',
        ]);

        // Assign roles
        if ($request->filled('roles')) {
            $admin->roles()->sync($request->roles);
        }

        // Assign permissions
        if ($request->filled('permissions')) {
            $admin->permissions()->sync($request->permissions);
        }

        // Log activity
        AdminActivityLogService::logAdminCreate($admin, [
            'name' => $admin->name,
            'email' => $admin->email,
            'roles' => $admin->roles->pluck('name')->toArray(),
            'permissions_count' => $admin->permissions->count(),
        ]);

        return redirect()->route('admin.admins.index')
            ->with('success', 'Admin user created successfully.');
    }

    public function show(User $admin)
    {
        // Check permission
        if (!auth()->user()->hasPermission('admins.view') && !auth()->user()->isSuperAdmin()) {
            abort(403, 'You do not have permission to view admin users.');
        }
        $admin->load(['roles', 'permissions']);
        $activityLogs = $admin->adminActivityLogs()
            ->latest()
            ->limit(50)
            ->get();

        return view('admin.admins.show', compact('admin', 'activityLogs'));
    }

    public function edit(User $admin)
    {
        // Check permission
        if (!auth()->user()->hasPermission('admins.edit') && !auth()->user()->isSuperAdmin()) {
            abort(403, 'You do not have permission to edit admin users.');
        }
        // Prevent self role change
        $canChangeRole = auth()->id() !== $admin->id;

        $roles = Role::all();
        $permissions = Permission::orderBy('module')->orderBy('name')->get()->groupBy('module');
        $modules = config('rbac.permissions.modules', []);

        $admin->load(['roles', 'permissions']);

        return view('admin.admins.edit', compact('admin', 'roles', 'permissions', 'modules', 'canChangeRole'));
    }

    public function update(Request $request, User $admin)
    {
        // Check permission
        if (!auth()->user()->hasPermission('admins.edit') && !auth()->user()->isSuperAdmin()) {
            abort(403, 'You do not have permission to edit admin users.');
        }
        // Prevent self role change
        if (auth()->id() === $admin->id) {
            $request->merge(['roles' => $admin->roles->pluck('id')->toArray()]);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users')->ignore($admin->id)],
            'password' => 'nullable|string|min:8|confirmed',
            'is_active' => 'boolean',
            'roles' => 'array',
            'roles.*' => 'exists:roles,id',
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $oldData = [
            'name' => $admin->name,
            'email' => $admin->email,
            'is_active' => $admin->is_active,
            'roles' => $admin->roles->pluck('name')->toArray(),
            'permissions' => $admin->permissions->pluck('name')->toArray(),
        ];

        $admin->name = $request->name;
        $admin->email = $request->email;
        
        if ($request->filled('password')) {
            $admin->password = Hash::make($request->password);
        }

        $admin->is_active = $request->has('is_active') ? (bool)$request->is_active : true;
        $admin->save();

        // Update roles (only if not self)
        if (auth()->id() !== $admin->id && $request->filled('roles')) {
            $oldRoles = $admin->roles->pluck('name')->toArray();
            $admin->roles()->sync($request->roles);
            $newRoles = $admin->fresh()->roles->pluck('name')->toArray();
            
            if ($oldRoles !== $newRoles) {
                AdminActivityLogService::logRoleChange($admin, $oldRoles, $newRoles);
            }
        }

        // Update permissions
        if ($request->filled('permissions')) {
            $oldPermissions = $admin->permissions->pluck('name')->toArray();
            $admin->permissions()->sync($request->permissions);
            $newPermissions = $admin->fresh()->permissions->pluck('name')->toArray();
            
            if ($oldPermissions !== $newPermissions) {
                AdminActivityLogService::logPermissionChange($admin, $oldPermissions, $newPermissions);
            }
        }

        $newData = [
            'name' => $admin->name,
            'email' => $admin->email,
            'is_active' => $admin->is_active,
            'roles' => $admin->fresh()->roles->pluck('name')->toArray(),
            'permissions' => $admin->fresh()->permissions->pluck('name')->toArray(),
        ];

        // Log activity
        AdminActivityLogService::logAdminUpdate($admin, $oldData, $newData);

        return redirect()->route('admin.admins.index')
            ->with('success', 'Admin user updated successfully.');
    }

    public function destroy(User $admin)
    {
        // Check permission
        if (!auth()->user()->hasPermission('admins.delete') && !auth()->user()->isSuperAdmin()) {
            abort(403, 'You do not have permission to delete admin users.');
        }
        // Prevent self deletion
        if (auth()->id() === $admin->id) {
            return redirect()->back()
                ->with('error', 'You cannot delete your own account.');
        }

        // Prevent deleting last super admin
        if ($admin->isSuperAdmin()) {
            $superAdminCount = User::whereHas('roles', function ($q) {
                $q->where('is_super_admin', true);
            })->where('is_active', true)->count();

            if ($superAdminCount <= 1) {
                return redirect()->back()
                    ->with('error', 'Cannot delete the last active super admin.');
            }
        }

        // Deactivate instead of delete
        $admin->is_active = false;
        $admin->save();

        AdminActivityLogService::logAdminDeactivate($admin);

        return redirect()->route('admin.admins.index')
            ->with('success', 'Admin user deactivated successfully.');
    }

    public function activate(User $admin)
    {
        // Check permission
        if (!auth()->user()->hasPermission('admins.edit') && !auth()->user()->isSuperAdmin()) {
            abort(403, 'You do not have permission to activate admin users.');
        }
        $admin->is_active = true;
        $admin->save();

        AdminActivityLogService::logAdminActivate($admin);

        return redirect()->back()
            ->with('success', 'Admin user activated successfully.');
    }

    public function deactivate(User $admin)
    {
        // Check permission
        if (!auth()->user()->hasPermission('admins.delete') && !auth()->user()->isSuperAdmin()) {
            abort(403, 'You do not have permission to deactivate admin users.');
        }
        // Prevent self deactivation
        if (auth()->id() === $admin->id) {
            return redirect()->back()
                ->with('error', 'You cannot deactivate your own account.');
        }

        // Prevent deactivating last super admin
        if ($admin->isSuperAdmin()) {
            $superAdminCount = User::whereHas('roles', function ($q) {
                $q->where('is_super_admin', true);
            })->where('is_active', true)->count();

            if ($superAdminCount <= 1) {
                return redirect()->back()
                    ->with('error', 'Cannot deactivate the last active super admin.');
            }
        }

        $admin->is_active = false;
        $admin->save();

        AdminActivityLogService::logAdminDeactivate($admin);

        return redirect()->back()
            ->with('success', 'Admin user deactivated successfully.');
    }

    /**
     * Export admin users to Excel or PDF
     */
    public function export(Request $request)
    {
        try {
            $format = $request->get('format', 'excel');
            $range = $request->get('range', 'current');

            $query = User::with(['roles', 'permissions'])
                ->where(function ($q) {
                    $q->whereHas('roles')
                      ->orWhere('role', 'admin');
                });

            // Apply filters
            if ($range === 'filtered' || $range === 'current') {
                if ($request->filled('search')) {
                    $search = $request->search;
                    $query->where(function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                          ->orWhere('email', 'like', "%{$search}%");
                    });
                }
                if ($request->filled('role')) {
                    $query->whereHas('roles', function ($q) use ($request) {
                        $q->where('slug', $request->role);
                    });
                }
                if ($request->filled('status')) {
                    if ($request->status === 'active') {
                        $query->where('is_active', true);
                    } elseif ($request->status === 'inactive') {
                        $query->where('is_active', false);
                    }
                }
            }

            // Apply date filters (works with all ranges)
            if ($request->filled('date_from')) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }
            if ($request->filled('date_to')) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            // Get data based on range
            if ($range === 'current') {
                $admins = $query->paginate(20);
                $data = $admins->items();
            } elseif ($range === 'custom') {
                $limit = min((int) $request->get('limit', 100), 10000); // Max 10,000
                $data = $query->limit($limit)->get();
            } else {
                $data = $query->get();
            }

            $exportData = Collection::make($data)->map(function ($admin) {
                $roles = $admin->roles->pluck('name')->join(', ');
                if ($roles === '' && $admin->role === 'admin') {
                    $roles = 'Legacy Admin';
                }
                return [
                    'ID' => $admin->id,
                    'Name' => $admin->name,
                    'Email' => $admin->email,
                    'Roles' => $roles ?: 'N/A',
                    'Status' => $admin->is_active ? 'Active' : 'Inactive',
                    'Last Login' => $admin->last_login_at ? $admin->last_login_at->format('Y-m-d H:i:s') : 'Never',
                    'Created' => $admin->created_at->format('Y-m-d'),
                ];
            });

            $headers = ['ID', 'Name', 'Email', 'Roles', 'Status', 'Last Login', 'Created'];
            $filename = 'admin_users_' . $range . '_' . now()->format('Y-m-d_H-i-s');

            if ($format === 'pdf') {
                return ExportService::exportToPdf($exportData, $headers, 'Admin Users Export', $filename);
            }

            return ExportService::exportToExcel($exportData, $headers, $filename);
        } catch (\Exception $e) {
            Log::error('Admin users export error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return redirect()->back()->with('error', 'Export failed: ' . $e->getMessage());
        }
    }
}

