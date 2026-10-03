<?php

namespace App\Support;

use App\Models\Connector;

/**
 * Whether WhatsApp messaging is switched on: the toggle on the WhatsApp page,
 * stored as the WhatsApp connector's `enabled` flag.
 *
 * Off means nothing is sent to a customer -- no confirmation on "No Answer", no
 * 24h follow-up, no agent reply -- and a customer's reply changes no order. The
 * rest of the app then looks as it did before WhatsApp was added: no call or
 * WhatsApp columns on Orders, no call-outcome panel, no dashboard section.
 *
 * Read fresh every time rather than cached: the connectors table is a handful
 * of rows, and a queue worker has to see the switch flip without a restart.
 */
class WhatsAppMessaging
{
    public const CONNECTOR_KEY = 'whatsapp';

    public static function enabled(): bool
    {
        return (bool) Connector::where('key', self::CONNECTOR_KEY)->value('enabled');
    }

    public static function set(bool $enabled): void
    {
        Connector::where('key', self::CONNECTOR_KEY)->firstOrFail()->update(['enabled' => $enabled]);
    }
}
