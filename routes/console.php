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
};