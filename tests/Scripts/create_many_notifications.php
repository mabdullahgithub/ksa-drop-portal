<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\DB;

// Get the first user
$user = User::first();

if (!$user) {
    echo "No users found. Please create a user first.\n";
    exit(1);
}

$count = $argv[1] ?? 25;

echo "Creating {$count} test notifications for user: {$user->name} ({$user->email})\n";

$types = [
    'settings_updated',
    'profile_updated',
    'password_changed',
    'preferences_updated',
];

$titles = [
    'Profile Settings Updated',
    'Security Settings Changed',
    'Notification Preferences Updated',
    'Account Settings Modified',
    'Password Successfully Changed',
    'Profile Information Updated',
    'Display Preferences Changed',
    'Privacy Settings Updated',
];

$messages = [
    'Your profile settings have been successfully updated',
    'Your security settings have been changed',
    'Your notification preferences have been updated',
    'Your account settings have been modified',
    'Your password has been successfully changed',
    'Your profile information has been updated',
    'Your display preferences have been changed',
    'Your privacy settings have been updated',
];

$notifications = [];
$now = now();

for ($i = 0; $i < $count; $i++) {
    $typeIndex = array_rand($types);
    $titleIndex = array_rand($titles);
    $messageIndex = array_rand($messages);
    
    // Mix of read and unread (70% unread, 30% read)
    $isRead = rand(1, 10) <= 3;
    
    // Random time in the past (up to 7 days ago)
    $minutesAgo = rand(5, 10080); // 5 minutes to 7 days
    $createdAt = $now->copy()->subMinutes($minutesAgo);
    
    $notifications[] = [
        'id' => \Illuminate\Support\Str::uuid(),
        'type' => 'App\Notifications\SettingsUpdatedNotification',
        'notifiable_type' => 'App\Models\User',
        'notifiable_id' => $user->id,
        'data' => json_encode([
            'title' => $titles[$titleIndex],
            'message' => $messages[$messageIndex],
            'type' => $types[$typeIndex],
            'setting_type' => $types[$typeIndex],
            'changes' => ['updated' => true],
            'action_url' => route('settings'),
            'icon' => 'settings',
        ]),
        'read_at' => $isRead ? $now->copy()->subMinutes(rand(1, $minutesAgo)) : null,
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ];
}

// Sort by created_at descending (newest first)
usort($notifications, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});

DB::table('notifications')->insert($notifications);

$unread = count(array_filter($notifications, fn($n) => $n['read_at'] === null));

echo "\n✅ Successfully created {$count} test notifications!\n";
echo "   • Unread: {$unread}\n";
echo "   • Read: " . ($count - $unread) . "\n";
echo "\nYou can now:\n";
echo "   1. Click the bell icon to see dropdown with 15 latest\n";
echo "   2. Visit /notifications to see paginated list (20 per page)\n";
echo "\nTo create more, run:\n";
echo "   php create_many_notifications.php [count]\n";
echo "   Example: php create_many_notifications.php 50\n";
