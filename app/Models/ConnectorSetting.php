<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class ConnectorSetting extends Model
{
    protected $fillable = [
        'connector_id',
        'key',
        'value',
        'is_encrypted',
    ];

    protected $casts = [
        'is_encrypted' => 'boolean',
    ];

    /**
     * Encrypt on the way to the database, once both `value` and `is_encrypted`
     * are known.
     *
     * This deliberately does NOT live in a `value` mutator. Mass assignment
     * fills attributes in array order, so `updateOrCreate([...], ['value' =>
     * ..., 'is_encrypted' => true])` sets `value` while `is_encrypted` is still
     * false on a *new* row — a mutator would store the secret in plain text and
     * then flag it as encrypted, after which every read fails to decrypt and
     * silently returns null. That bug only shows up on the first save of a
     * given key (a later save sees the flag already persisted), which makes it
     * especially easy to miss.
     */
    protected static function booted(): void
    {
        static::saving(function (self $setting): void {
            $value = $setting->attributes['value'] ?? null;

            if (! $setting->is_encrypted || $value === null || $value === '') {
                return;
            }

            // Re-saving a loaded row would otherwise double-encrypt it.
            if (! static::isEncryptedPayload($value)) {
                $setting->attributes['value'] = Crypt::encryptString($value);
            }
        });
    }

    /**
     * Whether a stored value is already a Laravel-encrypted payload, as opposed
     * to plain text that still needs encrypting.
     */
    public static function isEncryptedPayload(string $value): bool
    {
        try {
            Crypt::decryptString($value);

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function connector()
    {
        return $this->belongsTo(Connector::class);
    }

    public function getValueAttribute($value)
    {
        if ($this->is_encrypted && $value) {
            try {
                return Crypt::decryptString($value);
            } catch (\Exception $e) {
                return null;
            }
        }

        return $value;
    }

    public static function getForConnector(string $connectorKey, string $settingKey): ?string
    {
        $connector = Connector::where('key', $connectorKey)->first();

        if (! $connector) {
            return null;
        }

        $setting = static::where('connector_id', $connector->id)
            ->where('key', $settingKey)
            ->first();

        return $setting?->value;
    }

    public static function setForConnector(string $connectorKey, string $settingKey, $value, bool $encrypt = false): void
    {
        $connector = Connector::where('key', $connectorKey)->first();

        if (! $connector) {
            return;
        }

        static::updateOrCreate(
            ['connector_id' => $connector->id, 'key' => $settingKey],
            ['value' => $value, 'is_encrypted' => $encrypt],
        );
    }

    public static function getAllForConnector(string $connectorKey): array
    {
        $connector = Connector::where('key', $connectorKey)->first();

        if (! $connector) {
            return [];
        }

        return static::where('connector_id', $connector->id)
            ->get()
            ->pluck('value', 'key')
            ->toArray();
    }
}
