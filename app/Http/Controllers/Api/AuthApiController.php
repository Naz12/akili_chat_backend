<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Http\Controllers\Controller;
use App\Traits\AddsCorsHeaders;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Notifications\ForgotPasswordNotification;
use App\Services\Notification\NotificationService;


class AuthApiController extends Controller
{
    use AddsCorsHeaders;

    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }
    public function login(Request $request)
    {
        try {
            $credentials = $request->only('email', 'password');

            Log::info('🔐 Login attempt', $credentials);

            $user = \App\Models\User::where('email', $request->email)->first();
            $match = $user ? \Hash::check($request->password, $user->password) : false;

            Log::info('🧠 Found user?', ['found' => (bool) $user]);
            Log::info('🔑 Password match?', ['match' => $match]);
            Log::info('🔐 Attempting JWT login...');

            if (!$token = auth('api')->attempt($credentials)) {
                Log::warning('❌ JWT login failed');
                $response = response()->json(['error' => 'Unauthorized'], 401);
                return $this->addCorsHeaders($response, $request);
            }

            Log::info('✅ JWT login success');

            // Migrate guest sessions to user account on login
            try {
                $migratedCount = \App\Listeners\MigrateGuestSessionsOnSignup::migrateGuestSessionsForUser($user, $request);
                if ($migratedCount > 0) {
                    Log::info('Migrated guest sessions on login', [
                        'user_id' => $user->id,
                        'sessions_count' => $migratedCount,
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Failed to migrate guest sessions on login', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
                // Don't fail login if migration fails
            }

            $response = response()->json([
                'access_token' => $token,
                'user' => $user,
            ]);
            return $this->addCorsHeaders($response, $request);
        } catch (\Throwable $e) {
            Log::error('Login error', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            $response = response()->json(['error' => 'Login failed: ' . $e->getMessage()], 500);
            return $this->addCorsHeaders($response, $request);
        }
    }

        public function refreshToken(Request $request)
    {
        $refreshToken = $request->input('refresh_token');
        
        // You can validate the refresh token here
        $user = User::where('refresh_token', $refreshToken)->first();

        if (!$user) {
            $response = response()->json(['error' => 'Invalid refresh token'], 401);
            return $this->addCorsHeaders($response, $request);
        }

        $newToken = Auth::login($user);
        $newRefreshToken = Str::random(60);

        $user->update(['refresh_token' => $newRefreshToken]);

        $response = response()->json([
            'access_token' => $newToken,
            'refresh_token' => $newRefreshToken,
        ]);
        return $this->addCorsHeaders($response, $request);
    }

    public function register(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string',
                'email' => 'required|email|unique:users',
                'password' => 'required|string|min:6',
                'region' => 'nullable|string', // ✅ accept from client

            ]);

            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => bcrypt($validated['password']),
                'region' => $validated['region'] ?? 'local', // ✅ fallback to 'local' if not sent

            ]);

            $token = JWTAuth::fromUser($user);

            // Migrate guest sessions to user account
            try {
                $migratedCount = \App\Listeners\MigrateGuestSessionsOnSignup::migrateGuestSessionsForUser($user, $request);
                if ($migratedCount > 0) {
                    Log::info('Migrated guest sessions on registration', [
                        'user_id' => $user->id,
                        'sessions_count' => $migratedCount,
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Failed to migrate guest sessions on registration', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
                // Don't fail registration if migration fails
            }

            $response = response()->json([
                'access_token' => $token,
                'user' => $user,
            ]);
            return $this->addCorsHeaders($response, $request);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $response = response()->json(['error' => 'Validation failed', 'errors' => $e->errors()], 422);
            return $this->addCorsHeaders($response, $request);
        } catch (\Throwable $e) {
            Log::error('Register error', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            $response = response()->json(['error' => 'Registration failed: ' . $e->getMessage()], 500);
            return $this->addCorsHeaders($response, $request);
        }
    }

    public function me()
    {
        return response()->json(Auth::user());
    }

        public function loginWithGoogle(Request $request)
    {
        $idToken = $request->input('id_token');
        if (!$idToken) {
            $response = response()->json(['error' => 'Missing Google ID token'], 400);
            return $this->addCorsHeaders($response, $request);
        }

        try {
            // Validate token via Google
            $googleResponse = Http::get('https://oauth2.googleapis.com/tokeninfo', [
                'id_token' => $idToken,
            ]);

            if (!$googleResponse->ok()) {
                $response = response()->json(['error' => 'Invalid Google token'], 401);
                return $this->addCorsHeaders($response, $request);
            }

            $googleData = $googleResponse->json();
            $email = $googleData['email'] ?? null;
            $name = $googleData['name'] ?? 'Google User';
            $region = $request->input('region', 'local'); // ✅ client decides

            if (!$email) {
                $response = response()->json(['error' => 'Google response missing email'], 422);
                return $this->addCorsHeaders($response, $request);
            }

            // Check or create user
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => bcrypt(Str::random(16)), // unused
                    'region' => $region,
                    ]
            );

            $token = $user->createToken('google-login')->accessToken;

            $response = response()->json([
                'access_token' => $token,
                'user' => $user,
            ]);
            return $this->addCorsHeaders($response, $request);
        } catch (\Exception $e) {
            $response = response()->json(['error' => 'Server error: ' . $e->getMessage()], 500);
            return $this->addCorsHeaders($response, $request);
        }
    }

    /**
     * Forgot Password - Send reset code via email using notification system
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email'
        ]);

        $user = User::where('email', $request->email)->first();

        // Don't reveal if email exists for security
        if (!$user) {
            // Still return success to prevent email enumeration
            $response = response()->json([
                'message' => 'If the email exists, a password reset code has been sent.'
            ], 200);
            return $this->addCorsHeaders($response, $request);
        }

        // Generate 6-digit reset code
        $code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        
        // Store reset code (code expires after 10 minutes - handled in reset method)
        $user->update([
            'password_reset_code' => $code
        ]);

        try {
            // Send notification via notification service
            $notification = new ForgotPasswordNotification($code);
            $result = $this->notificationService->send(
                $user,
                $notification,
                [NotificationService::CHANNEL_EMAIL],
                false // Don't respect preferences for password reset
            );

            Log::info('Password reset code sent', [
                'user_id' => $user->id,
                'email' => $user->email,
                'reset_code' => $code,
                'notification_result' => $result
            ]);
            
            // Also log to help with debugging - always log the code for troubleshooting
            Log::info('Password reset code generated for testing', [
                'email' => $user->email,
                'code' => $code,
                'expires_in' => '10 minutes',
                'timestamp' => now()->toDateTimeString()
            ]);

            $responseData = [
                'message' => 'If the email exists, a password reset code has been sent to your email.',
                'success' => true
            ];
            
            // In debug mode, include the code in response for testing (REMOVE IN PRODUCTION)
            if (config('app.debug')) {
                $responseData['debug_code'] = $code;
                $responseData['debug_message'] = 'DEBUG MODE: Code included in response. Remove in production!';
            }

            $response = response()->json($responseData, 200);
            return $this->addCorsHeaders($response, $request);
        } catch (\Exception $e) {
            Log::error('Failed to send password reset email', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage()
            ]);

            $response = response()->json([
                'error' => 'Failed to send password reset email. Please try again later.',
                'success' => false
            ], 500);
            return $this->addCorsHeaders($response, $request);
        }
    }

    /**
     * Legacy method - kept for backward compatibility
     * @deprecated Use forgotPassword instead
     */
    public function sendResetCode(Request $request)
    {
        return $this->forgotPassword($request);
    }

    /**
     * Reset Password with Code
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function resetPasswordWithCode(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'code' => 'required|string|size:6',
            'password' => 'required|string|confirmed|min:6',
        ]);
    
        $user = User::where('email', $request->email)->first();
    
        if (!$user) {
            Log::warning('Password reset attempt - User not found', ['email' => $request->email]);
            $response = response()->json([
                'message' => 'Invalid email or reset code.',
                'success' => false
            ], 400);
            return $this->addCorsHeaders($response, $request);
        }
    
        // Check if code matches
        if (!$user->password_reset_code || $user->password_reset_code !== $request->code) {
            Log::warning('Password reset attempt - Invalid code', [
                'email' => $request->email,
                'submitted_code' => $request->code,
                'has_stored_code' => !empty($user->password_reset_code)
            ]);
            $response = response()->json([
                'message' => 'Invalid or expired reset code. Please request a new one.',
                'success' => false
            ], 400);
            return $this->addCorsHeaders($response, $request);
        }
        
        // Reset password
        $user->password = Hash::make($request->password);
        $user->password_reset_code = null; // Invalidate code after successful reset
        $user->save();
    
        Log::info('Password reset successful', ['user_id' => $user->id, 'email' => $user->email]);
    
        $response = response()->json([
            'message' => 'Password reset successfully. You can now login with your new password.',
            'success' => true
        ], 200);
        return $this->addCorsHeaders($response, $request);
    }
    
    

    public function logout()
    {
        Auth::logout();
        return response()->json(['message' => 'Successfully logged out']);
    }
}