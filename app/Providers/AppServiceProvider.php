<?php

namespace App\Providers;

use App\Listeners\EnforceEmailGraveyard;
use App\Listeners\LogMailActivity;
use App\Models\Client;
use App\Models\ClientProduct;
use App\Models\Order;
use App\Models\Product;
use App\Models\Rider;
use App\Models\User;
use App\Observers\ClientObserver;
use App\Observers\ClientProductObserver;
use App\Observers\OrderObserver;
use App\Observers\ProductObserver;
use App\Observers\RiderObserver;
use App\Observers\UserObserver;
use App\Services\Mail\GraveyardMailManager;
use App\Support\PhoneNumber;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Swap in the mail manager that notices when a mail server refuses a
        // recipient as unknown. extend() rather than a binding of our own: the
        // framework's mail provider is deferred and would overwrite one.
        $this->app->extend('mail.manager', fn ($manager, $app) => new GraveyardMailManager($app));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // Behind an HTTPS-terminating proxy (e.g. ngrok), Laravel sees the
        // forwarded request as HTTP and generates http:// links, which the
        // browser blocks as mixed content. Force HTTPS when APP_URL is https.
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        // Never mail an address in the email graveyard. Registered ahead of
        // the logger so a message it cancels is not logged as "Mail sending".
        Event::listen(MessageSending::class, [EnforceEmailGraveyard::class, 'sending']);

        // Trace every outbound email into storage/logs/mail-*.log.
        Event::listen(MessageSending::class, [LogMailActivity::class, 'sending']);
        Event::listen(MessageSent::class, [LogMailActivity::class, 'sent']);

        // Clean up files the FK cascades leave behind when the recycle bin
        // permanently deletes a record. Registered as observers so this is
        // correct no matter which code path calls forceDelete().
        // These also carry the deletion audit trail (who/IP/device), so every
        // soft-deletable model needs one -- Product has no files to clean but
        // still gets an observer for that reason.
        // OrderObserver also starts the WhatsApp order-confirmation flow
        // whenever an agent marks a call unanswered. Register it only once:
        // a second observe() would fire every hook twice.
        Order::observe(OrderObserver::class);
        Client::observe(ClientObserver::class);
        ClientProduct::observe(ClientProductObserver::class);
        Product::observe(ProductObserver::class);
        User::observe(UserObserver::class);
        Rider::observe(RiderObserver::class);

        // Rider phone + PIN sign-in. Per phone on top of per IP, so guessing
        // one rider's PIN is slow even from many networks; the rider's own
        // lock after Rider::MAX_PIN_ATTEMPTS wrong PINs is the backstop.
        RateLimiter::for('rider-login', fn (Request $request) => [
            Limit::perMinute(10)->by('ip:' . $request->ip()),
            Limit::perMinutes(15, 5)->by('phone:' . (PhoneNumber::normalize((string) $request->input('phone')) ?? $request->ip())),
        ]);
    }
}
