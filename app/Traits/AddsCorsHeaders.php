<?php

namespace App\Traits;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

trait AddsCorsHeaders
{
    /**
     * Add CORS headers to a response
     */
    protected function addCorsHeaders(Response $response, Request $request): Response
    {
        $origin = $request->headers->get('Origin');
        $allowedOrigins = config('cors.allowed_origins', []);
        $allowedOrigin = ($origin && in_array($origin, $allowedOrigins)) ? $origin : ($allowedOrigins[0] ?? '*');
        
        // Only add if not already present
        if (!$response->headers->has('Access-Control-Allow-Origin')) {
            if ($allowedOrigin !== '*') {
                $response->headers->set('Access-Control-Allow-Origin', $allowedOrigin);
                $response->headers->set('Access-Control-Allow-Credentials', 'true');
            } else {
                $response->headers->set('Access-Control-Allow-Origin', '*');
            }
            $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS, PATCH');
            $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin, X-Guest-UUID, X-CSRF-TOKEN, Referer, User-Agent, X-Session-ID, post, put, PATCH, DELETE');
        }
        
        return $response;
    }
}

