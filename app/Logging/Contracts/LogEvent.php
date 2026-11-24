<?php

namespace App\Logging\Contracts;

use App\Logging\Enums\LogDomain;
use App\Logging\Enums\Severity;
use App\Logging\EventSpec;

/**
 * One loggable event. Implemented only by the backed int enums in
 * App\Logging\Events: the case value is the event ID that is written to the
 * log, the spec() is the only place its description and schema live.
 */
interface LogEvent extends \BackedEnum
{
    public function id(): int;

    public function domain(): LogDomain;

    public function spec(): EventSpec;

    public function description(): string;

    public function severity(): Severity;
}
