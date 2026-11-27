<?php

/**
 * Live chat: support conversations between clients (portal) and the team.
 * See App\Services\Chat\*.
 */
return [

    // A resolved conversation the client has not answered is closed after this
    // many days (chats:auto-close, daily).
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
