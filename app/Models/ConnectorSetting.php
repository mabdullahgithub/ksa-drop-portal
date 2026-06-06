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

    public function setValueAttribute($value)
    {
        if ($this->is_encrypted && $value) {
            $this->attributes['value'] = Crypt::encryptString($value);
        } else {
            $this->attributes['value'] = $value;
        }
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
