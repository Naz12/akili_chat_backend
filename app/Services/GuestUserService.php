<?php

namespace App\Services;

use App\Models\GuestSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class GuestUserService
{
    /**
     * Get or create a guest session based on device fingerprint.
     * If a session with this fingerprint already exists (even expired), we reuse and extend it to avoid duplicate key errors.
     */
    public function getOrCreateGuestSession(Request $request): GuestSession
    {
        $fingerprint = $this->generateDeviceFingerprint($request);
        $expirationHours = (int) \App\Models\SystemSetting::getValue('guest.session_expiration_hours', 24);
        $expiresAt = now()->addHours($expirationHours);
        $metadata = [
            'user_agent' => $request->userAgent(),
            'ip' => $request->ip(),
            'created_at' => now()->toIso8601String(),
        ];

        // Try to find existing session (including expired) to avoid duplicate key on concurrent or retry requests
        $guestSession = GuestSession::where('device_fingerprint', $fingerprint)->first();
        if ($guestSession) {
            $guestSession->update([
                'expires_at' => $expiresAt,
                'metadata' => array_merge($guestSession->metadata ?? [], $metadata),
            ]);
            return $guestSession->fresh();
        }

        return GuestSession::create([
            'device_fingerprint' => $fingerprint,
            'expires_at' => $expiresAt,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Generate device fingerprint from request
     */
    public function generateDeviceFingerprint(Request $request): string
    {
        // Check if client provided a UUID (stored in cookie or header)
        $clientUuid = $request->cookie('guest_uuid') 
            ?? $request->header('X-Guest-UUID')
            ?? $request->input('guest_uuid');
        
        if ($clientUuid) {
            return hash('sha256', $clientUuid);
        }
        
        // Generate fingerprint from request data
        $data = [
            $request->userAgent() ?? '',
            $request->ip() ?? '',
            $request->header('Accept-Language') ?? '',
        ];
        
        return hash('sha256', implode('|', $data));
    }

    /**
     * Get guest session by fingerprint (only non-expired).
     */
    public function getGuestSessionByFingerprint(string $fingerprint): ?GuestSession
    {
        return GuestSession::where('device_fingerprint', $fingerprint)
            ->where('expires_at', '>', now())
            ->first();
    }

    /**
     * Get guest session from request (only non-expired). Does not create or extend.
     */
    public function getGuestSessionFromRequest(Request $request): ?GuestSession
    {
        $fingerprint = $this->generateDeviceFingerprint($request);
        return $this->getGuestSessionByFingerprint($fingerprint);
    }

    /**
     * Get guest session from request, or extend if found but expired (e.g. for GET sessions/messages).
     * Does not create a new guest; use getOrCreateGuestSession for that (e.g. on first POST /chat).
     */
    public function getOrExtendGuestSessionFromRequest(Request $request): ?GuestSession
    {
        $fingerprint = $this->generateDeviceFingerprint($request);
        $guestSession = GuestSession::where('device_fingerprint', $fingerprint)->first();
        if (!$guestSession) {
            return null;
        }
        $expirationHours = (int) \App\Models\SystemSetting::getValue('guest.session_expiration_hours', 24);
        $expiresAt = now()->addHours($expirationHours);
        $metadata = array_merge($guestSession->metadata ?? [], [
            'user_agent' => $request->userAgent(),
            'ip' => $request->ip(),
            'last_extended_at' => now()->toIso8601String(),
        ]);
        $guestSession->update(['expires_at' => $expiresAt, 'metadata' => $metadata]);
        return $guestSession->fresh();
    }

    /**
     * Clean up expired guest sessions
     */
    public function cleanupExpiredSessions(): int
    {
        return GuestSession::where('expires_at', '<=', now())->delete();
    }
}

