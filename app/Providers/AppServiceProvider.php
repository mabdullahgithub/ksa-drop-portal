<?php

namespace App\Providers;

use App\Listeners\LogMailActivity;
use App\Models\Client;
use App\Models\ClientProduct;
use App\Models\Order;
use App\Models\Product;
use App\Observers\ClientObserver;
use App\Observers\ClientProductObserver;
use App\Observers\OrderObserver;
use App\Observers\ProductObserver;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
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
        //
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

        // Trace every outbound email into storage/logs/mail-*.log.
        Event::listen(MessageSending::class, [LogMailActivity::class, 'sending']);
        Event::listen(MessageSent::class, [LogMailActivity::class, 'sent']);

        // Clean up files the FK cascades leave behind when the recycle bin
        // permanently deletes a record. Registered as observers so this is
        // correct no matter which code path calls forceDelete().
        // These also carry the deletion audit trail (who/IP/device), so every
        // soft-deletable model needs one -- Product has no files to clean but
        // still gets an observer for that reason.
        Order::observe(OrderObserver::class);
        Client::observe(ClientObserver::class);
        ClientProduct::observe(ClientProductObserver::class);
        Product::observe(ProductObserver::class);
    }
}
