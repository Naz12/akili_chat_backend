<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Services\UsageValidatorService;

class EnsureUsageQuota
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        \Log::debug('Quota-middleware for user', ['id' => $user?->id]);

        $result = app(UsageValidatorService::class)
                  ->checkQuota($user, onlySubscription: true);  // ✅

        \Log::debug('Middleware quota result', $result);

        if ($result['error']) {
            $origin = $request->headers->get('Origin');
            $allowedOrigins = config('cors.allowed_origins', []);
            $allowedOrigin = ($origin && in_array($origin, $allowedOrigins)) ? $origin : ($allowedOrigins[0] ?? '*');
            
            $response = response()->json(['error' => $result['message']], 403);
            if ($allowedOrigin !== '*') {
                $response->header('Access-Control-Allow-Origin', $allowedOrigin)
                         ->header('Access-Control-Allow-Credentials', 'true');
            } else {
                $response->header('Access-Control-Allow-Origin', '*');
            }
            $response->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                     ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
            
            return $response;
        }

        return $next($request);
    }
}