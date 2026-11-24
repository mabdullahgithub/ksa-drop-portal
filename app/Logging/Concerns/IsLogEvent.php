<?php

namespace App\Logging\Concerns;

use App\Logging\Enums\LogDomain;
use App\Logging\Enums\Severity;

/**
 * Shared behaviour of every event enum. The domain is the first two digits of
 * the 5-digit event ID (DDNNN), so it can never disagree with the ID.
 */
trait IsLogEvent
{
    public function id(): int
    {
        return $this->value;
    }

    public function domain(): LogDomain
    {
        return LogDomain::from(intdiv($this->value, 1000));
    }

    public function description(): string
    {
        return $this->spec()->description;
    }

    public function severity(): Severity
    {
        return $this->spec()->severity;
    }
}
