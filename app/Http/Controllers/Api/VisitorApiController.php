<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Visitor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use App\Traits\AddsCorsHeaders;
use Tymon\JWTAuth\Facades\JWTAuth;

class VisitorApiController extends Controller
{
    use AddsCorsHeaders;

    /**
     * Track a visitor/page view
     * This endpoint should be called on every page load from the frontend
     */
    public function track(Request $request)
    {
        try {
            // Get or create session ID
            $sessionId = $request->header('X-Session-ID') 
                ?? $request->input('session_id')
                ?? $this->generateSessionId();

            // Try to authenticate user from JWT token (route is public, so we manually check)
            $user = null;
            $userId = null;
            $authHeader = $request->header('Authorization');
            
            if ($authHeader && stripos($authHeader, 'Bearer') === 0) {
                try {
                    $token = preg_replace('/^Bearer\s+/i', '', $authHeader);
                    if ($token && $token !== $authHeader && strlen($token) > 50) {
                        JWTAuth::setToken($token);
                        $user = JWTAuth::authenticate();
                        if ($user) {
                            $userId = $user->id;
                        }
                    }
                } catch (\Exception $e) {
                    // Token is invalid or expired, treat as guest
                    Log::debug('JWT token validation failed in visitor tracking', [
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            
            // Also check metadata from frontend (fallback)
            $metadata = $request->input('metadata', []);
            if (!$userId && isset($metadata['is_authenticated']) && $metadata['is_authenticated'] && isset($metadata['user_id'])) {
                $userId = $metadata['user_id'];
                $user = \App\Models\User::find($userId);
            }
            
            // Log authentication status for debugging
            Log::debug('Visitor tracking authentication check', [
                'has_auth_header' => !empty($authHeader),
                'user_id' => $userId,
                'is_logged_in' => $userId !== null,
                'metadata_is_authenticated' => $metadata['is_authenticated'] ?? null,
                'metadata_user_id' => $metadata['user_id'] ?? null,
            ]);

            // Get IP address
            $ipAddress = $request->ip() 
                ?? $request->header('X-Forwarded-For')
                ?? $request->header('X-Real-IP')
                ?? 'unknown';

            // Parse user agent
            $userAgent = $request->header('User-Agent', '');
            $deviceInfo = $this->parseUserAgent($userAgent);

            // Get location data from request or IP geolocation
            $locationData = $this->getLocationData($request, $ipAddress);

            // Get UTM parameters
            $utmData = $this->getUtmData($request);

            // Get screen resolution and other client data
            $clientData = $request->only([
                'screen_resolution',
                'language',
                'timezone',
                'referrer',
            ]);

            // Check if visitor exists
            $visitor = Visitor::where('session_id', $sessionId)->first();

            if ($visitor) {
                // Update existing visitor
                $existingMetadata = $visitor->metadata ?? [];
                $newMetadata = array_merge($existingMetadata, $request->input('metadata', []), [
                    'is_authenticated' => $userId !== null,
                    'user_id' => $userId,
                    'user_email' => $user?->email,
                ]);
                
                $visitor->update([
                    'user_id' => $userId ?? $visitor->user_id, // Update if user logged in
                    'is_logged_in' => $userId !== null,
                    'page_views' => $visitor->page_views + 1,
                    'last_visit_at' => now(),
                    'last_activity_at' => now(),
                    'session_duration' => $visitor->first_visit_at->diffInSeconds(now()),
                    // Update location if changed (prefer new data, fallback to existing)
                    'country' => $locationData['country'] ?? $visitor->country,
                    'country_name' => $locationData['country_name'] ?? $visitor->country_name,
                    'region' => $locationData['region'] ?? $visitor->region,
                    'city' => $locationData['city'] ?? $visitor->city,
                    'latitude' => $locationData['latitude'] ?? $visitor->latitude,
                    'longitude' => $locationData['longitude'] ?? $visitor->longitude,
                    'timezone' => $locationData['timezone'] ?? $visitor->timezone,
                    'metadata' => $newMetadata,
                ]);
            } else {
                // Create new visitor
                $visitor = Visitor::create([
                    'session_id' => $sessionId,
                    'user_id' => $userId,
                    'ip_address' => $ipAddress,
                    'user_agent' => $userAgent,
                    'device_type' => $deviceInfo['device_type'],
                    'browser' => $deviceInfo['browser'],
                    'browser_version' => $deviceInfo['browser_version'],
                    'os' => $deviceInfo['os'],
                    'os_version' => $deviceInfo['os_version'],
                    'is_mobile' => $deviceInfo['is_mobile'],
                    'is_tablet' => $deviceInfo['is_tablet'],
                    'is_desktop' => $deviceInfo['is_desktop'],
                    'is_bot' => $deviceInfo['is_bot'],
                    'is_logged_in' => $userId !== null,
                    'country' => $locationData['country'] ?? null,
                    'country_name' => $locationData['country_name'] ?? null,
                    'region' => $locationData['region'] ?? null,
                    'city' => $locationData['city'] ?? null,
                    'latitude' => $locationData['latitude'] ?? null,
                    'longitude' => $locationData['longitude'] ?? null,
                    'timezone' => $clientData['timezone'] ?? $locationData['timezone'] ?? null,
                    'language' => $clientData['language'] ?? $request->header('Accept-Language'),
                    'referrer' => $clientData['referrer'] ?? $request->header('Referer'),
                    'utm_source' => $utmData['utm_source'] ?? null,
                    'utm_medium' => $utmData['utm_medium'] ?? null,
                    'utm_campaign' => $utmData['utm_campaign'] ?? null,
                    'utm_term' => $utmData['utm_term'] ?? null,
                    'utm_content' => $utmData['utm_content'] ?? null,
                    'screen_resolution' => $clientData['screen_resolution'] ?? null,
                    'page_views' => 1,
                    'first_visit_at' => now(),
                    'last_visit_at' => now(),
                    'last_activity_at' => now(),
                    'metadata' => array_merge($request->input('metadata', []), [
                        'is_authenticated' => $userId !== null,
                        'user_id' => $userId,
                        'user_email' => $user?->email,
                    ]),
                ]);
            }

            $response = response()->json([
                'success' => true,
                'session_id' => $visitor->session_id,
                'message' => 'Visitor tracked successfully',
            ], 200);

            return $this->addCorsHeaders($response, $request);
        } catch (\Exception $e) {
            Log::error('Visitor tracking failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $response = response()->json([
                'success' => false,
                'message' => 'Failed to track visitor',
            ], 500);

            return $this->addCorsHeaders($response, $request);
        }
    }

    /**
     * Update visitor activity (for tracking session duration)
     */
    public function updateActivity(Request $request)
    {
        try {
            $sessionId = $request->header('X-Session-ID') 
                ?? $request->input('session_id');

            if (!$sessionId) {
                return response()->json(['success' => false, 'message' => 'Session ID required'], 400);
            }

            // Try to authenticate user from JWT token
            $user = null;
            $userId = null;
            $authHeader = $request->header('Authorization');
            
            if ($authHeader && stripos($authHeader, 'Bearer') === 0) {
                try {
                    $token = preg_replace('/^Bearer\s+/i', '', $authHeader);
                    if ($token && $token !== $authHeader && strlen($token) > 50) {
                        JWTAuth::setToken($token);
                        $user = JWTAuth::authenticate();
                        if ($user) {
                            $userId = $user->id;
                        }
                    }
                } catch (\Exception $e) {
                    // Token is invalid or expired, treat as guest
                }
            }

            $visitor = Visitor::where('session_id', $sessionId)->first();

            if ($visitor) {
                // Update user info if authenticated
                $updateData = [
                    'last_activity_at' => now(),
                    'session_duration' => $visitor->first_visit_at->diffInSeconds(now()),
                ];
                
                // Update user info if logged in
                if ($userId) {
                    $updateData['user_id'] = $userId;
                    $updateData['is_logged_in'] = true;
                }
                
                $visitor->update($updateData);
            }

            $response = response()->json(['success' => true], 200);
            return $this->addCorsHeaders($response, $request);
        } catch (\Exception $e) {
            Log::error('Activity update failed', ['error' => $e->getMessage()]);
            $response = response()->json(['success' => false], 500);
            return $this->addCorsHeaders($response, $request);
        }
    }

    /**
     * Generate unique session ID
     */
    protected function generateSessionId(): string
    {
        return 'visitor_' . Str::random(32) . '_' . time();
    }

    /**
     * Parse user agent to extract device/browser info
     */
    protected function parseUserAgent(string $userAgent): array
    {
        $isMobile = preg_match('/Mobile|Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i', $userAgent);
        $isTablet = preg_match('/Tablet|iPad/i', $userAgent);
        $isDesktop = !$isMobile && !$isTablet;
        $isBot = preg_match('/bot|crawler|spider|crawling/i', $userAgent);

        // Detect browser
        $browser = 'Unknown';
        $browserVersion = null;
        if (preg_match('/Chrome\/(\d+)/i', $userAgent, $matches)) {
            $browser = 'Chrome';
            $browserVersion = $matches[1];
        } elseif (preg_match('/Firefox\/(\d+)/i', $userAgent, $matches)) {
            $browser = 'Firefox';
            $browserVersion = $matches[1];
        } elseif (preg_match('/Safari\/(\d+)/i', $userAgent, $matches) && !preg_match('/Chrome/i', $userAgent)) {
            $browser = 'Safari';
            $browserVersion = $matches[1];
        } elseif (preg_match('/Edge\/(\d+)/i', $userAgent, $matches)) {
            $browser = 'Edge';
            $browserVersion = $matches[1];
        } elseif (preg_match('/Opera\/(\d+)/i', $userAgent, $matches)) {
            $browser = 'Opera';
            $browserVersion = $matches[1];
        }

        // Detect OS
        $os = 'Unknown';
        $osVersion = null;
        if (preg_match('/Windows NT (\d+\.\d+)/i', $userAgent, $matches)) {
            $os = 'Windows';
            $osVersion = $matches[1];
        } elseif (preg_match('/Mac OS X (\d+[._]\d+)/i', $userAgent, $matches)) {
            $os = 'macOS';
            $osVersion = str_replace('_', '.', $matches[1]);
        } elseif (preg_match('/Linux/i', $userAgent)) {
            $os = 'Linux';
        } elseif (preg_match('/Android (\d+\.\d+)/i', $userAgent, $matches)) {
            $os = 'Android';
            $osVersion = $matches[1];
        } elseif (preg_match('/iPhone OS (\d+[._]\d+)/i', $userAgent, $matches)) {
            $os = 'iOS';
            $osVersion = str_replace('_', '.', $matches[1]);
        }

        // Determine device type
        $deviceType = 'desktop';
        if ($isMobile) $deviceType = 'mobile';
        elseif ($isTablet) $deviceType = 'tablet';

        return [
            'device_type' => $deviceType,
            'browser' => $browser,
            'browser_version' => $browserVersion,
            'os' => $os,
            'os_version' => $osVersion,
            'is_mobile' => $isMobile,
            'is_tablet' => $isTablet,
            'is_desktop' => $isDesktop,
            'is_bot' => $isBot,
        ];
    }

    /**
     * Get location data from request or IP geolocation
     */
    protected function getLocationData(Request $request, string $ipAddress): array
    {
        // First, try to get from request (frontend can send this)
        $locationData = $request->only([
            'country',
            'country_name',
            'region',
            'city',
            'latitude',
            'longitude',
            'timezone',
        ]);

        // If location data is missing, try IP geolocation
        if (empty($locationData['country']) && $ipAddress && $ipAddress !== 'unknown' && $ipAddress !== '127.0.0.1') {
            $ipGeoData = $this->getLocationFromIp($ipAddress);
            if ($ipGeoData) {
                $locationData = array_merge($locationData, $ipGeoData);
            }
        }

        return array_filter($locationData);
    }

    /**
     * Get location data from IP address using ip-api.com (free service)
     * Results are cached for 24 hours to reduce API calls
     * 
     * @param string $ipAddress
     * @return array|null
     */
    protected function getLocationFromIp(string $ipAddress): ?array
    {
        try {
            // Skip localhost and private IPs
            if (in_array($ipAddress, ['127.0.0.1', '::1', 'localhost', 'unknown']) || 
                preg_match('/^(10\.|172\.(1[6-9]|2[0-9]|3[01])\.|192\.168\.)/', $ipAddress)) {
                return null;
            }

            // Check cache first (24 hour cache)
            $cacheKey = "ip_geo_{$ipAddress}";
            $cachedData = Cache::get($cacheKey);
            if ($cachedData !== null) {
                return $cachedData;
            }

            // Use ip-api.com free service (no API key required, 45 requests/minute limit)
            // For production, consider using a paid service for better reliability
            $url = "http://ip-api.com/json/{$ipAddress}?fields=status,message,country,countryCode,region,regionName,city,lat,lon,timezone,query";
            
            $response = Http::timeout(3)->get($url);
            
            if ($response->successful()) {
                $data = $response->json();
                
                if (isset($data['status']) && $data['status'] === 'success') {
                    $locationData = [
                        'country' => $data['countryCode'] ?? null,
                        'country_name' => $data['country'] ?? null,
                        'region' => $data['regionName'] ?? null,
                        'city' => $data['city'] ?? null,
                        'latitude' => $data['lat'] ?? null,
                        'longitude' => $data['lon'] ?? null,
                        'timezone' => $data['timezone'] ?? null,
                    ];
                    
                    // Cache for 24 hours
                    Cache::put($cacheKey, $locationData, now()->addHours(24));
                    
                    return $locationData;
                }
            }
        } catch (\Exception $e) {
            // Log error but don't fail the request
            Log::debug('IP geolocation failed', [
                'ip' => $ipAddress,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Get UTM parameters from request
     */
    protected function getUtmData(Request $request): array
    {
        return $request->only([
            'utm_source',
            'utm_medium',
            'utm_campaign',
            'utm_term',
            'utm_content',
        ]);
    }
}
