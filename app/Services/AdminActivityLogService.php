<?php

namespace App\Services;

use App\Models\AdminActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AdminActivityLogService
{
    public static function log(
        string $action,
        ?Model $model = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $description = null
    ): AdminActivityLog {
        $adminId = Auth::id();
        
        if (!$adminId) {
            throw new \Exception('No authenticated admin user found');
        }

        return AdminActivityLog::create([
            'admin_id' => $adminId,
            'action' => $action,
            'model_type' => $model ? get_class($model) : null,
            'model_id' => $model?->id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'description' => $description,
        ]);
    }

    public static function logCreate(Model $model, array $data, ?string $description = null): AdminActivityLog
    {
        return static::log(
            'created_' . static::getModelName($model),
            $model,
            null,
            $data,
            $description ?? 'Created ' . static::getModelName($model)
        );
    }

    public static function logUpdate(Model $model, array $oldData, array $newData, ?string $description = null): AdminActivityLog
    {
        return static::log(
            'updated_' . static::getModelName($model),
            $model,
            $oldData,
            $newData,
            $description ?? 'Updated ' . static::getModelName($model)
        );
    }

    public static function logDelete(Model $model, array $data, ?string $description = null): AdminActivityLog
    {
        return static::log(
            'deleted_' . static::getModelName($model),
            $model,
            $data,
            null,
            $description ?? 'Deleted ' . static::getModelName($model)
        );
    }

    public static function logPermissionChange(
        Model $admin,
        array $oldPermissions,
        array $newPermissions,
        ?string $description = null
    ): AdminActivityLog {
        return static::log(
            'updated_permissions',
            $admin,
            ['permissions' => $oldPermissions],
            ['permissions' => $newPermissions],
            $description ?? 'Updated admin permissions'
        );
    }

    public static function logRoleChange(
        Model $admin,
        array $oldRoles,
        array $newRoles,
        ?string $description = null
    ): AdminActivityLog {
        return static::log(
            'updated_roles',
            $admin,
            ['roles' => $oldRoles],
            ['roles' => $newRoles],
            $description ?? 'Updated admin roles'
        );
    }

    public static function logAdminCreate(Model $admin, array $data, ?string $description = null): AdminActivityLog
    {
        return static::log(
            'created_admin',
            $admin,
            null,
            $data,
            $description ?? 'Created new admin user'
        );
    }

    public static function logAdminUpdate(Model $admin, array $oldData, array $newData, ?string $description = null): AdminActivityLog
    {
        return static::log(
            'updated_admin',
            $admin,
            $oldData,
            $newData,
            $description ?? 'Updated admin user'
        );
    }

    public static function logAdminActivate(Model $admin, ?string $description = null): AdminActivityLog
    {
        return static::log(
            'activated_admin',
            $admin,
            ['is_active' => false],
            ['is_active' => true],
            $description ?? 'Activated admin user'
        );
    }

    public static function logAdminDeactivate(Model $admin, ?string $description = null): AdminActivityLog
    {
        return static::log(
            'deactivated_admin',
            $admin,
            ['is_active' => true],
            ['is_active' => false],
            $description ?? 'Deactivated admin user'
        );
    }

    private static function getModelName(Model $model): string
    {
        $className = class_basename($model);
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $className));
    }
}

