<?php

namespace App\Providers;

use App\Models\User;
use App\Observers\UserObserver;
use Illuminate\Support\ServiceProvider;
use Illuminate\Console\Scheduling\Schedule;


class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register User Observer to assign free plan on user creation
        User::observe(UserObserver::class);
        
        // Scheduled tasks are defined in routes/console.php
        // This ensures the scheduler is booted
        $schedule = app(Schedule::class);
        
        // Ensure expired subscriptions are disabled daily
        $schedule->command('subscriptions:disable-expired')
            ->daily();
    }
}