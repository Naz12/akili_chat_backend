<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CheckUsageQuotas extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'usage:check-quotas';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check usage quotas and send warnings for users approaching or exceeding limits';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking usage quotas...');
        
        $notificationService = app(\App\Services\Notification\NotificationService::class);
        $warningsSent = 0;
        $exceededSent = 0;
        
        // Get all active subscriptions
        $subscriptions = \App\Models\Subscription::where('is_active', true)
            ->whereDate('end_date', '>=', now())
            ->with(['user', 'plan'])
            ->get();
        
        foreach ($subscriptions as $subscription) {
            $plan = $subscription->plan;
            
            if (!$plan || !$plan->max_tokens) {
                continue;
            }
            
            $tokensUsed = $subscription->tokens_used ?? 0;
            $maxTokens = $plan->max_tokens;
            $percentageUsed = $maxTokens > 0 ? ($tokensUsed / $maxTokens) * 100 : 0;
            
            // Check if quota exceeded
            if ($tokensUsed >= $maxTokens) {
                try {
                    $notification = new \App\Notifications\QuotaExceededNotification($subscription);
                    $notificationService->send($subscription->user, $notification);
                    $exceededSent++;
                } catch (\Exception $e) {
                    \Log::error('Failed to send quota exceeded notification', [
                        'subscription_id' => $subscription->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            // Check if at warning threshold (configurable via system settings)
            $warningThreshold = \App\Models\SystemSetting::getValue('usage.warning_threshold_percent', 80);
            if ($percentageUsed >= $warningThreshold && $percentageUsed < 100) {
                try {
                    $notification = new \App\Notifications\UsageWarningNotification($subscription, $percentageUsed);
                    $notificationService->send($subscription->user, $notification);
                    $warningsSent++;
                } catch (\Exception $e) {
                    \Log::error('Failed to send usage warning notification', [
                        'subscription_id' => $subscription->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
        
        $this->info("Sent {$warningsSent} usage warnings.");
        $this->info("Sent {$exceededSent} quota exceeded notifications.");
        
        return 0;
    }
}
