<?php

namespace App\Logging;

use App\Logging\Contracts\LogEvent;
use App\Logging\Enums\LogDomain;

/** Every event in the app, looked up by its ID. */
final class EventRegistry
{
    /** @return array<int, LogEvent> */
    public static function all(): array
    {
        $all = [];

        foreach (LogDomain::cases() as $domain) {
            foreach ($domain->eventEnum()::cases() as $event) {
                $all[$event->id()] = $event;
            }
        }

        ksort($all);

        return $all;
    }

    public static function find(int $id): ?LogEvent
    {
        $domain = LogDomain::tryFrom(intdiv($id, 1000));

        return $domain?->eventEnum()::tryFrom($id);
    }
}
