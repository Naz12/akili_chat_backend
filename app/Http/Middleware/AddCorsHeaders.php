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
        $allowedOrigins = config('cors.allowed_origins', []);
        $allowedOrigin = ($origin && in_array($origin, $allowedOrigins)) ? $origin : ($allowedOrigins[0] ?? '*');
        
        $response = response('', $status);
        
        if ($allowedOrigin !== '*') {
            $response->headers->set('Access-Control-Allow-Origin', $allowedOrigin);
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
        } else {
            $response->headers->set('Access-Control-Allow-Origin', '*');
        }
        
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS, PATCH');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin, X-Guest-UUID, X-CSRF-TOKEN, Referer, User-Agent, post');
        $response->headers->set('Access-Control-Max-Age', '86400');
        
        return $response;
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
        
        $origin = $request->headers->get('Origin');
        $allowedOrigins = config('cors.allowed_origins', []);
        $allowedOrigin = ($origin && in_array($origin, $allowedOrigins)) ? $origin : ($allowedOrigins[0] ?? '*');
        
        if ($allowedOrigin !== '*') {
            $response->headers->set('Access-Control-Allow-Origin', $allowedOrigin);
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
        } else {
            $response->headers->set('Access-Control-Allow-Origin', '*');
        }
        
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS, PATCH');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin, X-Guest-UUID, X-CSRF-TOKEN, Referer, User-Agent, post');
    }
}


