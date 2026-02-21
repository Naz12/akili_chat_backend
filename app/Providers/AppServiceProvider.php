<?php

namespace App\Providers;

use App\Models\User;
use App\Observers\UserObserver;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;

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

        // Ensure generated files storage exists (presentations, diagrams) so downloads work
        $private = storage_path('app/private');
        if (! is_dir($private)) {
            @mkdir($private, 0755, true);
        }
        foreach (['presentations', 'diagrams'] as $sub) {
            $dir = $private . DIRECTORY_SEPARATOR . $sub;
            if (! is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
        }

        // Tool routes (PPT, diagram, doc-converter) are in routes/api.php

        // Scheduled tasks are defined in routes/console.php
        $schedule = app(Schedule::class);

        // Ensure expired subscriptions are disabled daily
        $schedule->command('subscriptions:disable-expired')
            ->daily();
    }

}