<?php

use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogUdpHandler;
use Monolog\Processor\PsrLogMessageProcessor;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Log Channel
    |--------------------------------------------------------------------------
    |
    | This option defines the default log channel that is utilized to write
    | messages to your logs. The value provided here should match one of
    | the channels present in the list of "channels" configured below.
    |
    */

    'default' => env('LOG_CHANNEL', 'stack'),

    /*
    |--------------------------------------------------------------------------
    | Deprecations Log Channel
    |--------------------------------------------------------------------------
    |
    | This option controls the log channel that should be used to log warnings
    | regarding deprecated PHP and library features. This allows you to get
    | your application ready for upcoming major versions of dependencies.
    |
    */

    'deprecations' => [
        'channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),
        'trace' => env('LOG_DEPRECATIONS_TRACE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Log Channels
    |--------------------------------------------------------------------------
    |
    | Here you may configure the log channels for your application. Laravel
    | utilizes the Monolog PHP logging library, which includes a variety
    | of powerful log handlers and formatters that you're free to use.
    |
    | Available drivers: "single", "daily", "slack", "syslog",
    |                    "errorlog", "monolog", "custom", "stack"
    |
    */

    'channels' => [

        'stack' => [
            'driver' => 'stack',
            'channels' => explode(',', (string) env('LOG_STACK', 'single')),
            'ignore_exceptions' => false,
        ],

        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => env('LOG_DAILY_DAYS', 14),
            'replace_placeholders' => true,
        ],

        'slack' => [
            'driver' => 'slack',
            'url' => env('LOG_SLACK_WEBHOOK_URL'),
            'username' => env('LOG_SLACK_USERNAME', env('APP_NAME', 'Laravel')),
            'emoji' => env('LOG_SLACK_EMOJI', ':boom:'),
            'level' => env('LOG_LEVEL', 'critical'),
            'replace_placeholders' => true,
        ],

        'papertrail' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => env('LOG_PAPERTRAIL_HANDLER', SyslogUdpHandler::class),
            'handler_with' => [
                'host' => env('PAPERTRAIL_URL'),
                'port' => env('PAPERTRAIL_PORT'),
                'connectionString' => 'tls://'.env('PAPERTRAIL_URL').':'.env('PAPERTRAIL_PORT'),
            ],
            'processors' => [PsrLogMessageProcessor::class],
        ],

        'stderr' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => StreamHandler::class,
            'handler_with' => [
                'stream' => 'php://stderr',
            ],
            'formatter' => env('LOG_STDERR_FORMATTER'),
            'processors' => [PsrLogMessageProcessor::class],
        ],

        'syslog' => [
            'driver' => 'syslog',
            'level' => env('LOG_LEVEL', 'debug'),
            'facility' => env('LOG_SYSLOG_FACILITY', LOG_USER),
            'replace_placeholders' => true,
        ],

        'errorlog' => [
            'driver' => 'errorlog',
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        'null' => [
            'driver' => 'monolog',
            'handler' => NullHandler::class,
        ],

        'emergency' => [
            'path' => storage_path('logs/laravel.log'),
        ],

        'jnt_webhooks' => [
            'driver' => 'daily',
            'path' => storage_path('logs/jnt-webhooks.log'),
            'level' => 'debug',
            'days' => 30,
            'replace_placeholders' => true,
        ],

        'imile_webhooks' => [
            'driver' => 'daily',
            'path' => storage_path('logs/imile-webhooks.log'),
            'level' => 'debug',
            'days' => 30,
            'replace_placeholders' => true,
        ],

        // LogesTechs pushes each status change exactly once with no retry
        // ("only status 200, its send one time"), so this log is the only
        // record of a push we failed to process — worth keeping longer than
        // the other courier webhook logs.
        'logestechs_webhooks' => [
            'driver' => 'daily',
            'path' => storage_path('logs/logestechs-webhooks.log'),
            'level' => 'debug',
            'days' => 60,
            'replace_placeholders' => true,
        ],

        // Outbound LogesTechs API traffic (the above is inbound only). Split
        // out because LogesTechs publishes no error-code catalogue and returns
        // empty bodies on 5xx — the request we sent is the only thing left to
        // debug from, so LogesTechsDriver logs the payload on a server error.
        // Keeping that out of the shared app log makes it findable, and bounds
        // how long recipient names/phones/addresses linger in plain text.
        'logestechs' => [
            'driver' => 'daily',
            'path' => storage_path('logs/logestechs-api.log'),
            'level' => 'debug',
            'days' => 30,
            'replace_placeholders' => true,
        ],

        // Everything Shopify — OAuth/token exchange, webhook delivery and
        // registration, order sync, and the GDPR compliance topics — lands here
        // rather than in the shared app log. Kept 90 days so the compliance
        // records (customers/redact, shop/redact) outlive their action window.
        // Every outbound email — the message envelope on the way out, the
        // confirmed handoff to the transport, and any transport failure with
        // its full exception chain. Split out from the app log because SMTP
        // failures are otherwise buried under unrelated stack traces.
        'mail' => [
            'driver' => 'daily',
            'path' => storage_path('logs/mail.log'),
            'level' => 'debug',
            'days' => 30,
            'replace_placeholders' => true,
        ],

        'shopify' => [
            'driver' => 'daily',
            'path' => storage_path('logs/shopify.log'),
            'level' => 'debug',
            'days' => 90,
            'replace_placeholders' => true,
        ],

    ],

];
