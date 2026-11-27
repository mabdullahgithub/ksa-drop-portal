<?php

namespace App\Services\Chat;

use App\Support\ClientDevice;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Where a chat message was sent from: the address, the browser and device,
 * the network and the approximate place, as they were at that moment.
 *
 * Sensitive. It is saved on the message but never part of a message payload
 * or a broadcast: only the details endpoint returns it, to team members with
 * `view chat message details`.
 */
class ChatOrigin
{
    /**
     * What the browser may say about itself, and the longest each may be.
     * These come from the sender's own machine, so they can be faked; the
     * address and user agent are what the server received.
     */
    private const REPORTED = [
        'platform' => 60,
        'model' => 60,
        'brands' => 200,
        'language' => 20,
        'timezone' => 60,
        'screen' => 30,
        'viewport' => 30,
        'connection' => 20,
        'connection_type' => 20,
        'downlink' => 20,
        'rtt' => 20,
    ];

    public function __construct(private ChatGeoIp $geo) {}

    /** @return array<string, mixed> */
    public function capture(Request $request): array
    {
        $ip = $request->ip();
        $userAgent = Str::limit((string) $request->userAgent(), 600, '') ?: null;

        return [
            'ip' => $ip,
            'user_agent' => $userAgent,
            'device' => ClientDevice::describe($userAgent),
            'reported' => $this->reported($request->input('client_info')),
        ] + $this->geo->lookup($ip);
    }

    /** @return array<string, string> only the known keys, as short plain text */
    private function reported(mixed $json): array
    {
        $given = is_string($json) ? json_decode($json, true) : null;

        if (! is_array($given)) {
            return [];
        }

        $clean = [];
        foreach (self::REPORTED as $key => $length) {
            if (isset($given[$key]) && is_scalar($given[$key])) {
                $value = trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string) $given[$key]) ?? '');
                if ($value !== '') {
                    $clean[$key] = Str::limit($value, $length, '');
                }
            }
        }

        return $clean;
    }
}
