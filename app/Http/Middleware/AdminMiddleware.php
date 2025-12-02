<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use App\Models\User;


class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        if (!$user) {
            abort(401, 'Unauthenticated');
        }

        // Check if user is admin (has roles/permissions or legacy admin role)
        if (!$user->isAdmin()) {
            abort(403, 'Access denied. Admin privileges required.');
        }

        // Check if user is active
        if (!$user->isActive()) {
            abort(403, 'Your account has been deactivated. Please contact an administrator.');
        }
    
        return $next($request);
    }
}