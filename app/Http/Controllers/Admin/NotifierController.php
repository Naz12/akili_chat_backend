<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use App\Services\SmsService;
use App\Services\Notification\NotificationService;
use App\Notifications\AdminBroadcastNotification;
use Illuminate\Http\Request;
use App\Models\NotificationLog;
use App\Mail\GenericMarketingMail;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class NotifierController extends Controller
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }
    public function sendEmail(Request $request)
    {
        $request->validate([
            'user_ids' => 'required|array',
            'subject' => 'required|string',
            'message' => 'required|string',
        ]);

        $users = $this->getUsersFromRequest($request->user_ids);

        foreach ($users as $user) {
            if ($user->preference?->allow_marketing_email) {
                Mail::to($user->email)->send(new GenericMarketingMail($request->subject, $request->message));

                NotificationLog::create([
                    'user_id' => $user->id,
                    'channel' => 'email',
                    'content' => $request->subject,
                ]);
            }
        }

        return back()->with('success', '✅ Emails sent.');
    }

    public function sendPush(Request $request)
    {
        $request->validate([
            'user_ids' => 'required|array',
            'title' => 'required|string',
            'body' => 'required|string',
        ]);

        $users = $this->getUsersFromRequest($request->user_ids);

        foreach ($users as $user) {
            if ($user->preference?->allow_push_notifications && $user->fcm_token) {
                $this->sendFCM($user->fcm_token, $request->title, $request->body);

                NotificationLog::create([
                    'user_id' => $user->id,
                    'channel' => 'push',
                    'content' => $request->title,
                ]);
            }
        }

        return back()->with('success', '✅ Push notifications sent.');
    }

    public function broadcast(Request $request)
    {
        $request->validate([
            'user_ids' => 'required|array',
            'channels' => 'required|array',
            'subject' => 'required|string',
            'message' => 'required|string',
        ]);

        $requestChannels = $request->channels;
        $users = $this->getUsersFromRequest($request->user_ids);

        // Map request channel names to NotificationService channel constants
        $notificationChannels = [];
        foreach ($requestChannels as $channel) {
            $notificationChannels[] = match($channel) {
                'email' => NotificationService::CHANNEL_EMAIL,
                'push' => NotificationService::CHANNEL_PUSH,
                'sms' => NotificationService::CHANNEL_SMS,
                'websocket' => NotificationService::CHANNEL_WEBSOCKET,
                'webpush' => NotificationService::CHANNEL_WEBPUSH,
                'database' => NotificationService::CHANNEL_DATABASE,
                default => null,
            };
        }
        $notificationChannels = array_filter($notificationChannels);

        // If no valid channels, add database as default
        if (empty($notificationChannels)) {
            $notificationChannels = [NotificationService::CHANNEL_DATABASE];
        }

        $successCount = 0;
        $errorCount = 0;

        foreach ($users as $user) {
            try {
                $notification = new AdminBroadcastNotification(
                    $request->subject,
                    $request->message,
                    $notificationChannels
                );

                $results = $this->notificationService->send($user, $notification, $notificationChannels, true);

                // Check if at least one channel succeeded
                $hasSuccess = false;
                foreach ($results as $channel => $result) {
                    if (isset($result['success']) && $result['success']) {
                        $hasSuccess = true;
                        break;
                    }
                }

                if ($hasSuccess) {
                    $successCount++;
                } else {
                    $errorCount++;
                    Log::warning('Admin broadcast failed for user', [
                        'user_id' => $user->id,
                        'channels' => $notificationChannels,
                        'results' => $results,
                    ]);
                }
            } catch (\Exception $e) {
                $errorCount++;
                Log::error('Admin broadcast exception', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        $message = "✅ Notifications sent to {$successCount} user(s)";
        if ($errorCount > 0) {
            $message .= ", {$errorCount} failed";
        }

        return back()->with('success', $message);
    }

    protected function sendFCM($token, $title, $body)
    {
        Http::withHeaders([
            'Authorization' => 'key=' . config('services.fcm.server_key'),
            'Content-Type' => 'application/json',
        ])->post('https://fcm.googleapis.com/fcm/send', [
            'to' => $token,
            'notification' => ['title' => $title, 'body' => $body],
        ]);
    }

    /**
     * Handle "all" vs selected user_ids.
     */
    private function getUsersFromRequest(array $userIds)
    {
        if (in_array('all', $userIds)) {
            return User::with('preference')->get();
        }

        return User::with('preference')->whereIn('id', $userIds)->get();
    }
}