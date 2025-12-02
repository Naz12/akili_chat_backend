<?php

return [
    'super_admin_role_slug' => 'super_admin',
    'default_admin_role_slug' => 'admin',
    
    'audit_log' => [
        'retention_days' => env('AUDIT_LOG_RETENTION_DAYS', 90),
        'auto_cleanup' => env('AUDIT_LOG_AUTO_CLEANUP', true),
    ],
    
    'permissions' => [
        'modules' => [
            'dashboard' => 'Dashboard',
            'plans' => 'Plans',
            'ai_engines' => 'AI Engines',
            'subscriptions' => 'Subscriptions',
            'billing' => 'Billing',
            'payments' => 'Payments',
            'users' => 'Users',
            'admins' => 'Admin Management',
            'settings' => 'System Settings',
            'analytics' => 'Analytics',
        ],
    ],
];

