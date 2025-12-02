<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SendExpirationWarnings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:send-expiration-warnings';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send expiration warnings to users (7, 3, 1 days before expiration)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Sending subscription expiration warnings...');
        
        $warningsSent = 0;
        
        // Check for subscriptions expiring in 7 days (if enabled)
        if (\App\Models\SystemSetting::getValue('subscription.expiration_warning_7_days', true)) {
            $expiringIn7Days = \App\Models\Subscription::where('is_active', true)
                ->whereDate('end_date', '=', now()->addDays(7)->toDateString())
                ->with('user')
                ->get();
            
            foreach ($expiringIn7Days as $subscription) {
                // Send 7-day warning notification
                // TODO: Implement notification
                \Log::info('7-day expiration warning should be sent', [
                    'subscription_id' => $subscription->id,
                    'user_id' => $subscription->user_id,
                ]);
                $warningsSent++;
            }
        }
        
        // Check for subscriptions expiring in 3 days (if enabled)
        if (\App\Models\SystemSetting::getValue('subscription.expiration_warning_3_days', true)) {
            $expiringIn3Days = \App\Models\Subscription::where('is_active', true)
                ->whereDate('end_date', '=', now()->addDays(3)->toDateString())
                ->with('user')
                ->get();
            
            foreach ($expiringIn3Days as $subscription) {
                // Send 3-day warning notification
                \Log::info('3-day expiration warning should be sent', [
                    'subscription_id' => $subscription->id,
                    'user_id' => $subscription->user_id,
                ]);
                $warningsSent++;
            }
        }
        
        // Check for subscriptions expiring in 1 day (if enabled)
        if (\App\Models\SystemSetting::getValue('subscription.expiration_warning_1_day', true)) {
            $expiringIn1Day = \App\Models\Subscription::where('is_active', true)
                ->whereDate('end_date', '=', now()->addDay()->toDateString())
                ->with('user')
                ->get();
            
            foreach ($expiringIn1Day as $subscription) {
                // Send 1-day warning notification
                \Log::info('1-day expiration warning should be sent', [
                    'subscription_id' => $subscription->id,
                    'user_id' => $subscription->user_id,
                ]);
                $warningsSent++;
            }
        }
        
        $this->info("Sent {$warningsSent} expiration warnings.");
        
        return 0;
    }
}
