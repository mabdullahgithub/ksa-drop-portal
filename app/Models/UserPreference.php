<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPreference extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'toast_position',
        'notification_type',
        'mobile_notifications',
        'communication_emails',
        'social_emails',
        'marketing_emails',
        'security_emails',
        'user_management_emails',
        'in_app_notifications',
        // Individual email preferences
        'verify_email',
        'reset_password',
        'password_changed',
        'two_factor_enabled',
        'two_factor_disabled',
        'welcome_user',
        'user_created_admin',
        'user_updated',
        'welcome',
        'settings_updated',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'mobile_notifications' => 'boolean',
        'communication_emails' => 'boolean',
        'social_emails' => 'boolean',
        'marketing_emails' => 'boolean',
        'security_emails' => 'boolean',
        'user_management_emails' => 'boolean',
        'in_app_notifications' => 'boolean',
        // Individual email preferences
        'verify_email' => 'boolean',
        'reset_password' => 'boolean',
        'password_changed' => 'boolean',
        'two_factor_enabled' => 'boolean',
        'two_factor_disabled' => 'boolean',
        'welcome_user' => 'boolean',
        'user_created_admin' => 'boolean',
        'user_updated' => 'boolean',
        'welcome' => 'boolean',
        'settings_updated' => 'boolean',
    ];

    /**
     * Get the user that owns the preference.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
