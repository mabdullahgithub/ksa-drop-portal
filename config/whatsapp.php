<?php

return [
    /*
     * The inbox is unlocked with the recycle bin's PIN (config('recyclebin.pin'),
     * RECYCLE_BIN_PIN in .env); there is no separate WhatsApp PIN.
     *
     * Minutes of inactivity after which the inbox locks again. Unlike the
     * recycle bin this is idle time, not time since unlock: agents work in the
     * inbox for hours, and being sent back to the PIN prompt in the middle of a
     * reply would only teach them to resent it.
     */
    'unlock_ttl' => (int) env('WHATSAPP_UNLOCK_TTL', 30),
];
