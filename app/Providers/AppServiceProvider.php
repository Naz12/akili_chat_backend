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
        
        app(Schedule::class)
        ->command('subscriptions:disable-expired')
        ->daily(); // or use ->hourly(), ->everyTenMinutes(), etc.
    }
}