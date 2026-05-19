<?php

/**
 * Test Email Sending
 *
 * This script sends a test welcome email
 * Run: php artisan tinker
 * Then paste this code
 */

use App\Models\User;
use App\Models\EmailSetting;
use App\Models\EmailLog;
use App\Notifications\WelcomeNotification;

echo "📧 Email System Test\n";
echo "===================\n\n";

// Check email settings
$settings = EmailSetting::getActive();
if (!$settings) {
    echo "❌ No email settings found!\n";
    echo "   Run: php artisan tinker < setup_email_test.php\n";
    exit;
}

echo "Email System Status: " . ($settings->is_active ? "✅ Active" : "⚠️  Inactive") . "\n";
echo "Driver: {$settings->driver}\n";
echo "Host: {$settings->host}\n";
echo "From: {$settings->from_address}\n\n";

if (!$settings->is_active) {
    echo "⚠️  Email system is not active!\n";
    echo "   Enable it in the database or admin panel\n\n";
}

// Get test user
$user = User::first();
if (!$user) {
    echo "❌ No users found! Create a user first.\n";
    exit;
}

echo "Test User: {$user->name} ({$user->email})\n\n";

// Check user preferences
$pref = $user->preference;
if (!$pref) {
    echo "⚠️  No preferences found, creating defaults...\n";
    $pref = $user->preference()->create([
        'toast_position' => 'bottom-right',
        'notification_type' => 'all',
        'communication_emails' => true,
        'social_emails' => true,
        'marketing_emails' => false,
        'security_emails' => true,
        'user_management_emails' => true,
        'in_app_notifications' => true,
    ]);
}

echo "User Email Preferences:\n";
echo "  Communication Emails: " . ($pref->communication_emails ? "✅" : "❌") . "\n";
echo "  Social Emails: " . ($pref->social_emails ? "✅" : "❌") . "\n";
echo "  Marketing Emails: " . ($pref->marketing_emails ? "✅" : "❌") . "\n";
echo "  User Management: " . ($pref->user_management_emails ? "✅" : "❌") . "\n\n";

// Send test email
echo "🚀 Sending welcome email...\n";

try {
    $user->notify(new WelcomeNotification());
    echo "✅ Notification dispatched!\n\n";

    // Wait a moment for queue to process
    sleep(2);

    // Check email log
    $log = EmailLog::latest()->first();
    if ($log) {
        echo "📋 Email Log:\n";
        echo "  Recipient: {$log->recipient_email}\n";
        echo "  Subject: {$log->subject}\n";
        echo "  Type: {$log->email_type}\n";
        echo "  Status: {$log->status}\n";

        if ($log->status === 'failed') {
            echo "  Error: {$log->error_message}\n";
        }

        if ($log->status === 'sent') {
            echo "  Sent at: {$log->sent_at}\n";
        }

        echo "\n";
    }

    echo "✨ Test complete!\n";
    echo "\n📬 Check your Mailtrap inbox for the email.\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n💡 Tips:\n";
echo "  - Make sure queue worker is running: php artisan queue:work\n";
echo "  - Check email logs: EmailLog::latest()->get()\n";
echo "  - Preview email: return new \\App\\Mail\\Auth\\WelcomeMail(\$user);\n";
