<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\WeeklySummary;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\AIChatSummaryService;

class GenerateWeeklySummaries implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $ai = app(AIChatSummaryService::class);

        $startOfWeek = Carbon::now()->startOfWeek();
        $endOfWeek = Carbon::now()->endOfWeek();
        $weekNumber = $startOfWeek->isoWeek();
        $year = $startOfWeek->year;

        User::chunk(50, function ($users) use ($ai, $startOfWeek, $endOfWeek, $weekNumber, $year) {
            foreach ($users as $user) {
                $messages = $user->chatMessages()
                    ->whereBetween('created_at', [$startOfWeek, $endOfWeek])
                    ->orderBy('created_at')
                    ->pluck('content')
                    ->implode("\n");

                if (trim($messages) === '') {
                    \Log::info("⛔ No weekly messages for user #{$user->id}");
                    continue;
                }

                \Log::info("🧠 Generating weekly summary for user #{$user->id}");

                $result = $ai->summarize($messages, [
                    'type' => 'weekly',
                    'user_id' => $user->id,
                    'instructions' => 'Summarize what the user discussed throughout the past week in a short paragraph.',
                ]);

                WeeklySummary::updateOrCreate(
                    [
                        'user_id'    => $user->id,
                        'week_start' => $startOfWeek->toDateString(),
                    ],
                    [
                        'week_end'     => $endOfWeek->toDateString(),
                        'week_number'  => $weekNumber,
                        'year'         => $year,
                        'summary_text' => $result['summary'],
                        'tokens_used'  => $result['tokens_used'] ?? 0,
                        'generated_by' => 'ai',
                    ]
                );

                \Log::info("✅ Weekly summary saved for user #{$user->id}");
            }
        });
    }
}