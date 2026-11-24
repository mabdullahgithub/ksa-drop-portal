<?php

namespace App\Logging;

use App\Logging\Contracts\LogEvent;
use App\Logging\Enums\Ctx;
use App\Logging\Events\SystemEvent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Validates an event's context against its spec and writes the coded line.
 * Reach it through AppLog; tests swap it for FakeEventLogger.
 */
class EventLogger
{
    /** @param array<string, mixed> $context keyed by the short Ctx key */
    public function emit(LogEvent $event, array $context = []): void
    {
        try {
            $this->dispatch($event, $context, null);
        } catch (InvalidLogContext $e) {
            if ($this->strict()) {
                throw $e;
            }
        } catch (Throwable) {
            // Logging must never take the request down.
        }
    }

    /**
     * Raw request/response body behind an event, for debugging an integration.
     * Goes to the `payloads` channel, which writes nothing unless
     * LOG_PAYLOADS=true, and is never part of the event line itself.
     */
    public function payload(LogEvent $event, mixed $body): void
    {
        try {
            if (! config('logging.payloads_enabled')) {
                return;
            }

            Log::channel('payloads')->log('debug', (string) $event->id(), ['_coded' => [
                'e' => $event->id(),
                'l' => $event->severity()->value,
                'k' => \App\Logging\Enums\Retention::PRUNABLE->value,
                'x' => (object) [],
                'd' => ['body' => $body],
            ]]);
        } catch (Throwable) {
            // Logging must never take the request down.
        }
    }

    /** @param array<string, mixed> $context keyed by the short Ctx key */
    public function failure(LogEvent $event, Throwable $e, array $context = []): void
    {
        try {
            $context[Ctx::EXCEPTION_FINGERPRINT->value] = ExceptionFingerprint::of($e);
            $this->dispatch($event, $context, $e);
        } catch (InvalidLogContext $ex) {
            if ($this->strict()) {
                throw $ex;
            }
        } catch (Throwable) {
            // Logging must never take the request down.
        }
    }

    /** @param array<string, mixed> $context */
    private function dispatch(LogEvent $event, array $context, ?Throwable $exception): void
    {
        $spec = $event->spec();
        [$clean, $errors] = ContextValidator::validate($spec, $context);

        if ($errors !== []) {
            if ($this->strict()) {
                throw new InvalidLogContext($event::class.'::'.$event->name.': '.implode('; ', $errors));
            }

            $this->write(SystemEvent::INVALID_CONTEXT, [
                Ctx::SUBJECT_EVENT->value => $event->id(),
                Ctx::COUNT->value => count($errors),
            ]);
        }

        $this->write($event, $clean);

        if ($exception !== null) {
            $this->writeDetail($exception, (string) ($clean[Ctx::EXCEPTION_FINGERPRINT->value] ?? ExceptionFingerprint::of($exception)));
        }
    }

    /** @param array<string, int|string> $clean */
    protected function write(LogEvent $event, array $clean, ?array $detail = null): void
    {
        $spec = $event->spec();

        $payload = ['e' => $event->id(), 'l' => $spec->severity->value, 'k' => $spec->keep->value, 'x' => (object) $clean];

        if ($detail !== null) {
            $payload['d'] = $detail;
        }

        $channel = $spec->isHistory() ? 'history' : ($spec->channel ?? $event->domain()->channel());

        ($channel === null ? Log::channel() : Log::channel($channel))
            ->log($spec->severity->psr(), (string) $event->id(), ['_coded' => $payload]);
    }

    /** The trace and message go to the exceptions log, once per fingerprint per day. */
    private function writeDetail(Throwable $e, string $fingerprint): void
    {
        if (! $this->firstToday($fingerprint)) {
            return;
        }

        $this->write(SystemEvent::EXCEPTION_DETAIL, [Ctx::EXCEPTION_FINGERPRINT->value => $fingerprint], ExceptionFingerprint::detail($e));
    }

    private function firstToday(string $fingerprint): bool
    {
        try {
            return Cache::add('log:exf:'.$fingerprint.':'.gmdate('Ymd'), 1, 86400);
        } catch (Throwable) {
            return true; // cache down: better a duplicate detail line than none
        }
    }

    private function strict(): bool
    {
        return app()->environment('local', 'testing');
    }
}
