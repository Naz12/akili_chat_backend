<?php

namespace App\Services;

use Twilio\Rest\Client;
use Illuminate\Support\Facades\Log;

class SmsService
{
    public static function send($to, $message)
    {
        try {
            $sid = config('services.twilio.sid');
            $token = config('services.twilio.token');
            $from = config('services.twilio.from');

            $twilio = new Client($sid, $token);

            $twilio->messages->create($to, [
                'from' => $from,
                'body' => $message,
            ]);

            Log::info("✅ SMS sent to {$to}");
        } catch (\Exception $e) {
            Log::error("❌ Failed to send SMS: " . $e->getMessage());
            throw $e;
        }
    }
}