<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Subscription;

class DisableExpiredSubscriptions extends Command
{
    protected $signature = 'subscriptions:disable-expired';
    protected $description = 'Disable expired subscriptions automatically';

    public function handle(): int
    {
        $count = Subscription::where('is_active', true)
            ->whereDate('end_date', '<', now())
            ->update(['is_active' => false]);

        $this->info("✅ Disabled $count expired subscriptions.");

        return static::SUCCESS;
    }
}