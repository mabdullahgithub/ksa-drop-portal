<?php

/**
 * Quick Email System Test Setup
 *
 * This script sets up email configuration for testing with Mailtrap
 * Run: php artisan tinker < setup_email_test.php
 */

use App\Models\EmailSetting;
use App\Models\User;
use App\Notifications\WelcomeNotification;

echo "🚀 Setting up Email System for Testing...\n\n";

// Create or update email settings
$settings = EmailSetting::first() ?? new EmailSetting();

$settings->fill([
    'is_active' => false, // Set to true after adding your credentials
    'driver' => 'smtp',
    'host' => 'smtp.mailtrap.io', // Replace with your Mailtrap host
    'port' => 2525,
    'username' => 'your_mailtrap_username', // Replace with your username
    'password' => 'your_mailtrap_password', // Replace with your password
    'encryption' => 'tls',
    'from_address' => 'noreply@example.com',
    'from_name' => config('app.name'),
]);

$settings->save();

echo "✅ Email settings created/updated\n";
echo "⚠️  Remember to:\n";
echo "   1. Update the username and password with your Mailtrap credentials\n";
echo "   2. Set is_active to true\n";
echo "   3. Start queue worker: php artisan queue:work\n\n";

// Create default user preferences if needed
$user = User::first();
if ($user && !$user->preference) {
    $user->preference()->create([
        'toast_position' => 'bottom-right',
        'notification_type' => 'all',
        'mobile_notifications' => false,
        'communication_emails' => true,
        'social_emails' => true,
        'marketing_emails' => false,
        'security_emails' => true,
        'user_management_emails' => true,
        'in_app_notifications' => true,
    ]);
    echo "✅ User preferences created for: {$user->email}\n\n";
}

echo "📧 To test email sending:\n";
echo "   php artisan tinker\n";
echo "   >>> \$user = User::first();\n";
echo "   >>> \$user->notify(new \\App\\Notifications\\WelcomeNotification());\n\n";

echo "🔍 To check email logs:\n";
echo "   >>> \\App\\Models\\EmailLog::latest()->first();\n\n";

echo "✨ Setup complete!\n";
