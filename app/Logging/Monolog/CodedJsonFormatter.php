<?php

namespace App\Logging\Monolog;

use App\Logging\Enums\Retention;
use App\Logging\Enums\Severity;
use App\Logging\Events\SystemEvent;
use Monolog\Formatter\FormatterInterface;
use Monolog\LogRecord;
use Throwable;

/**
 * One JSON object per line, IDs only:
 *   {"t":"…Z","e":40203,"l":4,"k":1,"r":"<trace>","at":4,"a":9,"c":17,"x":{"con":9,"rsn":1003}}
 * `k` is the Retention class (1 prunable, 2 history).
 * `a` and `c` are left out when there is no actor ID or client.
 * A record that did not come from AppLog (a framework or library log) is
 * written as SystemEvent::UNCODED_RECORD with a fingerprint, never as prose.
 */
final class CodedJsonFormatter implements FormatterInterface
{
    public function format(LogRecord $record): string
    {
        $coded = $record->context['_coded'] ?? null;

        if (! is_array($coded)) {
            $coded = $this->uncoded($record);
        }

        $ambient = $record->extra['ambient'] ?? [];

        $line = [
            't' => $record->datetime->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.v\Z'),
            'e' => $coded['e'],
            'l' => $coded['l'],
            'k' => $coded['k'] ?? Retention::PRUNABLE->value,
            'r' => $ambient['r'] ?? null,
            'at' => $ambient['at'] ?? null,
            'a' => $ambient['a'] ?? null,
            'c' => $ambient['c'] ?? null,
            'x' => $coded['x'] ?? (object) [],
        ];

        if (isset($coded['d'])) {
            $line['d'] = $coded['d'];
        }

        foreach (['a', 'c'] as $optional) {
            if ($line[$optional] === null) {
                unset($line[$optional]);
            }
        }

        return json_encode($line, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR)."\n";
    }

    public function formatBatch(array $records): string
    {
        return implode('', array_map(fn (LogRecord $r) => $this->format($r), $records));
    }

    /** @return array{e: int, l: int, x: object} */
    private function uncoded(LogRecord $record): array
    {
        $exception = $record->context['exception'] ?? null;
        $fingerprint = $exception instanceof Throwable
            ? \App\Logging\ExceptionFingerprint::of($exception)
            : substr(sha1($record->message), 0, 12);

        return [
            'e' => SystemEvent::UNCODED_RECORD->id(),
            'l' => Severity::fromPsr($record->level->getName())->value,
            'x' => (object) ['exf' => $fingerprint],
        ];
    }
}
