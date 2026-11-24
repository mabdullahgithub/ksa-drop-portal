<?php

namespace App\Logging;

use App\Logging\Enums\ActorType;
use App\Logging\Enums\Ctx;
use App\Logging\Enums\Severity;
use Carbon\CarbonImmutable;

/** Turns one coded log line back into readable text, using the enums as the dictionary. */
final class LineDecoder
{
    /** @return array<string, mixed>|null */
    public static function parse(string $line): ?array
    {
        $data = json_decode(trim($line), true);

        return is_array($data) && isset($data['e'], $data['l']) ? $data : null;
    }

    /** @param array<string, mixed> $line */
    public static function render(array $line): string
    {
        $event = EventRegistry::find((int) $line['e']);
        $severity = Severity::tryFrom((int) $line['l']);
        $time = CarbonImmutable::parse($line['t'] ?? 'now')->utc()->format('Y-m-d H:i:s');

        $head = sprintf(
            '%s %s%s %d %s',
            $time,
            strtoupper($severity?->name ?? '?'),
            (int) ($line['k'] ?? 1) === \App\Logging\Enums\Retention::HISTORY->value ? ' [history]' : '',
            $line['e'],
            $event?->description() ?? '(unknown event ID — newer than this build?)',
        );

        $actor = ActorType::tryFrom((int) ($line['at'] ?? -1));
        $meta = ['trace='.($line['r'] ?? '-'), 'actor='.($actor ? ucfirst(strtolower(str_replace('_', ' ', $actor->name))) : '-').(isset($line['a']) ? '#'.$line['a'] : '')];

        if (isset($line['c'])) {
            $meta[] = 'client=#'.$line['c'];
        }

        $out = $head."\n  ".implode(' ', $meta);

        foreach ((array) ($line['x'] ?? []) as $key => $value) {
            $out .= "\n  ".self::renderContext((string) $key, $value);
        }

        if (isset($line['d'])) {
            $out .= "\n  detail: ".json_encode($line['d'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        return $out;
    }

    private static function renderContext(string $key, mixed $value): string
    {
        $ctx = Ctx::tryFrom($key);

        if ($ctx === null) {
            return "{$key}={$value}";
        }

        $name = strtolower($ctx->name);
        $class = $ctx->enumClass();

        if ($class !== null && ($case = $class::tryFrom($value)) !== null) {
            $text = method_exists($case, 'description') ? $case->description() : (method_exists($case, 'label') ? $case->label() : $case->name);

            return "{$name}={$value} ({$text})";
        }

        return "{$name}={$value}";
    }
}
