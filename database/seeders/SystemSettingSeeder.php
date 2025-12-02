<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SystemSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            // Payment & Billing Settings
            [
                'key' => 'payment.grace_period_days',
                'category' => 'payment',
                'label' => 'Grace Period (Days)',
                'description' => 'Number of days a subscription remains active after payment failure',
                'type' => 'integer',
                'value' => '3',
                'default_value' => '3',
                'unit' => 'days',
                'min_value' => 1,
                'max_value' => 30,
                'display_order' => 1,
            ],
            [
                'key' => 'payment.retry_max_attempts',
                'category' => 'payment',
                'label' => 'Maximum Payment Retry Attempts',
                'description' => 'Maximum number of times to retry a failed payment',
                'type' => 'integer',
                'value' => '3',
                'default_value' => '3',
                'unit' => 'attempts',
                'min_value' => 1,
                'max_value' => 10,
                'display_order' => 2,
            ],
            [
                'key' => 'payment.retry_delay_1',
                'category' => 'payment',
                'label' => 'First Retry Delay',
                'description' => 'Hours to wait before first payment retry',
                'type' => 'integer',
                'value' => '24',
                'default_value' => '24',
                'unit' => 'hours',
                'min_value' => 1,
                'max_value' => 168,
                'display_order' => 3,
            ],
            [
                'key' => 'payment.retry_delay_2',
                'category' => 'payment',
                'label' => 'Second Retry Delay',
                'description' => 'Days to wait before second payment retry',
                'type' => 'integer',
                'value' => '3',
                'default_value' => '3',
                'unit' => 'days',
                'min_value' => 1,
                'max_value' => 30,
                'display_order' => 4,
            ],
            [
                'key' => 'payment.retry_delay_3',
                'category' => 'payment',
                'label' => 'Third Retry Delay',
                'description' => 'Days to wait before third payment retry',
                'type' => 'integer',
                'value' => '7',
                'default_value' => '7',
                'unit' => 'days',
                'min_value' => 1,
                'max_value' => 30,
                'display_order' => 5,
            ],

            // Subscription Management
            [
                'key' => 'subscription.renewal_days_ahead',
                'category' => 'subscription',
                'label' => 'Renewal Processing Days Ahead',
                'description' => 'Number of days before expiration to process renewals',
                'type' => 'integer',
                'value' => '3',
                'default_value' => '3',
                'unit' => 'days',
                'min_value' => 1,
                'max_value' => 14,
                'display_order' => 1,
            ],
            [
                'key' => 'subscription.free_duration_days',
                'category' => 'subscription',
                'label' => 'Free Subscription Duration',
                'description' => 'Number of days for free subscription period',
                'type' => 'integer',
                'value' => '30',
                'default_value' => '30',
                'unit' => 'days',
                'min_value' => 1,
                'max_value' => 365,
                'display_order' => 2,
            ],
            [
                'key' => 'subscription.expiration_warning_7_days',
                'category' => 'subscription',
                'label' => '7-Day Expiration Warning',
                'description' => 'Send warning when subscription expires in 7 days',
                'type' => 'boolean',
                'value' => '1',
                'default_value' => '1',
                'display_order' => 3,
            ],
            [
                'key' => 'subscription.expiration_warning_3_days',
                'category' => 'subscription',
                'label' => '3-Day Expiration Warning',
                'description' => 'Send warning when subscription expires in 3 days',
                'type' => 'boolean',
                'value' => '1',
                'default_value' => '1',
                'display_order' => 4,
            ],
            [
                'key' => 'subscription.expiration_warning_1_day',
                'category' => 'subscription',
                'label' => '1-Day Expiration Warning',
                'description' => 'Send warning when subscription expires in 1 day',
                'type' => 'boolean',
                'value' => '1',
                'default_value' => '1',
                'display_order' => 5,
            ],

            // Usage & Quotas
            [
                'key' => 'usage.warning_threshold_percent',
                'category' => 'usage',
                'label' => 'Usage Warning Threshold',
                'description' => 'Percentage of quota usage to trigger warning (token and message limits)',
                'type' => 'integer',
                'value' => '80',
                'default_value' => '80',
                'unit' => '%',
                'min_value' => 50,
                'max_value' => 99,
                'display_order' => 1,
            ],
            [
                'key' => 'usage.statistics_period_days',
                'category' => 'usage',
                'label' => 'Usage Statistics Period',
                'description' => 'Number of days to include in usage statistics',
                'type' => 'integer',
                'value' => '30',
                'default_value' => '30',
                'unit' => 'days',
                'min_value' => 7,
                'max_value' => 365,
                'display_order' => 2,
            ],

            // Guest Users
            [
                'key' => 'guest.session_expiration_hours',
                'category' => 'guest',
                'label' => 'Guest Session Expiration',
                'description' => 'Number of hours before guest session expires',
                'type' => 'integer',
                'value' => '24',
                'default_value' => '24',
                'unit' => 'hours',
                'min_value' => 1,
                'max_value' => 168,
                'display_order' => 1,
            ],

            // Cache Settings
            [
                'key' => 'cache.visitor_api_hours',
                'category' => 'cache',
                'label' => 'Visitor API Cache Duration',
                'description' => 'Hours to cache visitor API responses',
                'type' => 'integer',
                'value' => '24',
                'default_value' => '24',
                'unit' => 'hours',
                'min_value' => 1,
                'max_value' => 168,
                'display_order' => 1,
            ],
            [
                'key' => 'cache.document_service_hours',
                'category' => 'cache',
                'label' => 'Document Service Cache Duration',
                'description' => 'Hours to cache document service responses',
                'type' => 'integer',
                'value' => '24',
                'default_value' => '24',
                'unit' => 'hours',
                'min_value' => 1,
                'max_value' => 168,
                'display_order' => 2,
            ],
            [
                'key' => 'cache.ai_engine_health_hours',
                'category' => 'cache',
                'label' => 'AI Engine Health Cache Duration',
                'description' => 'Hours to cache AI engine health status',
                'type' => 'integer',
                'value' => '1',
                'default_value' => '1',
                'unit' => 'hours',
                'min_value' => 1,
                'max_value' => 24,
                'display_order' => 3,
            ],
            [
                'key' => 'cache.brain_orchestrator_hours',
                'category' => 'cache',
                'label' => 'Brain Orchestrator Cache Duration',
                'description' => 'Hours to cache brain orchestrator responses',
                'type' => 'integer',
                'value' => '1',
                'default_value' => '1',
                'unit' => 'hours',
                'min_value' => 1,
                'max_value' => 24,
                'display_order' => 4,
            ],

            // Dashboard & Display
            [
                'key' => 'dashboard.expiring_subscriptions_days',
                'category' => 'dashboard',
                'label' => 'Expiring Subscriptions Threshold',
                'description' => 'Number of days ahead to show expiring subscriptions in dashboard',
                'type' => 'integer',
                'value' => '7',
                'default_value' => '7',
                'unit' => 'days',
                'min_value' => 1,
                'max_value' => 30,
                'display_order' => 1,
            ],
            [
                'key' => 'dashboard.recent_activity_hours',
                'category' => 'dashboard',
                'label' => 'Recent Activity Timeframe',
                'description' => 'Hours to include in recent activity display',
                'type' => 'integer',
                'value' => '24',
                'default_value' => '24',
                'unit' => 'hours',
                'min_value' => 1,
                'max_value' => 168,
                'display_order' => 2,
            ],

            // Audit Logs
            [
                'key' => 'audit.cleanup_frequency_days',
                'category' => 'audit',
                'label' => 'Audit Log Cleanup Frequency',
                'description' => 'Number of days between audit log cleanups',
                'type' => 'integer',
                'value' => '1',
                'default_value' => '1',
                'unit' => 'days',
                'min_value' => 1,
                'max_value' => 7,
                'display_order' => 1,
            ],
        ];

        foreach ($settings as $setting) {
            SystemSetting::updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }
}
