<?php

namespace App\Logging;

use App\Logging\Enums\ActorType;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Throwable;

/**
 * Trace, actor and client that every log line carries without the call site
 * mentioning them. Stored in Laravel's Context, which queued jobs inherit, so a
 * job logs under the trace of the request that dispatched it.
 */
final class AmbientContext
{
    private const TRACE = 'trace';
    private const ACTOR_TYPE = 'actor_type';
    private const ACTOR_ID = 'actor_id';
    private const CLIENT_ID = 'client_id';

    /** A ULID, or a UUID handed to us by a proxy; anything else is not trusted. */
    public static function isValidTrace(?string $value): bool
    {
        return is_string($value)
            && (preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/i', $value) === 1
                || preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value) === 1);
    }

    public static function trace(): string
    {
        $trace = Context::get(self::TRACE);

        if (! is_string($trace)) {
            $trace = (string) Str::ulid();
            Context::add(self::TRACE, $trace);
        }

        return $trace;
    }

    public static function startTrace(?string $inbound = null): string
    {
        $trace = self::isValidTrace($inbound) ? $inbound : (string) Str::ulid();
        Context::add(self::TRACE, $trace);

        return $trace;
    }

    public static function setActor(ActorType $type, ?int $id = null): void
    {
        Context::add(self::ACTOR_TYPE, $type->value);
        Context::add(self::ACTOR_ID, $id);
    }

    public static function setClient(?int $clientId): void
    {
        Context::add(self::CLIENT_ID, $clientId);
    }

    /** @return array{at: int, a: int|null, c: int|null} */
    public static function actor(): array
    {
        $type = Context::get(self::ACTOR_TYPE);
        $id = Context::get(self::ACTOR_ID);

        if ($type === null) {
            [$type, $id] = self::detect();
        }

        $client = Context::get(self::CLIENT_ID);

        return ['at' => (int) $type, 'a' => $id !== null ? (int) $id : null, 'c' => $client !== null ? (int) $client : null];
    }

    /** @return array{0: int, 1: int|null} */
    private static function detect(): array
    {
        try {
            $guard = Auth::guard('web');

            // hasUser() only looks at a user that is already resolved; it never
            // starts a session or runs a query from inside the logger.
            if ($guard->hasUser()) {
                return [ActorType::USER->value, (int) $guard->id()];
            }
        } catch (Throwable) {
            // never let logging fail because auth is unavailable
        }

        return [app()->runningInConsole() ? ActorType::CLI->value : ActorType::SYSTEM->value, null];
    }
}
