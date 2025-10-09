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

            return response()->json(['error' => 'Region mismatch'], 403);
        }

        return $next($request);
    }
}