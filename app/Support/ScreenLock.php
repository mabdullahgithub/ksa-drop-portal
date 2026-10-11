<?php

namespace App\Support;

/**
 * Whether a screen asks for the PIN: the recycle bin, the WhatsApp inbox and
 * the Requests page each have a switch in .env (config/locks.php).
 */
class ScreenLock
{
    public const RECYCLE_BIN = 'recycle_bin';

    public const WHATSAPP = 'whatsapp';

    public const REQUESTS = 'requests';

    public static function enabled(string $screen): bool
    {
        // A screen nobody listed stays locked.
        return (bool) config("locks.screens.{$screen}", true);
    }

    /**
     * Every screen's switch, for the pages to know whether to ask.
     *
     * @return array<string, bool>
     */
    public static function all(): array
    {
        return [
            self::RECYCLE_BIN => self::enabled(self::RECYCLE_BIN),
            self::WHATSAPP => self::enabled(self::WHATSAPP),
            self::REQUESTS => self::enabled(self::REQUESTS),
        ];
    }
}
