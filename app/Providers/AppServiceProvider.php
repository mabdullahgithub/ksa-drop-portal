<?php

namespace App\Providers;

use App\Listeners\LogMailActivity;
use App\Models\Order;
use App\Observers\OrderObserver;
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

        // Starts the WhatsApp order-confirmation flow whenever an agent
        // marks a call unanswered, regardless of which code path did it.
        Order::observe(OrderObserver::class);

        // Behind an HTTPS-terminating proxy (e.g. ngrok), Laravel sees the
        // forwarded request as HTTP and generates http:// links, which the
        // browser blocks as mixed content. Force HTTPS when APP_URL is https.
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        // Trace every outbound email into storage/logs/mail-*.log.
        Event::listen(MessageSending::class, [LogMailActivity::class, 'sending']);
        Event::listen(MessageSent::class, [LogMailActivity::class, 'sent']);
    }
}
