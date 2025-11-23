<?php

namespace App\Providers;

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
        //
        app(Schedule::class)
        ->command('subscriptions:disable-expired')
        ->daily(); // or use ->hourly(), ->everyTenMinutes(), etc.
    }
}