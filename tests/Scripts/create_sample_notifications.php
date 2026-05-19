<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Notifications\SettingsUpdatedNotification;
use App\Notifications\UserUpdatedNotification;
use Illuminate\Support\Facades\DB;

// Get the first user
$user = User::first();

if (!$user) {
    echo "No users found. Please create a user first.\n";
    exit(1);
}

echo "Creating sample notifications for user: {$user->name} ({$user->email})\n";

// Create sample notifications
$notifications = [
    [
        'id' => \Illuminate\Support\Str::uuid(),
        'type' => 'App\Notifications\SettingsUpdatedNotification',
        'notifiable_type' => 'App\Models\User',
        'notifiable_id' => $user->id,
        'data' => json_encode([
            'title' => 'Profile Settings Updated',
            'message' => 'Your profile settings have been successfully updated',
            'type' => 'settings_updated',
            'setting_type' => 'profile',
            'changes' => ['name' => 'Profile name updated'],
            'action_url' => route('settings'),
            'icon' => 'settings',
        ]),
        'read_at' => null,
        'created_at' => now()->subMinutes(5),
        'updated_at' => now()->subMinutes(5),
    ],
    [
        'id' => \Illuminate\Support\Str::uuid(),
        'type' => 'App\Notifications\SettingsUpdatedNotification',
        'notifiable_type' => 'App\Models\User',
        'notifiable_id' => $user->id,
        'data' => json_encode([
            'title' => 'Security Settings Updated',
            'message' => 'Your security settings have been changed',
            'type' => 'settings_updated',
            'setting_type' => 'security',
            'changes' => ['password' => 'Password changed'],
            'action_url' => route('settings.security'),
            'icon' => 'settings',
        ]),
        'read_at' => null,
        'created_at' => now()->subHour(),
        'updated_at' => now()->subHour(),
    ],
    [
        'id' => \Illuminate\Support\Str::uuid(),
        'type' => 'App\Notifications\UserUpdatedNotification',
        'notifiable_type' => 'App\Models\User',
        'notifiable_id' => $user->id,
        'data' => json_encode([
            'title' => 'User Profile Updated',
            'message' => "User '{$user->name}' information was updated",
            'type' => 'user_updated',
            'user_id' => $user->id,
            'user_name' => $user->name,
            'updated_by' => $user->name,
            'changes' => ['roles' => 'User roles updated'],
            'action_url' => route('team-management.users'),
            'icon' => 'user-check',
        ]),
        'read_at' => now()->subMinutes(30),
        'created_at' => now()->subHours(2),
        'updated_at' => now()->subHours(2),
    ],
    [
        'id' => \Illuminate\Support\Str::uuid(),
        'type' => 'App\Notifications\SettingsUpdatedNotification',
        'notifiable_type' => 'App\Models\User',
        'notifiable_id' => $user->id,
        'data' => json_encode([
            'title' => 'Notification Preferences Updated',
            'message' => 'Your notification preferences have been updated',
            'type' => 'settings_updated',
            'setting_type' => 'notification preferences',
            'changes' => ['in_app_notifications' => true],
            'action_url' => route('settings.notifications'),
            'icon' => 'settings',
        ]),
        'read_at' => null,
        'created_at' => now()->subHours(3),
        'updated_at' => now()->subHours(3),
    ],
];

DB::table('notifications')->insert($notifications);

echo "Successfully created " . count($notifications) . " sample notifications!\n";
echo "You can now view them at /notifications or in the header dropdown.\n";
