<?php

namespace App\Logging;

use App\Logging\Enums\Ctx;
use App\Logging\Enums\Retention;
use App\Logging\Enums\Severity;

/**
 * What an event is: its severity, its human description and which context
 * keys it may carry. This is the single source of truth, so a call site can
 * neither contradict the catalog nor smuggle in free text.
 */
final readonly class EventSpec
{
    /**
     * @param  list<Ctx>  $required
     * @param  list<Ctx>  $optional
     * @param  string|null  $channel  Overrides the domain's default channel (prunable events only).
     * @param  Retention  $keep  HISTORY events go to the history log and are not pruned.
     */
    public function __construct(
        public Severity $severity,
        public string $description,
        public array $required = [],
        public array $optional = [],
        public ?string $channel = null,
        public Retention $keep = Retention::PRUNABLE,
    ) {}

    /** @param list<Ctx> $required @param list<Ctx> $optional */
    public static function debug(string $description, array $required = [], array $optional = [], ?string $channel = null, Retention $keep = Retention::PRUNABLE): self
    {
        return new self(Severity::DEBUG, $description, $required, $optional, $channel, $keep);
    }

    /** @param list<Ctx> $required @param list<Ctx> $optional */
    public static function info(string $description, array $required = [], array $optional = [], ?string $channel = null, Retention $keep = Retention::PRUNABLE): self
    {
        return new self(Severity::INFO, $description, $required, $optional, $channel, $keep);
    }

    /** @param list<Ctx> $required @param list<Ctx> $optional */
    public static function notice(string $description, array $required = [], array $optional = [], ?string $channel = null, Retention $keep = Retention::PRUNABLE): self
    {
        return new self(Severity::NOTICE, $description, $required, $optional, $channel, $keep);
    }

    /** @param list<Ctx> $required @param list<Ctx> $optional */
    public static function warning(string $description, array $required = [], array $optional = [], ?string $channel = null, Retention $keep = Retention::PRUNABLE): self
    {
        return new self(Severity::WARNING, $description, $required, $optional, $channel, $keep);
    }

    /** @param list<Ctx> $required @param list<Ctx> $optional */
    public static function error(string $description, array $required = [], array $optional = [], ?string $channel = null, Retention $keep = Retention::PRUNABLE): self
    {
        return new self(Severity::ERROR, $description, $required, $optional, $channel, $keep);
    }

    /** @param list<Ctx> $required @param list<Ctx> $optional */
    public static function critical(string $description, array $required = [], array $optional = [], ?string $channel = null, Retention $keep = Retention::PRUNABLE): self
    {
        return new self(Severity::CRITICAL, $description, $required, $optional, $channel, $keep);
    }

    public function isHistory(): bool
    {
        return $this->keep === Retention::HISTORY;
    }

    /** @return list<Ctx> */
    public function allowed(): array
    {
        return [...$this->required, ...$this->optional];
    }
}
