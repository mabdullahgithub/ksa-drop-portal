<?php

namespace App\Services\Chat;

use MaxMind\Db\Reader;

/**
 * Where an IP address is, and whose network it is, looked up in DB-IP's free
 * databases kept on this server (chat:update-geoip downloads them). Nothing
 * is sent anywhere: no user's address leaves the server.
 *
 * An estimate: the country is reliable, the city often only roughly, and a
 * VPN or mobile network shows where its traffic comes out. IP geolocation by
 * DB-IP (https://db-ip.com), CC BY 4.0.
 */
class ChatGeoIp
{
    public const CITY = 'city';

    public const ASN = 'asn';

    public static function path(string $database): string
    {
        return rtrim(config('chat.geoip.directory'), '/') . "/dbip-{$database}-lite.mmdb";
    }

    /** A public address: not this machine, not a private network. */
    public static function isPublic(?string $ip): bool
    {
        return $ip !== null
            && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    /**
     * @return array{location: ?array<string, mixed>, network: ?array<string, mixed>}
     */
    public function lookup(?string $ip): array
    {
        $none = ['location' => null, 'network' => null];

        if (! self::isPublic($ip)) {
            return $none;
        }

        $city = $this->record(self::CITY, $ip);
        $asn = $this->record(self::ASN, $ip);

        return [
            'location' => $city ? array_filter([
                'city' => $city['city']['names']['en'] ?? null,
                'region' => $city['subdivisions'][0]['names']['en'] ?? null,
                'country' => $city['country']['names']['en'] ?? null,
                'country_code' => $city['country']['iso_code'] ?? null,
                'latitude' => $city['location']['latitude'] ?? null,
                'longitude' => $city['location']['longitude'] ?? null,
            ], fn ($value) => $value !== null) ?: null : null,
            'network' => $asn ? array_filter([
                'isp' => $asn['autonomous_system_organization'] ?? null,
                'asn' => isset($asn['autonomous_system_number']) ? 'AS' . $asn['autonomous_system_number'] : null,
            ]) ?: null : null,
        ];
    }

    /** A missing or unreadable database only means "unknown": it never stops a message. */
    private function record(string $database, string $ip): ?array
    {
        $path = self::path($database);

        if (! is_file($path)) {
            return null;
        }

        try {
            $reader = new Reader($path);

            try {
                return $reader->get($ip);
            } finally {
                $reader->close();
            }
        } catch (\Throwable) {
            return null;
        }
    }
}
