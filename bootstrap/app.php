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
        
        // Enable CORS for ALL routes - must be first to handle OPTIONS requests
        $middleware->prepend(\App\Http\Middleware\AddCorsHeaders::class);
        
        // Also use Laravel's built-in CORS handler
        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);
        
        // Ensure CORS headers are always added as a fallback
        $middleware->api(append: [
            \App\Http\Middleware\AddCorsHeaders::class,
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
        // Helper function to add CORS headers to response
        $addCorsHeaders = function($response, $request) {
            $origin = $request->headers->get('Origin');
            $allowedOrigins = config('cors.allowed_origins', []);
            $allowedOrigin = ($origin && in_array($origin, $allowedOrigins)) ? $origin : ($allowedOrigins[0] ?? '*');
            
            if ($allowedOrigin !== '*') {
                $response->header('Access-Control-Allow-Origin', $allowedOrigin)
                         ->header('Access-Control-Allow-Credentials', 'true');
            } else {
                $response->header('Access-Control-Allow-Origin', '*');
            }
            $response->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                     ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
            
            return $response;
        };
        
        // Handle validation exceptions for API routes
        $exceptions->renderable(function (\Illuminate\Validation\ValidationException $e, $request) use ($addCorsHeaders) {
            if ($request->is('api/*')) {
                $response = response()->json([
                    'error' => 'Validation failed',
                    'message' => 'The given data was invalid.',
                    'errors' => $e->errors()
                ], 422);
                
                return $addCorsHeaders($response, $request);
            }
        });
        
        // Handle unauthenticated exceptions for API routes
        $exceptions->renderable(function (\Illuminate\Auth\AuthenticationException $e, $request) use ($addCorsHeaders) {
            if ($request->is('api/*')) {
                $response = response()->json([
                    'error' => 'Unauthenticated',
                    'message' => 'Authentication required'
                ], 401);
                
                return $addCorsHeaders($response, $request);
            }
        });
        
        // Handle all exceptions for API routes to ensure CORS headers are included
        // This must be last to catch all unhandled exceptions
        $exceptions->renderable(function (\Throwable $e, $request) use ($addCorsHeaders) {
            if ($request->is('api/*')) {
                \Log::error('API Exception', [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                    'uri' => $request->getRequestUri(),
                    'class' => get_class($e),
                ]);
                
                $statusCode = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
                
                try {
                    $response = response()->json([
                        'error' => $e->getMessage() ?: 'Internal Server Error',
                        'message' => config('app.debug') ? $e->getMessage() : 'An error occurred',
                    ], $statusCode);
                } catch (\Throwable $responseError) {
                    // If we can't create a JSON response, create a plain text one
                    $response = response('Internal Server Error', 500);
                }
                
                return $addCorsHeaders($response, $request);
            }
        });
    })
    ->create();