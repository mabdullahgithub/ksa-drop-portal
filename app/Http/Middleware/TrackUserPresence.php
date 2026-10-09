<?php

namespace App\Http\Middleware;

use App\Support\UserPresence;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Marks whoever is signed in as online. See App\Support\UserPresence. */
class TrackUserPresence
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($user = $request->user()) {
            UserPresence::checkIn($user);
        }

        return $next($request);
    }
}
