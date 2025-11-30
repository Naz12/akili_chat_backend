<?php

use Illuminate\Console\Scheduling\Schedule;
use App\Jobs\GenerateDailySummaries;
use App\Jobs\GenerateWeeklySummaries;
use App\Jobs\GenerateMonthlySummaries;
use App\Jobs\GeneratePersistentSummaries;

return function (Schedule $schedule) {

    // 🕐 Daily summary at 11:55 PM
    // $schedule->job(new GenerateDailySummaries)->dailyAt('23:55');

    $schedule->job(new GenerateDailySummaries)->everyMinute();

    // 📅 Weekly summary every Sunday at 11:00 PM
    $schedule->job(new GenerateWeeklySummaries)->weeklyOn(7, '23:00');
    // $schedule->job(new GenerateWeeklySummaries)->everyMinute();

    // 📆 Monthly summary on the 1st day at 11:30 PM
    $schedule->job(new GenerateMonthlySummaries)->monthlyOn(1, '23:30');

    // 🧠 Persistent summary on 1st day at 00:10 AM (right after midnight)
    $schedule->job(new GeneratePersistentSummaries)->monthlyOn(1, '00:10');

    // 🔄 Process subscription renewals daily at 2 AM
    $schedule->command('subscriptions:process-renewals --days=3')
        ->dailyAt('02:00')
        ->name('process-subscription-renewals')
        ->withoutOverlapping();

    // ⚠️ Handle payment failures hourly
    $schedule->command('subscriptions:handle-payment-failures')
        ->hourly()
        ->name('handle-payment-failures')
        ->withoutOverlapping();

    // 📧 Send expiration warnings daily at 9 AM
    $schedule->command('subscriptions:send-expiration-warnings')
        ->dailyAt('09:00')
        ->name('send-expiration-warnings')
        ->withoutOverlapping();

    // 📊 Check usage quotas daily at 10 AM
    $schedule->command('usage:check-quotas')
        ->dailyAt('10:00')
        ->name('check-usage-quotas')
        ->withoutOverlapping();

    // 🏥 Check AI engine health hourly
    $schedule->command('engines:check-health')
        ->hourly()
        ->name('check-engine-health')
        ->withoutOverlapping();
};