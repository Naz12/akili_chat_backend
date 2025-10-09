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
            return response()->json(['error' => $result['message']], 403);
        }

        return $next($request);
    }
}