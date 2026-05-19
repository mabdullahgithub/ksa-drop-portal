<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class EmailSetting extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'is_active',
        'driver',
        'host',
        'port',
        'username',
        'password',
        'encryption',
        'from_address',
        'from_name',
        'test_email',
        'last_tested_at',
        'test_status',
        'test_error',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'port' => 'integer',
        'last_tested_at' => 'datetime',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
    ];

    /**
     * Get the password status (for API responses).
     */
    protected function passwordStatus(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->password ? 'set' : 'not_set',
        );
    }

    /**
     * Check if password is set.
     */
    public function hasPassword(): bool
    {
        return ! empty($this->attributes['password'] ?? null);
    }

    /**
     * Get the email configuration as an array for Laravel Mail config.
     */
    public function toMailConfig(): array
    {
        $password = null;
        if ($this->password) {
            try {
                $password = decrypt($this->password);
            } catch (\Exception $e) {
                \Log::error('Failed to decrypt email password for mail config', [
                    'error' => $e->getMessage(),
                    'setting_id' => $this->id,
                ]);
            }
        }

        return [
            'transport' => $this->driver,
            'host' => $this->host,
            'port' => $this->port,
            'username' => $this->username,
            'password' => $password,
            'encryption' => $this->encryption === 'none' ? null : $this->encryption,
            'from' => [
                'address' => $this->from_address,
                'name' => $this->from_name,
            ],
        ];
    }

    /**
     * Encrypt password before saving.
     */
    protected function password(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                if (! $value) {
                    return null;
                }

                try {
                    return decrypt($value);
                } catch (\Exception $e) {
                    \Log::error('Failed to decrypt email password', [
                        'error' => $e->getMessage(),
                        'setting_id' => $this->id,
                    ]);

                    return null;
                }
            },
            set: fn ($value) => $value ? encrypt($value) : null,
        );
    }

    /**
     * Get the active email settings (singleton pattern).
     */
    public static function getActive(): ?self
    {
        return self::where('is_active', true)->first() ?? self::first();
    }
}
