<?php

use App\Logging\Monolog\UseCodedFormat;
use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogUdpHandler;
use Monolog\Processor\PsrLogMessageProcessor;

// Operational log files are deleted after this many days (log:prune and each
// channel's rotation). History events live in storage/logs/history instead.
$prunableDays = (int) env('LOG_PRUNABLE_DAYS', 45);

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

    // Raw webhook/API bodies behind coded events (AppLog::payload). Off by
    // default: they can hold customer PII. Switch on briefly to debug an
    // integration; files are kept 14 days.
    'payloads_enabled' => (bool) env('LOG_PAYLOADS', false),

    // Two retention classes, chosen per event (App\Logging\Enums\Retention):
    // prunable lines are deleted after `prunable_days`; history lines are kept
    // for `history_days`, where 0 means forever. See docs/logging/README.md.
    'retention' => [
        'prunable_days' => $prunableDays,
        'history_days' => (int) env('LOG_HISTORY_DAYS', 0),
    ],

    'channels' => [

        'stack' => [
            'driver' => 'stack',
            'channels' => explode(',', (string) env('LOG_STACK', 'single')),
            'ignore_exceptions' => false,
        ],

        'single' => [
            'driver' => 'single',
            'tap' => [UseCodedFormat::class],
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        'daily' => [
            'driver' => 'daily',
            'tap' => [UseCodedFormat::class],
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => $prunableDays,
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
            'tap' => [UseCodedFormat::class],
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
            'tap' => [UseCodedFormat::class],
            'path' => storage_path('logs/jnt-webhooks.log'),
            'level' => 'debug',
            'days' => $prunableDays,
            'replace_placeholders' => true,
        ],

        // Outbound sends and agent replies for the WhatsApp order-confirmation
        // flow: SendWhatsAppOrderMessageJob, ProcessWhatsAppFollowUps, the agent
        // reply endpoint. What we told a customer and when is a HISTORY event
        // (storage/logs/history); this file holds the operational lines. Inbound traffic is split out to `whatsapp_webhooks` below —
        // same separation this file already uses for iMile and J&T.
        'whatsapp' => [
            'driver' => 'daily',
            'tap' => [UseCodedFormat::class],
            'path' => storage_path('logs/whatsapp.log'),
            'level' => 'debug',
            'days' => $prunableDays,
            'replace_placeholders' => true,
        ],

        // Everything Meta POSTs or GETs at /webhooks/whatsapp: the subscription
        // handshake, inbound customer replies, and delivery/read receipts —
        // plus rejected signatures, which is the main thing worth tailing this
        // file for while wiring up a new webhook URL.
        'whatsapp_webhooks' => [
            'driver' => 'daily',
            'tap' => [UseCodedFormat::class],
            'path' => storage_path('logs/whatsapp-webhooks.log'),
            'level' => 'debug',
            'days' => $prunableDays,
            'replace_placeholders' => true,
        ],

        'imile_webhooks' => [
            'driver' => 'daily',
            'tap' => [UseCodedFormat::class],
            'path' => storage_path('logs/imile-webhooks.log'),
            'level' => 'debug',
            'days' => $prunableDays,
            'replace_placeholders' => true,
        ],

        // LogesTechs pushes each status change exactly once with no retry
        // ("only status 200, its send one time"), so this log is the only
        // record of a push we failed to process. A failed push is logged as an
        // event with its body in the payloads log when LOG_PAYLOADS=true.
        //
        // Switched off (no file is written) unless LOGESTECHS_LOGS_ENABLED=true.
        'logestechs_webhooks' => env('LOGESTECHS_LOGS_ENABLED', false) ? [
            'driver' => 'daily',
            'tap' => [UseCodedFormat::class],
            'path' => storage_path('logs/logestechs-webhooks.log'),
            'level' => 'debug',
            'days' => $prunableDays,
            'replace_placeholders' => true,
        ] : [
            'driver' => 'monolog',
            'handler' => NullHandler::class,
        ],

        // Outbound LogesTechs API traffic (the above is inbound only). Split
        // out because LogesTechs publishes no error-code catalogue and returns
        // empty bodies on 5xx — the request we sent is the only thing left to
        // debug from, so LogesTechsDriver logs the payload on a server error.
        // Keeping that out of the shared app log makes it findable, and bounds
        // how long recipient names/phones/addresses linger in plain text.
        //
        // Switched off (no file is written) unless LOGESTECHS_LOGS_ENABLED=true.
        'logestechs' => env('LOGESTECHS_LOGS_ENABLED', false) ? [
            'driver' => 'daily',
            'tap' => [UseCodedFormat::class],
            'path' => storage_path('logs/logestechs-api.log'),
            'level' => 'debug',
            'days' => $prunableDays,
            'replace_placeholders' => true,
        ] : [
            'driver' => 'monolog',
            'handler' => NullHandler::class,
        ],

        // Everything Shopify — OAuth/token exchange, webhook delivery and
        // registration, order sync, and the GDPR compliance topics — lands here
        // rather than in the shared app log. The GDPR compliance topics
        // (customers/redact, shop/redact) are HISTORY events, kept in
        // storage/logs/history beyond this file's prunable window.
        // Every outbound email — the message envelope on the way out, the
        // confirmed handoff to the transport, and any transport failure with
        // its full exception chain. Split out from the app log because SMTP
        // failures are otherwise buried under unrelated stack traces.
        'mail' => [
            'driver' => 'daily',
            'tap' => [UseCodedFormat::class],
            'path' => storage_path('logs/mail.log'),
            'level' => 'debug',
            'days' => $prunableDays,
            'replace_placeholders' => true,
        ],

        'payloads' => env('LOG_PAYLOADS', false) ? [
            'driver' => 'daily',
            'tap' => [UseCodedFormat::class],
            'path' => storage_path('logs/payloads.log'),
            'level' => 'debug',
            'days' => 14,
        ] : [
            'driver' => 'monolog',
            'handler' => NullHandler::class,
        ],

        // Business history (Retention::HISTORY events): who did what to which
        // record. IDs only. One file per day, never pruned unless
        // LOG_HISTORY_DAYS is set; back this folder up off-server.
        'history' => [
            'driver' => 'daily',
            'tap' => [UseCodedFormat::class],
            'path' => storage_path('logs/history/history.log'),
            'level' => 'debug',
            'days' => (int) env('LOG_HISTORY_DAYS', 0),
        ],

        // Exception detail (class, message, location, trace) behind the
        // fingerprint `exf` of a coded line. Written once per fingerprint per
        // day. This is the only log that may hold prose.
        'exceptions' => [
            'driver' => 'daily',
            'tap' => [UseCodedFormat::class],
            'path' => storage_path('logs/exceptions.log'),
            'level' => 'debug',
            'days' => $prunableDays,
            'replace_placeholders' => true,
        ],

        'shopify' => [
            'driver' => 'daily',
            'tap' => [UseCodedFormat::class],
            'path' => storage_path('logs/shopify.log'),
            'level' => 'debug',
            'days' => $prunableDays,
            'replace_placeholders' => true,
        ],

    ],

];
