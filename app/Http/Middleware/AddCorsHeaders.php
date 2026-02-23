<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AddCorsHeaders
{
    /**
     * Handle an incoming request and ensure CORS headers are always present.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Handle OPTIONS preflight requests immediately
        if ($request->isMethod('OPTIONS')) {
            return $this->createCorsResponse($request, 200);
        }
        
        try {
            $response = $next($request);
        } catch (\Throwable $e) {
            // If an exception occurs, create a response with CORS headers
            $response = response()->json([
                'error' => 'Internal Server Error',
                'message' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
        
        // Add CORS headers to all API responses
        if ($request->is('api/*')) {
            $this->addCorsHeadersToResponse($response, $request);
        }
        
        return $response;
    }
    
    /**
     * Create a CORS response for OPTIONS requests
     */
    private function createCorsResponse(Request $request, int $status = 200): Response
    {
        $origin = $request->headers->get('Origin');
        $allowedOrigin = $this->resolveAllowedOrigin($origin);
        
        $response = response('', $status);
        
        if ($allowedOrigin !== '*') {
            $response->headers->set('Access-Control-Allow-Origin', $allowedOrigin);
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
        } else {
            $response->headers->set('Access-Control-Allow-Origin', '*');
        }
        
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS, PATCH');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin, X-Guest-UUID, X-CSRF-TOKEN, Referer, User-Agent, X-Session-ID, post, put, PATCH, DELETE');
        $response->headers->set('Access-Control-Max-Age', '86400');
        
        return $response;
    }

    /**
     * Resolve the Access-Control-Allow-Origin value: exact match or pattern match.
     */
    private function resolveAllowedOrigin(?string $origin): string
    {
        $allowedOrigins = config('cors.allowed_origins', []);
        $patterns = config('cors.allowed_origins_patterns', []);

        if ($origin === null || $origin === '') {
            return $allowedOrigins[0] ?? '*';
        }
        if (in_array($origin, $allowedOrigins, true)) {
            return $origin;
        }
        foreach ($patterns as $pattern) {
            if (is_string($pattern) && preg_match($pattern, $origin)) {
                return $origin;
            }
        }
        return $allowedOrigins[0] ?? '*';
    }

    /**
     * Add CORS headers to an existing response
     */
    private function addCorsHeadersToResponse(Response $response, Request $request): void
    {
        // Only add if not already present
        if ($response->headers->has('Access-Control-Allow-Origin')) {
            return;
        }

        $allowedOrigin = $this->resolveAllowedOrigin($request->headers->get('Origin'));

        if ($allowedOrigin !== '*') {
            $response->headers->set('Access-Control-Allow-Origin', $allowedOrigin);
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
        } else {
            $response->headers->set('Access-Control-Allow-Origin', '*');
        }
        
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS, PATCH');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin, X-Guest-UUID, X-CSRF-TOKEN, Referer, User-Agent, X-Session-ID, post, put, PATCH, DELETE');
    }
}


