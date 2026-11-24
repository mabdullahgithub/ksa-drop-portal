<?php

namespace App\Http\Middleware;

use App\Logging\AmbientContext;
use App\Logging\Enums\ActorType;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gives every request a trace ID (a valid inbound X-Request-Id is kept, anything
 * else is replaced by a fresh ULID) and echoes it back, so a user or a vendor
 * can quote it and `php artisan log:decode --trace=` finds every line.
 */
class AssignTraceContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $trace = AmbientContext::startTrace($request->header('X-Request-Id'));

        if ($request->is('webhooks/*')) {
            AmbientContext::setActor(ActorType::WEBHOOK);
        }

        $response = $next($request);
        $response->headers->set('X-Request-Id', $trace);

        return $response;
    }
}
