<?php

return [
    /*
     * PIN required to reveal the recycle bin.
     *
     * Checked server-side only -- it is never sent to the browser, so it does
     * not end up readable in the built JS bundle.
     *
     * Set RECYCLE_BIN_PIN in .env. Deliberately no default: a literal here
     * would be committed to the repository, and an empty value fails closed
     * (the unlock endpoint rejects every attempt) rather than leaving the
     * destructive screen open.
     */
    'pin' => (string) env('RECYCLE_BIN_PIN', ''),

    /*
     * How long a successful unlock keeps the API open, in minutes.
     *
     * The UI asks for the PIN every time the page is opened regardless; this
     * only stops a long restore/purge session from being interrupted midway.
     */
    'unlock_ttl' => (int) env('RECYCLE_BIN_UNLOCK_TTL', 30),
];
