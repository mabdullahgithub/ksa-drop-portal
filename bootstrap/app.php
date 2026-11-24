<?php

use App\Logging\AmbientContext;
use App\Logging\AppLog;
use App\Logging\Events\MailEvent;
use App\Logging\Events\SystemEvent;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            \Illuminate\Support\Facades\Route::middleware('rider')->group(base_path('routes/rider.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust the ngrok / reverse-proxy forwarded headers so Laravel detects
        // the original HTTPS scheme instead of the proxied HTTP request.
        // Only trust forwarded headers from the reverse proxy (Coolify/Traefik
        // sits on a private Docker network). Override with TRUSTED_PROXIES=
        // (comma separated IPs/CIDRs, or * for ngrok-style local tunnels).
        $trusted = env('TRUSTED_PROXIES', '10.0.0.0/8,172.16.0.0/12,192.168.0.0/16,127.0.0.1');
        $middleware->trustProxies(at: $trusted === '*' ? '*' : array_map('trim', explode(',', $trusted)));

        $middleware->validateCsrfTokens(except: [
            'webhooks/*',
            'embedded/shopify/api/*', // session-token (JWT) authenticated, no Laravel session
        ]);

        // Trace ID first, so every log line of a request (and of the jobs it
        // dispatches) shares it. Echoed back as X-Request-Id.
        $middleware->prepend(\App\Http\Middleware\AssignTraceContext::class);

        $middleware->web(append: [
            \App\Http\Middleware\SecurityHeaders::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
            \App\Http\Middleware\AddLinkHeadersForPreloadedAssetsUnlessInertia::class,
            \App\Http\Middleware\TrackUserPresence::class,
        ]);

        // KSA Express rider app: its own device cookie instead of a
        // session, so no session and no CSRF token — writes are checked by
        // Origin instead. See App\Services\Riders\RiderAuthService.
        $middleware->group('rider', [
            \App\Http\Middleware\AssignTraceContext::class,
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \App\Http\Middleware\VerifyRiderOrigin::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ]);

        // Ahead of route model binding, so a record belonging to a client the
        // person was not assigned is a 404.
        $middleware->prependToPriorityList(
            before: \Illuminate\Routing\Middleware\SubstituteBindings::class,
            prepend: \App\Http\Middleware\RestrictToAssignedClients::class,
        );

        $middleware->alias([
            'client.access' => \App\Http\Middleware\RestrictToAssignedClients::class,
            'permission' => \App\Http\Middleware\CheckPermission::class,
            'role' => \App\Http\Middleware\CheckRole::class,
            'shopify.session' => \App\Http\Middleware\VerifyShopifySessionToken::class,
            'shopify.csp' => \App\Http\Middleware\ShopifyEmbeddedCsp::class,
            'recyclebin.unlocked' => \App\Http\Middleware\EnsureRecycleBinUnlocked::class,
            'whatsapp.unlocked' => \App\Http\Middleware\EnsureWhatsAppUnlocked::class,
            'requests.unlocked' => \App\Http\Middleware\EnsureRequestsUnlocked::class,
        ]);

        \Illuminate\Auth\Middleware\RedirectIfAuthenticated::redirectUsing(function ($request) {
            $user = auth()->user();
            if ($user && $user->hasRole('client')) {
                return route('portal.dashboard');
            }
            return route('dashboard');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Any mail transport failure (SMTP auth refused, TLS handshake, host
        // unreachable, message rejected) is logged as MailEvent::TRANSPORT_FAILED;
        // the mailer settings and full cause chain go to the exceptions log
        // under the fingerprint, not into the event.
        $exceptions->report(function (TransportExceptionInterface $e) {
            AppLog::failure(MailEvent::TRANSPORT_FAILED, $e);
        })->stop();

        // Every other reported exception: one coded line, detail in the
        // exceptions log. Replaces the framework's default prose log record.
        $exceptions->report(function (Throwable $e) {
            AppLog::failure(SystemEvent::EXCEPTION_REPORTED, $e);
        })->stop();

        // Every browser-facing error gets our designed page and nothing else:
        // no exception class, message or stack trace ever reaches the screen,
        // even with APP_DEBUG on. The cause goes to the log instead, under the
        // request's trace ID, which the user can quote to support.
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request) {
            $status = $response->getStatusCode();

            // Only take over full-page browser navigations and Inertia visits.
            // The REST endpoints, Shopify webhooks and the embedded app's
            // session-token API must keep receiving machine-readable errors.
            if ($request->expectsJson() || $request->is('api/*', 'webhooks/*', 'embedded/shopify/api/*')) {
                return $response;
            }

            if ($status < 400) {
                return $response;
            }

            // Deliberate block, not a fault: fixed message, nothing to log.
            if ($exception instanceof \App\Exceptions\ClientPortalBlocked) {
                return Inertia::render('Errors/Error', [
                    'status' => $status,
                    'message' => $exception->getMessage(),
                    'retryable' => false,
                ])
                    ->toResponse($request)
                    ->setStatusCode($status);
            }

            $reference = null;

            if ($status >= 500) {
                $reference = AmbientContext::trace();

                // Reported exceptions were already logged by report(); an HTTP
                // exception (abort(503)) never is, so record the page served.
                if ($exception instanceof HttpExceptionInterface) {
                    AppLog::failure(SystemEvent::SERVER_ERROR_PAGE_SERVED, $exception, hst: $status);
                }
            }

            return Inertia::render('Errors/Error', [
                'status' => $status,
                'reference' => $reference,
            ])
                ->toResponse($request)
                ->setStatusCode($status);
        });
    })->create();
