<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ChatSession;
use App\Traits\DetectsRegion;
use Illuminate\Support\Facades\Log;

class AiChatHistoryApiController extends Controller
{
    use DetectsRegion;

    /**
     * Get all chat sessions for the authenticated user or guest
     */
    public function sessions(Request $request)
    {
        try {
            $user = null;
            $guestSession = null;
            
            // Try to authenticate user from JWT token (route is not protected by auth:api middleware)
            // so we need to manually validate the token if present
            $authHeader = $request->header('Authorization');
            
            // Also check for token in other possible header formats (some proxies modify headers)
            if (!$authHeader || stripos($authHeader, 'Bearer') !== 0) {
                // Try alternative header names that some proxies use
                $authHeader = $request->header('X-Authorization') 
                    ?? $request->header('HTTP_AUTHORIZATION')
                    ?? $request->header('Authorization');
            }
            
            if ($authHeader) {
                try {
                    // Skip if it's Basic auth (browser default when header is malformed)
                    if (stripos($authHeader, 'Basic') === 0) {
                        Log::warning('Received Basic auth instead of Bearer token - header may be malformed', [
                            'auth_header_preview' => substr($authHeader, 0, 50),
                            'ip' => $request->ip(),
                            'user_agent' => $request->userAgent(),
                            'all_headers' => array_keys($request->headers->all()),
                        ]);
                        // Return 401 error - invalid authentication
                        return response()->json([
                            'error' => 'Invalid authentication header. Expected Bearer token, received Basic auth.',
                            'message' => 'Please ensure you are sending Authorization: Bearer {token} header'
                        ], 401);
                    } else {
                        // Extract token from "Bearer <token>" format
                        $token = preg_replace('/^Bearer\s+/i', '', $authHeader);
                        
                        // Log the actual header value (first 50 chars for debugging)
                        Log::debug('JWT token extraction attempt', [
                            'auth_header_preview' => substr($authHeader, 0, 50) . '...',
                            'auth_header_length' => strlen($authHeader),
                            'token_preview' => $token ? substr($token, 0, 50) . '...' : 'null',
                            'token_length' => $token ? strlen($token) : 0,
                            'token_starts_with_bearer' => stripos($authHeader, 'Bearer') === 0,
                        ]);
                        
                        if ($token && $token !== $authHeader && strlen($token) > 50) {
                            // Set the token and try to authenticate
                            \Tymon\JWTAuth\Facades\JWTAuth::setToken($token);
                            $user = \Tymon\JWTAuth\Facades\JWTAuth::authenticate();
                            
                            if ($user) {
                                Log::debug('JWT token validated successfully', [
                                    'user_id' => $user->id,
                                    'user_email' => $user->email,
                                    'token_length' => strlen($token),
                                ]);
                            }
                        } else {
                            Log::warning('JWT token extraction failed - token too short or invalid format', [
                                'auth_header_preview' => substr($authHeader, 0, 100),
                                'auth_header_length' => strlen($authHeader ?? ''),
                                'token_extracted' => $token ? 'yes' : 'no',
                                'token_length' => $token ? strlen($token) : 0,
                                'expected_length' => '300+ characters for valid JWT',
                                'issue' => strlen($token) <= 50 ? 'Token too short' : 'Token format invalid',
                            ]);
                        }
                    }
                } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
                    Log::warning('JWT Token expired for chat sessions request', [
                        'ip' => $request->ip(),
                        'error' => $e->getMessage(),
                    ]);
                    // Return 401 for expired token
                    return response()->json([
                        'error' => 'Token expired',
                        'message' => 'Please login again to get a new token'
                    ], 401);
                } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
                    Log::warning('JWT Token invalid for chat sessions request', [
                        'ip' => $request->ip(),
                        'error' => $e->getMessage(),
                    ]);
                    // Return 401 for invalid token
                    return response()->json([
                        'error' => 'Invalid token',
                        'message' => 'Please login again to get a valid token'
                    ], 401);
                } catch (\Exception $e) {
                    // Token validation failed - return 401
                    Log::warning('JWT token validation failed', [
                        'error' => $e->getMessage(),
                        'error_class' => get_class($e),
                        'token_preview' => substr($token ?? '', 0, 20) . '...',
                        'ip' => $request->ip(),
                    ]);
                    // Return 401 for any other token validation error
                    return response()->json([
                        'error' => 'Authentication failed',
                        'message' => 'Unable to validate token. Please login again.'
                    ], 401);
                }
            } else {
                Log::debug('No Authorization header found', [
                    'ip' => $request->ip(),
                    'all_headers' => array_keys($request->headers->all()),
                ]);
            }
            
            // Log for debugging
            Log::debug('Chat sessions request', [
                'has_user' => $user !== null,
                'user_id' => $user?->id,
                'user_email' => $user?->email,
                'has_auth_header' => $request->hasHeader('Authorization'),
                'ip' => $request->ip(),
            ]);
            
            // If Authorization header was present but user is not authenticated, return 401
            // This handles cases where token is invalid, expired, or malformed
            if ($authHeader && !$user) {
                Log::warning('Authorization header present but user not authenticated', [
                    'ip' => $request->ip(),
                    'auth_header_preview' => substr($authHeader, 0, 50),
                    'auth_header_length' => strlen($authHeader),
                ]);
                return response()->json([
                    'error' => 'Invalid or expired authentication token',
                    'message' => 'Please login again to get a valid token'
                ], 401);
            }
            
            // Support guest users (only if no Authorization header was sent)
            if (!$user && !$authHeader) {
                $guestUserService = app(\App\Services\GuestUserService::class);
                $guestSession = $guestUserService->getGuestSessionFromRequest($request);
                
                if (!$guestSession) {
                    // No user and no guest session - return empty array
                    Log::debug('No user and no guest session - returning empty array');
                    return response()->json([]);
                }
                
                Log::debug('Guest session found', [
                    'guest_session_id' => $guestSession->id,
                    'device_fingerprint' => substr($guestSession->device_fingerprint, 0, 10) . '...',
                ]);
            }
            
            $withMessages = $request->boolean('with_messages');
        
            $query = ChatSession::query();
            
            // Filter by user or guest session - CRITICAL: Only return sessions for the current user/guest
            if ($user) {
                // Authenticated user: Return sessions where user_id matches (including migrated guest sessions)
                // This ensures users see their own sessions, including those migrated from guest sessions
                $query->where('user_id', $user->id);
                
                Log::debug('Filtering sessions for authenticated user', [
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                ]);
            } elseif ($guestSession) {
                // Guest user: ONLY return sessions for this specific guest session
                // Must not have a user_id (not yet migrated) and must match this guest session
                $query->where('guest_session_id', $guestSession->id)
                      ->where(function ($q) {
                          $q->where('is_guest', true)
                            ->orWhereNull('user_id');
                      });
                
                Log::debug('Filtering sessions for guest session', [
                    'guest_session_id' => $guestSession->id,
                ]);
            } else {
                // This should not happen if logic above is correct
                // If Authorization header was sent but user is null, we should have returned 401 already
                Log::warning('No user and no guest session - unexpected state', [
                    'had_auth_header' => $authHeader !== null,
                    'ip' => $request->ip(),
                ]);
                // If auth header was present, return 401, otherwise empty array for guest
                if ($authHeader) {
                    return response()->json([
                        'error' => 'Authentication failed',
                        'message' => 'Unable to authenticate user'
                    ], 401);
                }
                return response()->json([]);
            }
            
            // Eager load messages if requested
            if ($withMessages) {
                $query->with(['messages' => function ($q) {
                    $q->select('id', 'chat_session_id', 'role', 'content', 'created_at')
                      ->orderBy('created_at');
                }]);
            }
            
            // Get limit from request or use default
            // Default: 50 for authenticated users, 10 for guests (to prevent performance issues)
            $defaultLimit = $user ? 50 : 10;
            $limit = $request->integer('limit', $defaultLimit);
            
            $query = $query->latest();
            
            // Apply limit
            if ($limit > 0) {
                $query->take($limit);
            }
            
            $sessions = $query->get()
                ->map(function ($session) use ($withMessages) {
                    $data = [
                        'id' => $session->id,
                        'title' => $session->title,
                        'created_at' => $session->created_at,
                        'is_guest' => $session->is_guest ?? false,
                    ];
                    
                    if ($withMessages && $session->relationLoaded('messages')) {
                        $data['messages'] = $session->messages;
                    }
                    
                    return $data;
                });
        
            Log::debug('Returning chat sessions', [
                'user_id' => $user?->id,
                'guest_session_id' => $guestSession?->id,
                'session_count' => $sessions->count(),
                'session_ids' => $sessions->pluck('id')->toArray(),
            ]);
        
            return response()->json($sessions);
        } catch (\Throwable $e) {
            Log::error('Chat sessions error', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['error' => 'Failed to fetch sessions: ' . $e->getMessage()], 500);
        }
    }
    

    /**
     * Get all messages for a given chat session (supports authenticated and guest users)
     */
    public function messages(Request $request, $sessionId)
    {
        try {
            $user = null;
            $guestSession = null;
            
            // Try to authenticate user from JWT token
            if ($request->hasHeader('Authorization')) {
                try {
                    $token = str_replace('Bearer ', '', $request->header('Authorization'));
                    if ($token && $token !== 'Bearer') {
                        \Tymon\JWTAuth\Facades\JWTAuth::setToken($token);
                        $user = \Tymon\JWTAuth\Facades\JWTAuth::authenticate();
                    }
                } catch (\Exception $e) {
                    // Token is invalid or expired, treat as guest
                    Log::debug('JWT token validation failed in messages', [
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            
            // Log authentication status for debugging
            Log::debug('Chat messages request', [
                'session_id' => $sessionId,
                'has_user' => $user !== null,
                'user_id' => $user?->id,
                'has_auth_header' => $request->hasHeader('Authorization'),
                'has_guest_uuid' => $request->hasHeader('X-Guest-UUID') || $request->hasCookie('guest_uuid'),
            ]);
            
            // Support guest users
            if (!$user) {
                $guestUserService = app(\App\Services\GuestUserService::class);
                $guestSession = $guestUserService->getGuestSessionFromRequest($request);
                
                if (!$guestSession) {
                    Log::warning('Unauthenticated request with no guest session', [
                        'session_id' => $sessionId,
                        'ip' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                    ]);
                    return response()->json([
                        'error' => 'Unauthenticated',
                        'message' => 'Please log in or provide a valid guest session identifier.',
                    ], 401);
                }
            }

            // First, check if session exists at all
            $session = ChatSession::where('id', $sessionId)->first();
            
            if (!$session) {
                Log::warning('Chat session not found', [
                    'user_id' => $user?->id,
                    'guest_session_id' => $guestSession?->id,
                    'session_id' => $sessionId,
                ]);
                return response()->json([
                    'error' => 'Session not found or access denied.',
                    'message' => 'The chat session you are trying to access does not exist or you do not have permission to view it.',
                ], 404);
            }
            
            // Check access permissions
            $hasAccess = false;
            
            if ($user) {
                // Authenticated user: can access their own sessions OR guest sessions
                // (Guest sessions might be from before they logged in and haven't been migrated yet)
                if ($session->user_id === $user->id) {
                    $hasAccess = true;
                } elseif ($session->is_guest && $session->guest_session_id) {
                    // Allow access to guest sessions for authenticated users
                    // This handles cases where sessions weren't migrated yet
                    $hasAccess = true;
                    Log::info('Authenticated user accessing guest session (will be migrated)', [
                        'user_id' => $user->id,
                        'session_id' => $sessionId,
                        'guest_session_id' => $session->guest_session_id,
                    ]);
                }
            } elseif ($guestSession) {
                // Guest user: only access their own guest sessions
                $hasAccess = ($session->guest_session_id === $guestSession->id);
            }
            
            if (!$hasAccess) {
                Log::warning('Chat session access denied', [
                    'user_id' => $user?->id,
                    'guest_session_id' => $guestSession?->id,
                    'session_id' => $sessionId,
                    'session_user_id' => $session->user_id,
                    'session_guest_session_id' => $session->guest_session_id,
                ]);
                return response()->json([
                    'error' => 'Session not found or access denied.',
                    'message' => 'The chat session you are trying to access does not exist or you do not have permission to view it.',
                ], 404);
            }

            if (!$session) {
                Log::warning('Chat session not found or access denied', [
                    'user_id' => $user?->id,
                    'guest_session_id' => $guestSession?->id,
                    'session_id' => $sessionId,
                    'session_exists' => ChatSession::where('id', $sessionId)->exists(),
                ]);
                return response()->json([
                    'error' => 'Session not found or access denied.',
                    'message' => 'The chat session you are trying to access does not exist or you do not have permission to view it.',
                ], 404);
            }

            $messages = $session->messages()
                ->orderBy('created_at')
                ->get(['id', 'role', 'content', 'created_at']);

            return response()->json($messages);
        } catch (\Throwable $e) {
            Log::error('Chat messages error', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'session_id' => $sessionId,
            ]);
            return response()->json(['error' => 'Failed to fetch messages: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Rename a session (supports authenticated and guest users)
     */
    public function rename(Request $request, $sessionId)
    {
        $user = null;
        $guestSession = null;
        
        // Try to authenticate user from JWT token
        if ($request->hasHeader('Authorization')) {
            try {
                $token = str_replace('Bearer ', '', $request->header('Authorization'));
                if ($token && $token !== 'Bearer') {
                    \Tymon\JWTAuth\Facades\JWTAuth::setToken($token);
                    $user = \Tymon\JWTAuth\Facades\JWTAuth::authenticate();
                }
            } catch (\Exception $e) {
                // Token is invalid or expired, treat as guest
            }
        }
        
        // Support guest users
        if (!$user) {
            $guestUserService = app(\App\Services\GuestUserService::class);
            $guestSession = $guestUserService->getGuestSessionFromRequest($request);
            
            if (!$guestSession) {
                return response()->json(['error' => 'Unauthenticated'], 401);
            }
        }

        $request->validate([
            'title' => ['required', 'string', 'max:100', function ($attribute, $value, $fail) {
                if (trim($value) !== $value) {
                    $fail('Title must not contain leading or trailing spaces.');
                }
            }],
        ]);

        $query = ChatSession::where('id', $sessionId);
        
        // Filter by user or guest session
        if ($user) {
            $query->where('user_id', $user->id);
        } elseif ($guestSession) {
            $query->where('guest_session_id', $guestSession->id);
        }
        
        $session = $query->first();

        if (!$session) {
            return response()->json(['message' => 'Session not found.'], 404);
        }

        $session->update(['title' => $request->title]);

        return response()->json([
            'id' => $session->id,
            'title' => $session->title,
            'updated_at' => $session->updated_at,
        ]);
    }

    /**
     * Delete a session and its related messages (supports authenticated and guest users)
     */
    public function destroy(Request $request, $sessionId)
    {
        $user = null;
        $guestSession = null;
        
        // Try to authenticate user from JWT token
        if ($request->hasHeader('Authorization')) {
            try {
                $token = str_replace('Bearer ', '', $request->header('Authorization'));
                if ($token && $token !== 'Bearer') {
                    \Tymon\JWTAuth\Facades\JWTAuth::setToken($token);
                    $user = \Tymon\JWTAuth\Facades\JWTAuth::authenticate();
                }
            } catch (\Exception $e) {
                // Token is invalid or expired, treat as guest
            }
        }
        
        // Support guest users
        if (!$user) {
            $guestUserService = app(\App\Services\GuestUserService::class);
            $guestSession = $guestUserService->getGuestSessionFromRequest($request);
            
            if (!$guestSession) {
                return response()->json(['error' => 'Unauthenticated'], 401);
            }
        }

        $query = ChatSession::where('id', $sessionId);
        
        // Filter by user or guest session
        if ($user) {
            $query->where('user_id', $user->id);
        } elseif ($guestSession) {
            $query->where('guest_session_id', $guestSession->id);
        }
        
        $session = $query->first();

        if (!$session) {
            return response()->json(['message' => 'Session not found.'], 404);
        }

        $session->messages()->delete();
        $session->delete();

        Log::info('🗑️ Chat session deleted', [
            'user_id' => $user?->id,
            'guest_session_id' => $guestSession?->id,
            'session_id' => $sessionId,
        ]);

        return response()->json(['message' => 'Session deleted']);
    }
}