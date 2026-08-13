<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $permission  One permission, or several pipe-separated — any one of them is sufficient.
     *                              e.g. `permission:view apps|edit apps` lets an edit-only role through too, so a
     *                              role granted "edit apps" without "view apps" doesn't get silently 403'd on the
     *                              read endpoints its own edit page depends on (e.g. /api/connectors). Pipe, not
     *                              comma: Laravel's route-middleware string itself splits on commas to build the
     *                              parameter list before this method ever runs, so a comma here would silently
     *                              arrive as two separate $permission calls instead of one string to split.
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        $permissions = array_map('trim', explode('|', $permission));

        if (!auth()->user()->canAny($permissions)) {
            if (auth()->user()->hasRole('client')) {
                return redirect()->route('portal.dashboard');
            }
            abort(403, 'You do not have permission to access this resource.');
        }

        return $next($request);
    }
}
