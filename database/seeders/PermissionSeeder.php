<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Permission;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Dashboard
            ['name' => 'View Dashboard', 'slug' => 'dashboard.view', 'module' => 'dashboard', 'description' => 'View admin dashboard'],

            // Plans
            ['name' => 'View Plans', 'slug' => 'plans.view', 'module' => 'plans', 'description' => 'View subscription plans'],
            ['name' => 'Create Plans', 'slug' => 'plans.create', 'module' => 'plans', 'description' => 'Create new subscription plans'],
            ['name' => 'Edit Plans', 'slug' => 'plans.edit', 'module' => 'plans', 'description' => 'Edit existing subscription plans'],
            ['name' => 'Delete Plans', 'slug' => 'plans.delete', 'module' => 'plans', 'description' => 'Delete subscription plans'],

            // AI Engines
            ['name' => 'View AI Engines', 'slug' => 'ai_engines.view', 'module' => 'ai_engines', 'description' => 'View AI engines'],
            ['name' => 'Create AI Engines', 'slug' => 'ai_engines.create', 'module' => 'ai_engines', 'description' => 'Create new AI engines'],
            ['name' => 'Edit AI Engines', 'slug' => 'ai_engines.edit', 'module' => 'ai_engines', 'description' => 'Edit existing AI engines'],
            ['name' => 'Delete AI Engines', 'slug' => 'ai_engines.delete', 'module' => 'ai_engines', 'description' => 'Delete AI engines'],
            ['name' => 'Test AI Engines', 'slug' => 'ai_engines.test', 'module' => 'ai_engines', 'description' => 'Test AI engines'],

            // Subscriptions
            ['name' => 'View Subscriptions', 'slug' => 'subscriptions.view', 'module' => 'subscriptions', 'description' => 'View user subscriptions'],
            ['name' => 'Edit Subscriptions', 'slug' => 'subscriptions.edit', 'module' => 'subscriptions', 'description' => 'Edit user subscriptions'],
            ['name' => 'Extend Grace Period', 'slug' => 'subscriptions.extend_grace', 'module' => 'subscriptions', 'description' => 'Extend subscription grace period'],
            ['name' => 'Trigger Renewal', 'slug' => 'subscriptions.trigger_renewal', 'module' => 'subscriptions', 'description' => 'Trigger subscription renewal'],
            ['name' => 'Reset Failure Count', 'slug' => 'subscriptions.reset_failure', 'module' => 'subscriptions', 'description' => 'Reset payment failure count'],
            ['name' => 'Cancel Subscriptions', 'slug' => 'subscriptions.cancel', 'module' => 'subscriptions', 'description' => 'Cancel subscriptions'],

            // Billing
            ['name' => 'View Billing', 'slug' => 'billing.view', 'module' => 'billing', 'description' => 'View billing information'],
            ['name' => 'Adjust Usage', 'slug' => 'billing.adjust_usage', 'module' => 'billing', 'description' => 'Adjust subscription token usage'],
            ['name' => 'View Bills', 'slug' => 'bills.view', 'module' => 'billing', 'description' => 'View bills'],
            ['name' => 'Mark Bill Paid', 'slug' => 'bills.mark_paid', 'module' => 'billing', 'description' => 'Mark bills as paid'],
            ['name' => 'Cancel Bills', 'slug' => 'bills.cancel', 'module' => 'billing', 'description' => 'Cancel bills'],

            // Payments
            ['name' => 'View Payments', 'slug' => 'payments.view', 'module' => 'payments', 'description' => 'View payment records'],
            ['name' => 'Refund Payments', 'slug' => 'payments.refund', 'module' => 'payments', 'description' => 'Refund payments'],
            ['name' => 'Verify Payments', 'slug' => 'payments.verify', 'module' => 'payments', 'description' => 'Verify payments'],

            // Users
            ['name' => 'View Users', 'slug' => 'users.view', 'module' => 'users', 'description' => 'View user accounts'],
            ['name' => 'Suspend Users', 'slug' => 'users.suspend', 'module' => 'users', 'description' => 'Suspend user accounts'],
            ['name' => 'Unsuspend Users', 'slug' => 'users.unsuspend', 'module' => 'users', 'description' => 'Unsuspend user accounts'],

            // Admin Management
            ['name' => 'View Admins', 'slug' => 'admins.view', 'module' => 'admins', 'description' => 'View admin users'],
            ['name' => 'Create Admins', 'slug' => 'admins.create', 'module' => 'admins', 'description' => 'Create new admin users'],
            ['name' => 'Edit Admins', 'slug' => 'admins.edit', 'module' => 'admins', 'description' => 'Edit admin users'],
            ['name' => 'Delete Admins', 'slug' => 'admins.delete', 'module' => 'admins', 'description' => 'Delete admin users'],
            ['name' => 'Manage Roles', 'slug' => 'admins.manage_roles', 'module' => 'admins', 'description' => 'Assign and manage admin roles'],
            ['name' => 'View Audit Logs', 'slug' => 'admins.view_audit_logs', 'module' => 'admins', 'description' => 'View admin activity audit logs'],

            // System Settings
            ['name' => 'View Settings', 'slug' => 'settings.view', 'module' => 'settings', 'description' => 'View system settings'],
            ['name' => 'Edit Settings', 'slug' => 'settings.edit', 'module' => 'settings', 'description' => 'Edit system settings'],
            ['name' => 'Manage Payment Methods', 'slug' => 'payment_methods.manage', 'module' => 'settings', 'description' => 'Manage payment methods'],
            ['name' => 'Manage Preferences', 'slug' => 'preferences.manage', 'module' => 'settings', 'description' => 'Manage system preferences'],
            ['name' => 'Manage Webhooks', 'slug' => 'webhooks.manage', 'module' => 'settings', 'description' => 'Manage webhooks'],
            ['name' => 'View Webhooks', 'slug' => 'webhooks.view', 'module' => 'settings', 'description' => 'View webhook logs'],
            ['name' => 'Manage Notifier', 'slug' => 'notifier.manage', 'module' => 'settings', 'description' => 'Manage notification system'],

            // Analytics
            ['name' => 'View Analytics', 'slug' => 'analytics.view', 'module' => 'analytics', 'description' => 'View analytics'],
            ['name' => 'View Visitors', 'slug' => 'visitors.view', 'module' => 'analytics', 'description' => 'View visitor analytics'],
            ['name' => 'View Usage Logs', 'slug' => 'usage_logs.view', 'module' => 'analytics', 'description' => 'View token usage logs'],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['slug' => $permission['slug']],
                $permission
            );
        }
    }
}

