<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Portal Address
    |--------------------------------------------------------------------------
    |
    | Where the KSA Drop portal lives. The app has no data of its own: every
    | screen calls {portal_url}/rider/api/*. No trailing slash.
    |
    */

    'portal_url' => rtrim((string) env('RIDER_PORTAL_URL', ''), '/'),

];
