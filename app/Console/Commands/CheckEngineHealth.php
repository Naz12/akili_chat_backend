<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CheckEngineHealth extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'engines:check-health';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check health status of all AI engines';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking AI engine health...');
        
        $service = app(\App\Services\AIEngineHealthService::class);
        $results = $service->checkAllEngines();
        
        $healthyCount = 0;
        $unhealthyCount = 0;
        
        foreach ($results as $engineId => $health) {
            if ($health['is_healthy']) {
                $healthyCount++;
                $this->info("✅ {$health['engine_name']}: Healthy ({$health['response_time_ms']}ms)");
            } else {
                $unhealthyCount++;
                $this->error("❌ {$health['engine_name']}: Unhealthy - {$health['error']}");
            }
        }
        
        $this->info("Healthy: {$healthyCount}, Unhealthy: {$unhealthyCount}");
        
        return 0;
    }
}
