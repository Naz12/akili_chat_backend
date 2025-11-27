<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRegionMatchesUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $userRegion = $user?->region;
        $routeRegion = strtolower($request->segment(3)); // Get /local or /intl

        if ($userRegion && $routeRegion && $userRegion !== $routeRegion) {
            \Log::warning('⛔ Region mismatch', [
                'expected' => $userRegion,
                'given' => $routeRegion,
                'user_id' => $user->id,
                'uri' => $request->getRequestUri(),
            ]);

            $origin = $request->headers->get('Origin');
            $allowedOrigins = config('cors.allowed_origins', []);
            $allowedOrigin = ($origin && in_array($origin, $allowedOrigins)) ? $origin : ($allowedOrigins[0] ?? '*');
            
            $response = response()->json(['error' => 'Region mismatch'], 403);
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