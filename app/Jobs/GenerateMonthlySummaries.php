<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\MonthlySummary;
use App\Services\AIChatSummaryService;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateMonthlySummaries implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $startDate = Carbon::now()->startOfMonth();
        $month = $startDate->format('Y-m');
        $year = $startDate->year;

        $ai = app(AIChatSummaryService::class);

        \Log::info("📅 [MonthlySummary] Starting summary generation for month: $month");

        User::chunk(50, function ($users) use ($ai, $startDate, $month, $year) {
            foreach ($users as $user) {
                $messages = $user->chatMessages()
                    ->where('created_at', '>=', $startDate)
                    ->orderBy('created_at')
                    ->pluck('content')
                    ->implode("\n");

                if (trim($messages) === '') {
                    \Log::info("⛔ No monthly messages for user #{$user->id}");
                    continue;
                }

                \Log::info("🧠 Generating monthly summary for user #{$user->id}");

                $result = $ai->summarize($messages, [
                    'type' => 'monthly',
                    'user_id' => $user->id,
                    'instructions' => 'Summarize what the user discussed over the past month in a thoughtful, concise manner.',
                ]);

                MonthlySummary::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'month'   => $month,
                    ],
                    [
                        'year'          => $year,
                        'summary_text'  => $result['summary'],
                        'tokens_used'   => $result['tokens_used'] ?? 0,
                        'generated_by'  => 'ai',
                    ]
                );

                \Log::info("✅ Monthly summary saved for user #{$user->id}");
            }
        });

        \Log::info("🏁 [MonthlySummary] Job completed.");
    }
}