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


class AuthApiController extends Controller
{
    use AddsCorsHeaders;
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

        public function sendResetCode(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            $response = response()->json(['error' => 'Email not found'], 404);
            return $this->addCorsHeaders($response, $request);
        }

        $code = rand(100000, 999999);
        $user->update(['password_reset_code' => $code]);

        try {
            Mail::raw("Your ChatDagu password reset code is: $code", function ($message) use ($user) {
                $message->to($user->email)->subject('Your Password Reset Code');
            });

            $response = response()->json(['message' => 'Verification code sent']);
            return $this->addCorsHeaders($response, $request);
        } catch (\Exception $e) {
            $response = response()->json(['error' => 'Failed to send email.'], 500);
            return $this->addCorsHeaders($response, $request);
        }
    }

    public function resetPasswordWithCode(Request $request)
    {
        \Log::info('🛠️ Reset attempt', $request->all());
    
        $request->validate([
            'email' => 'required|email',
            'code' => 'required|string',
            'password' => 'required|string|confirmed|min:6',
        ]);
    
        $user = User::where('email', $request->email)->first();
    
        if (!$user) {
            \Log::warning('❌ User not found', ['email' => $request->email]);
            $response = response()->json(['message' => 'User not found.'], 404);
            return $this->addCorsHeaders($response, $request);
        }
    
        if (!$user || $user->password_reset_code !== $request->code) {
            Log::warning('❌ Code mismatch', [
                'submitted' => $request->code,
                'stored' => $user?->password_reset_code
            ]);
            $response = response()->json(['message' => 'Invalid verification code.'], 400);
            return $this->addCorsHeaders($response, $request);
        }
        
        $user->password = Hash::make($request->password);
        $user->password_reset_code = null; // ✅ Invalidate after success
        $user->save();
    
        \Log::info('✅ Password reset success', ['email' => $user->email]);
    
        $response = response()->json(['message' => 'Password reset successfully.']);
        return $this->addCorsHeaders($response, $request);
    }
    
    

    public function logout()
    {
        Auth::logout();
        return response()->json(['message' => 'Successfully logged out']);
    }
}