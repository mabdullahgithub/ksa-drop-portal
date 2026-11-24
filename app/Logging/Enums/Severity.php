<?php

namespace App\Logging\Enums;

/** RFC 5424 severity, written to the log as its number. */
enum Severity: int
{
    case EMERGENCY = 0;
    case ALERT = 1;
    case CRITICAL = 2;
    case ERROR = 3;
    case WARNING = 4;
    case NOTICE = 5;
    case INFO = 6;
    case DEBUG = 7;

    /** The PSR-3 level name Monolog understands. */
    public function psr(): string
    {
        return strtolower($this->name);
    }

    public function description(): string
    {
        return ucfirst(strtolower($this->name));
    }

    public static function fromPsr(string $level): self
    {
        foreach (self::cases() as $case) {
            if ($case->psr() === strtolower($level)) {
                return $case;
            }
        }

        return self::INFO;
    }
}
