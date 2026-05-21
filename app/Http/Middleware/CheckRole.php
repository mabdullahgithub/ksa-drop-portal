<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        // Allow admins impersonating a client to access portal routes
        if ($role === 'client' && session()->has('impersonate.admin_id')) {
            return $next($request);
        }

        if (!auth()->user()->hasRole($role)) {
            abort(403, 'You do not have the required role to access this resource.');
        }

        // Block clients whose account is no longer active (e.g. suspended after login)
        if ($role === 'client') {
            $client = auth()->user()->client;
            if (!$client || $client->status !== 'active') {
                if ($request->expectsJson()) {
                    return response()->json(['message' => 'Your account access has been restricted.'], 403);
                }
                return redirect()->route('portal.suspended');
            }
        }

        return $next($request);
    }
}
