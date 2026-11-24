<?php

namespace App\Logging;

use App\Logging\Contracts\LogEvent;
use App\Logging\Testing\FakeEventLogger;
use Throwable;

/**
 * The only way to write a log line. Never call Log::, logger() or error_log().
 *
 *   AppLog::emit(ShopifyEvent::WEBHOOK_REJECTED_HMAC, con: $connection->id, rsn: Reason::SIGNATURE_INVALID);
 *   AppLog::failure(ShopifyEvent::FULFILLMENT_REGISTER_FAILED, $e, con: $connection->id);
 *
 * Named arguments are the short keys of App\Logging\Enums\Ctx.
 */
final class AppLog
{
    public static function emit(LogEvent $event, mixed ...$context): void
    {
        self::logger()->emit($event, $context);
    }

    /** Like emit(), and records the exception's fingerprint with its detail in the exceptions log. */
    public static function failure(LogEvent $event, Throwable $e, mixed ...$context): void
    {
        self::logger()->failure($event, $e, $context);
    }

    /** Raw body behind an event, written only when LOG_PAYLOADS=true (short retention, may hold PII). */
    public static function payload(LogEvent $event, mixed $body): void
    {
        self::logger()->payload($event, $body);
    }

    public static function fake(): FakeEventLogger
    {
        $fake = new FakeEventLogger();
        app()->instance(EventLogger::class, $fake);

        return $fake;
    }

    private static function logger(): EventLogger
    {
        return app(EventLogger::class);
    }
}
