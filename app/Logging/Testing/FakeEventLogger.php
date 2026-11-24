<?php

namespace App\Logging\Testing;

use App\Logging\Contracts\LogEvent;
use App\Logging\EventLogger;
use PHPUnit\Framework\Assert;

/**
 * AppLog::fake(): records events instead of writing them. Context is still
 * validated, so a test fails if the code under test passes bad keys.
 */
class FakeEventLogger extends EventLogger
{
    /** @var list<array{event: LogEvent, context: array<string, int|string>}> */
    private array $emitted = [];

    protected function write(LogEvent $event, array $clean, ?array $detail = null): void
    {
        $this->emitted[] = ['event' => $event, 'context' => $clean];
    }

    public function payload(LogEvent $event, mixed $body): void
    {
        // payloads are not events; nothing to record
    }

    /** @param callable(array<string, int|string>): bool|null $matching */
    public function assertEmitted(LogEvent $event, ?callable $matching = null): void
    {
        Assert::assertNotEmpty($this->of($event, $matching), "Event {$event->name} ({$event->id()}) was not emitted.");
    }

    /** @param callable(array<string, int|string>): bool|null $matching */
    public function assertNotEmitted(LogEvent $event, ?callable $matching = null): void
    {
        Assert::assertEmpty($this->of($event, $matching), "Event {$event->name} ({$event->id()}) was emitted.");
    }

    public function assertNothingEmitted(): void
    {
        Assert::assertEmpty($this->emitted, 'Events were emitted: '.implode(', ', array_map(fn ($e) => $e['event']->name, $this->emitted)));
    }

    /** @return list<array<string, int|string>> */
    public function of(LogEvent $event, ?callable $matching = null): array
    {
        $found = [];

        foreach ($this->emitted as $entry) {
            if ($entry['event'] === $event && ($matching === null || $matching($entry['context']))) {
                $found[] = $entry['context'];
            }
        }

        return $found;
    }
}
