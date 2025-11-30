<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class HandlePaymentFailures extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:handle-payment-failures';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process expired grace periods and downgrade subscriptions to free plan';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Processing expired grace periods...');
        
        $service = app(\App\Services\PaymentFailureService::class);
        $results = $service->processExpiredGracePeriods();
        
        $this->info("Processed: {$results['processed']}");
        $this->info("Downgraded: {$results['downgraded']}");
        $this->info("Errors: {$results['errors']}");
        
        return 0;
    }
}
