<?php

use App\Http\Middleware\EnsureRegionMatchesUser;
use App\Http\Middleware\EnsureUsageQuota;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        api: __DIR__.'/../routes/api.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'ensure.region.match' => EnsureRegionMatchesUser::class,
            'quota.check'          => EnsureUsageQuota::class, // ✅ Add this line
        ]);
        
        // Enable CORS for API routes - must be first to handle OPTIONS requests
        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);
        
        // Prevent redirects for unauthenticated API requests
        $middleware->redirectGuestsTo(function ($request) {
            if ($request->is('api/*')) {
                return null; // Don't redirect API requests
            }
            return route('login');
        });
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Handle unauthenticated exceptions for API routes
        $exceptions->renderable(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'error' => 'Unauthenticated',
                    'message' => 'Authentication required'
                ], 401);
            }
        });
    })
    ->create();