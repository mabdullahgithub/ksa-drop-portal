<?php

namespace App\Support;

/**
 * Turns a User-Agent string into a short readable label, e.g.
 * "Chrome 140 on macOS (Desktop)".
 *
 * A deliberately small parser rather than a dependency: this only has to be
 * good enough to answer "which machine was this deleted from" at a glance, and
 * the untouched UA string is always stored alongside it for the real detail.
 * UA strings lie and change constantly, so treat the label as a hint.
 */
class ClientDevice
{
    public static function describe(?string $userAgent): ?string
    {
        $userAgent = trim((string) $userAgent);

        if ($userAgent === '') {
            return null;
        }

        $browser = self::browser($userAgent);
        $platform = self::platform($userAgent);
        $type = self::type($userAgent);

        $label = $browser ?? 'Unknown browser';

        if ($platform !== null) {
            $label .= ' on ' . $platform;
        }

        return $label . ' (' . $type . ')';
    }

    private static function browser(string $ua): ?string
    {
        // Order matters: Edge and Opera both also claim to be Chrome, and
        // Chrome also claims to be Safari.
        $patterns = [
            'Edge' => '/Edg(?:e|A|iOS)?\/([0-9]+)/',
            'Opera' => '/(?:OPR|Opera)\/([0-9]+)/',
            'Samsung Internet' => '/SamsungBrowser\/([0-9]+)/',
            'Firefox' => '/(?:Firefox|FxiOS)\/([0-9]+)/',
            'Chrome' => '/(?:Chrome|CriOS)\/([0-9]+)/',
            'Safari' => '/Version\/([0-9]+).*Safari/',
        ];

        foreach ($patterns as $name => $pattern) {
            if (preg_match($pattern, $ua, $matches)) {
                return $name . ' ' . $matches[1];
            }
        }

        return null;
    }

    private static function platform(string $ua): ?string
    {
        return match (true) {
            (bool) preg_match('/Windows NT 10\.0/', $ua) => 'Windows 10/11',
            (bool) preg_match('/Windows NT ([0-9.]+)/', $ua) => 'Windows',
            (bool) preg_match('/iPhone|iPad|iPod/', $ua) => 'iOS',
            (bool) preg_match('/Mac OS X ([0-9_.]+)/', $ua, $m) => 'macOS ' . str_replace('_', '.', $m[1]),
            (bool) preg_match('/Macintosh/', $ua) => 'macOS',
            (bool) preg_match('/Android ([0-9.]+)/', $ua, $m) => 'Android ' . $m[1],
            (bool) preg_match('/Android/', $ua) => 'Android',
            (bool) preg_match('/CrOS/', $ua) => 'ChromeOS',
            (bool) preg_match('/Linux/', $ua) => 'Linux',
            default => null,
        };
    }

    private static function type(string $ua): string
    {
        return match (true) {
            (bool) preg_match('/iPad|Tablet/i', $ua) => 'Tablet',
            (bool) preg_match('/Mobile|iPhone|Android.*Mobile/i', $ua) => 'Mobile',
            default => 'Desktop',
        };
    }
}
