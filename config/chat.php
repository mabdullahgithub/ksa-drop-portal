<?php

/**
 * Live chat: support conversations between clients (portal) and the team.
 * See App\Services\Chat\*.
 */
return [

    // A resolved conversation is marked closed after this many days
    // (chats:auto-close, daily).
    'auto_close_days' => (int) env('CHAT_AUTO_CLOSE_DAYS', 7),

    // Longest message body, in characters.
    'max_body' => 5000,

    'attachments' => [
        // Private disk: files are only ever streamed through a gated route.
        'disk' => 'local',
        'max_files' => 5,
        'max_kb' => 10240,
        // Never svg or html: both can carry script.
        'mimes' => ['jpg', 'jpeg', 'png', 'webp', 'gif', 'pdf', 'csv', 'txt', 'xlsx', 'xls', 'docx', 'doc'],
        'images' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
    ],

    // Keep with each message where it was sent from (IP, location, network,
    // browser). CHAT_RECORD_ORIGIN=false stops it and the database downloads.
    'record_origin' => (bool) env('CHAT_RECORD_ORIGIN', true),

    // IP location for message details: DB-IP's free databases, kept on this
    // server and refreshed by chat:update-geoip.
    'geoip' => [
        'directory' => env('CHAT_GEOIP_PATH', storage_path('app/geoip')),
        'url' => 'https://download.db-ip.com/free/dbip-{database}-lite-{month}.mmdb.gz',
    ],

    // Orders and inventory products a client can attach to one message.
    'max_shared_items' => 5,

    // Voice messages, recorded in the browser by either side.
    'voice' => [
        'max_seconds' => 180,
        'max_kb' => 10240,
        // What a recording is detected as => [served as, file extension].
        // Audio-only recordings are often detected as the video container.
        'types' => [
            'audio/mp4' => ['audio/mp4', 'm4a'],
            'video/mp4' => ['audio/mp4', 'm4a'],
            'audio/x-m4a' => ['audio/mp4', 'm4a'],
            'audio/webm' => ['audio/webm', 'webm'],
            'video/webm' => ['audio/webm', 'webm'],
            'audio/ogg' => ['audio/ogg', 'ogg'],
            'application/ogg' => ['audio/ogg', 'ogg'],
        ],
    ],

    // The email to a client about a reply waits this long, and is only sent if
    // the reply is still unread by then.
    'reply_mail_delay_minutes' => (int) env('CHAT_REPLY_MAIL_DELAY', 3),

    // Clients poll at these intervals when the live connection is down or not
    // configured (shared with the browser through the page props).
    'poll' => [
        'thread_ms' => 5000,
        'inbox_ms' => 20000,
    ],
];
