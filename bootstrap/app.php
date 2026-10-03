<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
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
        $middleware->trustProxies(at: '*');

        $middleware->validateCsrfTokens(except: [
            'webhooks/*',
            'embedded/shopify/api/*', // session-token (JWT) authenticated, no Laravel session
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \App\Http\Middleware\AddLinkHeadersForPreloadedAssetsUnlessInertia::class,
        ]);

        // KSA Express rider app: its own device cookie instead of a
        // session, so no session and no CSRF token — writes are checked by
        // Origin instead. See App\Services\Riders\RiderAuthService.
        $middleware->group('rider', [
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \App\Http\Middleware\VerifyRiderOrigin::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ]);

        $middleware->alias([
            'permission' => \App\Http\Middleware\CheckPermission::class,
            'role' => \App\Http\Middleware\CheckRole::class,
            'shopify.session' => \App\Http\Middleware\VerifyShopifySessionToken::class,
            'shopify.csp' => \App\Http\Middleware\ShopifyEmbeddedCsp::class,
            'recyclebin.unlocked' => \App\Http\Middleware\EnsureRecycleBinUnlocked::class,
            'whatsapp.unlocked' => \App\Http\Middleware\EnsureWhatsAppUnlocked::class,
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
        // unreachable, message rejected) gets its full cause chain recorded in
        // the mail channel before the exception continues on its way.
        $exceptions->report(function (TransportExceptionInterface $e) {
            $causes = [];

            for ($current = $e; $current !== null; $current = $current->getPrevious()) {
                $causes[] = $current::class.': '.$current->getMessage();
            }

            Log::channel('mail')->error('Mail transport failed', [
                'transport' => config('mail.default'),
                'host' => config('mail.mailers.'.config('mail.default').'.host'),
                'port' => config('mail.mailers.'.config('mail.default').'.port'),
                'username' => config('mail.mailers.'.config('mail.default').'.username'),
                'encryption' => config('mail.mailers.'.config('mail.default').'.encryption'),
                'causes' => $causes,
                'trace' => $e->getTraceAsString(),
            ]);
        });

        // Every browser-facing error gets our designed page and nothing else:
        // no exception class, message or stack trace ever reaches the screen,
        // even with APP_DEBUG on. The cause goes to the log instead, under a
        // reference the user can quote to support.
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

            $reference = null;

            if ($status >= 500) {
                $reference = Str::upper(Str::random(10));

                Log::error("Unhandled exception [{$reference}]", [
                    'reference' => $reference,
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                    'location' => $exception->getFile().':'.$exception->getLine(),
                    'url' => $request->fullUrl(),
                    'user_id' => $request->user()?->id,
                ]);
            }

            return Inertia::render('Errors/Error', [
                'status' => $status,
                'reference' => $reference,
            ])
                ->toResponse($request)
                ->setStatusCode($status);
        });
    })->create();
