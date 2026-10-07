<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Laravel's defaults, plus rider/api/*: the riders' phone app
    | (rider-native/) calls it from its own origin — http://127.0.0.1 on
    | Android, php://127.0.0.1 on iPhone. It signs requests with a Bearer
    | token, never a cookie, so credentials stay off and any origin may ask.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'rider/api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
