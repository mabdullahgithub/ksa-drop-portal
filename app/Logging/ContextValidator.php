<?php

namespace App\Logging;

use App\Logging\Enums\Ctx;
use App\Logging\Enums\CtxType;

/**
 * Checks the named arguments of a call against the event's spec and turns them
 * into the plain values that are written: ints, strings and enum backing values.
 */
final class ContextValidator
{
    public const EXTERNAL_ID_PATTERN = '/^[A-Za-z0-9_\-.\/:#]{1,128}$/';

    /**
     * @param  array<string, mixed>  $context  keyed by the short Ctx key
     * @return array{0: array<string, int|string>, 1: list<string>} the normalised values and the problems found
     */
    public static function validate(EventSpec $spec, array $context): array
    {
        // Every event may carry an exception fingerprint (AppLog::failure adds it).
        $allowed = [Ctx::EXCEPTION_FINGERPRINT->value => Ctx::EXCEPTION_FINGERPRINT];
        foreach ($spec->allowed() as $ctx) {
            $allowed[$ctx->value] = $ctx;
        }

        $clean = [];
        $errors = [];

        foreach ($context as $key => $value) {
            $key = (string) $key;

            if (Ctx::tryFrom($key) === null) {
                $errors[] = "unknown key [{$key}]";

                continue;
            }

            if ($value === null) {
                continue; // null means absent: nothing is written
            }

            if (! isset($allowed[$key])) {
                $errors[] = "key [{$key}] is not declared by the event";

                continue;
            }

            $normalised = self::normalise($allowed[$key], $value);

            if ($normalised === null) {
                $errors[] = "bad value for [{$key}] (".$allowed[$key]->type()->value.')';

                continue;
            }

            $clean[$key] = $normalised;
        }

        foreach ($spec->required as $ctx) {
            if (! array_key_exists($ctx->value, $clean) && ! in_array("bad value for [{$ctx->value}] (".$ctx->type()->value.')', $errors, true)) {
                $errors[] = "missing required key [{$ctx->value}]";
            }
        }

        return [$clean, $errors];
    }

    private static function normalise(Ctx $ctx, mixed $value): int|string|null
    {
        return match ($ctx->type()) {
            CtxType::INT_ID => self::int($value, 1),
            CtxType::COUNT, CtxType::DURATION_MS, CtxType::AMOUNT, CtxType::ATTEMPT => self::int($value, 0),
            CtxType::HTTP_STATUS => ($n = self::int($value, 100)) !== null && $n <= 599 ? $n : null,
            CtxType::BOOL => is_bool($value) ? (int) $value : null,
            CtxType::EXTERNAL_ID => self::externalId($value),
            CtxType::ENUM => self::enum($ctx, $value),
        };
    }

    private static function int(mixed $value, int $min): ?int
    {
        if (is_string($value) && ctype_digit($value)) {
            $value = (int) $value;
        }

        return is_int($value) && $value >= $min ? $value : null;
    }

    private static function externalId(mixed $value): ?string
    {
        if (is_int($value)) {
            $value = (string) $value;
        }

        return is_string($value) && preg_match(self::EXTERNAL_ID_PATTERN, $value) === 1 ? $value : null;
    }

    private static function enum(Ctx $ctx, mixed $value): int|string|null
    {
        $class = $ctx->enumClass();

        if ($class === null) {
            return null;
        }

        if ($value instanceof $class) {
            return $value->value;
        }

        return (is_int($value) || is_string($value)) && $class::tryFrom($value) !== null ? $value : null;
    }
}
