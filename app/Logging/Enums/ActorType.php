<?php

namespace App\Logging\Enums;

/** Who caused a log line. The actor's ID (if any) is written next to it. */
enum ActorType: int
{
    case SYSTEM = 0;
    case USER = 1;
    case RIDER = 2;
    case SHOPIFY_EMBEDDED = 3;
    case WEBHOOK = 4;
    case SCHEDULER = 5;
    case QUEUE = 6;
    case CLI = 7;

    public function description(): string
    {
        return match ($this) {
            self::SYSTEM => 'System (no request, no console)',
            self::USER => 'Signed-in portal user (staff or client); the actor ID is users.id',
            self::RIDER => 'Rider app request; the actor ID is riders.id',
            self::SHOPIFY_EMBEDDED => 'Shopify embedded app session; the actor ID is client_shopify_connections.id',
            self::WEBHOOK => 'Inbound third-party webhook or callback',
            self::SCHEDULER => 'Scheduled task',
            self::QUEUE => 'Queue worker running a job',
            self::CLI => 'Artisan command run by hand',
        };
    }
}
