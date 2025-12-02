<?php

namespace App\Services;

use App\Models\GuestSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class GuestUserService
{
    /**
     * Get or create a guest session based on device fingerprint
     */
    public function getOrCreateGuestSession(Request $request): GuestSession
    {
        $fingerprint = $this->generateDeviceFingerprint($request);
        
        // Try to find existing non-expired session
        $guestSession = GuestSession::where('device_fingerprint', $fingerprint)
            ->where('expires_at', '>', now())
            ->first();
        
        if ($guestSession) {
            return $guestSession;
        }
        
        // Create new guest session (configurable via system settings)
        $expirationHours = \App\Models\SystemSetting::getValue('guest.session_expiration_hours', 24);
        $guestSession = GuestSession::create([
            'device_fingerprint' => $fingerprint,
            'expires_at' => now()->addHours($expirationHours),
            'metadata' => [
                'user_agent' => $request->userAgent(),
                'ip' => $request->ip(),
                'created_at' => now()->toIso8601String(),
            ],
        ]);
        
        return $guestSession;
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
     * Get guest session by fingerprint
     */
    public function getGuestSessionByFingerprint(string $fingerprint): ?GuestSession
    {
        return GuestSession::where('device_fingerprint', $fingerprint)
            ->where('expires_at', '>', now())
            ->first();
    }

    /**
     * Get guest session from request (helper method)
     */
    public function getGuestSessionFromRequest(Request $request): ?GuestSession
    {
        $fingerprint = $this->generateDeviceFingerprint($request);
        return $this->getGuestSessionByFingerprint($fingerprint);
    }

    /**
     * Clean up expired guest sessions
     */
    public function cleanupExpiredSessions(): int
    {
        return GuestSession::where('expires_at', '<=', now())->delete();
    }
}

