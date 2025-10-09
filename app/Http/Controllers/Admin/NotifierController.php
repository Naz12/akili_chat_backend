<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use App\Services\SmsService;
use Illuminate\Http\Request;
use App\Models\NotificationLog;
use App\Mail\GenericMarketingMail;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

class NotifierController extends Controller
{
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

        $channels = $request->channels;
        $users = $this->getUsersFromRequest($request->user_ids);

        foreach ($users as $user) {
            $pref = $user->preference;

            if (!$pref) continue;

            // 📧 Email
            if (in_array('email', $channels) && $pref->allow_marketing_email) {
                Mail::to($user->email)->send(new GenericMarketingMail($request->subject, $request->message));

                NotificationLog::create([
                    'user_id' => $user->id,
                    'channel' => 'email',
                    'content' => $request->subject,
                ]);
            }

            // 🔔 Push
            if (in_array('push', $channels) && $pref->allow_push_notifications && $user->fcm_token) {
                $this->sendFCM($user->fcm_token, $request->subject, $request->message);

                NotificationLog::create([
                    'user_id' => $user->id,
                    'channel' => 'push',
                    'content' => $request->subject,
                ]);
            }

            // 📲 SMS
            if (in_array('sms', $channels) && $pref->allow_sms && $user->phone) {
                SmsService::send($user->phone, $request->message);

                NotificationLog::create([
                    'user_id' => $user->id,
                    'channel' => 'sms',
                    'content' => $request->message,
                ]);
            }
        }

        return back()->with('success', '✅ Notifications sent successfully.');
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