<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Permission;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // Super Admin Role
        $superAdmin = Role::firstOrCreate(
            ['slug' => 'super_admin'],
            [
                'name' => 'Super Admin',
                'description' => 'Full system access with all permissions',
                'is_super_admin' => true,
                'is_system_role' => true,
            ]
        );

        // Assign all permissions to super admin
        $superAdmin->permissions()->sync(Permission::pluck('id'));

        // Admin Role (Full Access)
        $admin = Role::firstOrCreate(
            ['slug' => 'admin'],
            [
                'name' => 'Admin',
                'description' => 'Full admin access except admin management',
                'is_super_admin' => false,
                'is_system_role' => true,
            ]
        );

        // Assign all permissions except admin management to regular admin
        $adminPermissions = Permission::whereNotIn('slug', [
            'admins.create',
            'admins.manage_roles',
            'admins.view_audit_logs',
        ])->pluck('id');
        $admin->permissions()->sync($adminPermissions);

        // Billing Admin Role
        $billingAdmin = Role::firstOrCreate(
            ['slug' => 'billing_admin'],
            [
                'name' => 'Billing Admin',
                'description' => 'Access to billing, payments, and subscriptions',
                'is_super_admin' => false,
                'is_system_role' => false,
            ]
        );

        $billingAdminPermissions = Permission::whereIn('slug', [
            'dashboard.view',
            'billing.view',
            'billing.adjust_usage',
            'bills.view',
            'bills.mark_paid',
            'bills.cancel',
            'payments.view',
            'payments.refund',
            'payments.verify',
            'subscriptions.view',
            'subscriptions.edit',
            'subscriptions.extend_grace',
            'subscriptions.trigger_renewal',
            'subscriptions.reset_failure',
            'analytics.view',
        ])->pluck('id');
        $billingAdmin->permissions()->sync($billingAdminPermissions);

        // Content Admin Role
        $contentAdmin = Role::firstOrCreate(
            ['slug' => 'content_admin'],
            [
                'name' => 'Content Admin',
                'description' => 'Access to plans, AI engines, and preferences',
                'is_super_admin' => false,
                'is_system_role' => false,
            ]
        );

        $contentAdminPermissions = Permission::whereIn('slug', [
            'dashboard.view',
            'plans.view',
            'plans.create',
            'plans.edit',
            'plans.delete',
            'ai_engines.view',
            'ai_engines.create',
            'ai_engines.edit',
            'ai_engines.delete',
            'ai_engines.test',
            'preferences.manage',
            'analytics.view',
        ])->pluck('id');
        $contentAdmin->permissions()->sync($contentAdminPermissions);

        // Support Admin Role
        $supportAdmin = Role::firstOrCreate(
            ['slug' => 'support_admin'],
            [
                'name' => 'Support Admin',
                'description' => 'Access to visitors, usage logs, and subscriptions (view only)',
                'is_super_admin' => false,
                'is_system_role' => false,
            ]
        );

        $supportAdminPermissions = Permission::whereIn('slug', [
            'dashboard.view',
            'visitors.view',
            'usage_logs.view',
            'subscriptions.view',
            'users.view',
        ])->pluck('id');
        $supportAdmin->permissions()->sync($supportAdminPermissions);

        // Analytics Admin Role (Read-only)
        $analyticsAdmin = Role::firstOrCreate(
            ['slug' => 'analytics_admin'],
            [
                'name' => 'Analytics Admin',
                'description' => 'Read-only access to analytics and reports',
                'is_super_admin' => false,
                'is_system_role' => false,
            ]
        );

        $analyticsAdminPermissions = Permission::whereIn('slug', [
            'dashboard.view',
            'analytics.view',
            'visitors.view',
            'usage_logs.view',
            'subscriptions.view',
        ])->pluck('id');
        $analyticsAdmin->permissions()->sync($analyticsAdminPermissions);
    }
}

