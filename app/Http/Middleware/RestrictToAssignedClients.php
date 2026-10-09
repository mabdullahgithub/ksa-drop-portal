<?php

namespace App\Http\Middleware;

use App\Support\ClientAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Limits a staff request to the clients its user was assigned; see
 * ClientAccess. Runs ahead of route model binding (bootstrap/app.php), so a
 * record of another client is a 404 rather than a page.
 */
class RestrictToAssignedClients
{
    public function handle(Request $request, Closure $next): Response
    {
        app(ClientAccess::class)->restrictTo(ClientAccess::idsFor($request->user()));

        return $next($request);
    }
}
