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

        $middleware->alias([
            'permission' => \App\Http\Middleware\CheckPermission::class,
            'role' => \App\Http\Middleware\CheckRole::class,
            'shopify.session' => \App\Http\Middleware\VerifyShopifySessionToken::class,
            'shopify.csp' => \App\Http\Middleware\ShopifyEmbeddedCsp::class,
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

        // Statuses we own a designed page for. Anything else keeps Laravel's
        // default response.
        $rendered = [401, 403, 404, 419, 429, 500, 503];

        $exceptions->respond(function (Response $response, Throwable $exception, Request $request) use ($rendered) {
            $status = $response->getStatusCode();

            // Only take over full-page browser navigations and Inertia visits.
            // The REST endpoints, Shopify webhooks and the embedded app's
            // session-token API must keep receiving machine-readable errors.
            if ($request->expectsJson() || $request->is('api/*', 'webhooks/*', 'embedded/shopify/api/*')) {
                return $response;
            }

            if (! in_array($status, $rendered, true)) {
                return $response;
            }

            // While developing, a 5xx should still open the full stack-trace
            // page — it is far more useful than our error screen. Set
            // APP_DEBUG=false to preview what a user actually sees.
            if ($status >= 500 && config('app.debug')) {
                return $response;
            }

            // Give the user something support can grep the logs for.
            $reference = null;

            if ($status >= 500) {
                $reference = Str::upper(Str::random(10));

                Log::error("Unhandled exception [{$reference}]", [
                    'reference' => $reference,
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                    'url' => $request->fullUrl(),
                    'user_id' => $request->user()?->id,
                ]);
            }

            return Inertia::render('Errors/Error', [
                'status' => $status,
                'reference' => $reference,
                'detail' => config('app.debug')
                    ? $exception::class.': '.$exception->getMessage()."\n\n".$exception->getTraceAsString()
                    : null,
            ])
                ->toResponse($request)
                ->setStatusCode($status);
        });
    })->create();
