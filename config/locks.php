<?php

return [
    /*
     * Which screens ask for the PIN before they show anything. One PIN opens
     * all of them: RECYCLE_BIN_PIN (config('recyclebin.pin')).
     *
     * Each is on unless its line in .env says false, so a screen is never
     * left open by a missing or misspelt setting.
     */
    'screens' => [
        'recycle_bin' => (bool) env('LOCK_RECYCLE_BIN', true),
        'whatsapp' => (bool) env('LOCK_WHATSAPP', true),
        'requests' => (bool) env('LOCK_REQUESTS', true),
    ],

    /*
     * Minutes of inactivity after which the Requests page locks again. Idle
     * time, like the WhatsApp inbox: a queue is worked for a while, and being
     * sent back to the PIN prompt halfway through an approval helps nobody.
     */
    'requests_unlock_ttl' => (int) env('REQUESTS_UNLOCK_TTL', 30),
];
