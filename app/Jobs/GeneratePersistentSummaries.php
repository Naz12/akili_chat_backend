<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\PersistentSummary;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\AIChatSummaryService;

class GeneratePersistentSummaries implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $ai = app(AIChatSummaryService::class);
        $users = User::all(); // Can be chunked later for performance

        foreach ($users as $user) {
            $messages = $user->chatMessages()
                ->orderBy('created_at')
                ->limit(500) // Limit to prevent overload
                ->pluck('content')
                ->implode("\n");

            if (trim($messages) === '') {
                \Log::info("⛔ No messages for user #{$user->id}");
                continue;
            }

            \Log::info("🧠 Generating persistent summary for user #{$user->id}");

            $result = $ai->summarize($messages, [
                'type' => 'persistent',
                'user_id' => $user->id,
                'instructions' => 'Generate a timeless summary about the user: personality, profession, values, goals, tone, patterns, etc.',
            ]);

            PersistentSummary::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'topic'         => 'user_identity',
                    'summary_type'  => 'persistent',
                    'summary_text'  => $result['summary'],
                    'source'        => 'chat_history',
                    'tokens_used'   => $result['tokens_used'] ?? 0,
                ]
            );

            \Log::info("✅ Persistent summary saved for user #{$user->id}");
        }
    }
}