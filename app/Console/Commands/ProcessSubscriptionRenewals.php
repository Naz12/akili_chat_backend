<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ProcessSubscriptionRenewals extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:process-renewals {--days=3 : Number of days ahead to check for expiring subscriptions}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process subscription renewals for subscriptions expiring soon';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = (int) $this->option('days');
        
        $this->info("Processing subscription renewals for subscriptions expiring in {$days} days...");
        
        $service = app(\App\Services\SubscriptionRenewalService::class);
        $results = $service->processRenewals($days);
        
        $this->info("Processed: {$results['processed']}");
        $this->info("Successful: {$results['successful']}");
        $this->info("Failed: {$results['failed']}");
        $this->info("Skipped: {$results['skipped']}");
        
        return 0;
    }
}
