<?php

namespace App\Logging;

/**
 * For identifiers taken from an untrusted request (a username a webhook sent,
 * a vendor status string): returns the value if it is a clean identifier and
 * null otherwise, so the key is simply left out instead of failing validation.
 */
final class ExternalId
{
    public static function orNull(mixed $value): ?string
    {
        if (is_int($value)) {
            $value = (string) $value;
        }

        return is_string($value) && preg_match(ContextValidator::EXTERNAL_ID_PATTERN, $value) === 1 ? $value : null;
    }
}
