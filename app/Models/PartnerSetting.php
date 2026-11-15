<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * The partner program's live settings, one row per key. Falls back to
 * config/partners.php for anything never saved.
 */
class PartnerSetting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    private const CACHE_KEY = 'partner_settings:all';

    /** Keys that are whole numbers. */
    public const INT_KEYS = [
        'approval_bonus_points', 'activation_points', 'delivery_points', 'delivery_months',
        'milestone_deliveries', 'milestone_points', 'min_redeem_points', 'cookie_days',
        'silver_min_active_clients', 'gold_min_active_clients',
    ];

    /** Keys that are decimals. */
    public const FLOAT_KEYS = ['silver_multiplier', 'gold_multiplier'];

    public const STRING_KEYS = ['terms_version'];

    /** @return array<string, int|float|string> */
    public static function all_(): array
    {
        return Cache::remember(self::CACHE_KEY, now()->addMinutes(10), function () {
            $saved = self::query()->pluck('value', 'key')->all();

            return self::cast(array_merge(self::defaults(), $saved));
        });
    }

    public static function get(string $key): int|float|string|null
    {
        return self::all_()[$key] ?? null;
    }

    /** @param  array<string, mixed>  $values */
    public static function put(array $values): void
    {
        foreach ($values as $key => $value) {
            self::updateOrCreate(['key' => $key], ['value' => (string) $value]);
        }

        Cache::forget(self::CACHE_KEY);
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /** @return array<string, int|float|string> */
    public static function defaults(): array
    {
        $config = config('partners');

        return [
            'approval_bonus_points' => $config['approval_bonus_points'],
            'activation_points' => $config['activation_points'],
            'delivery_points' => $config['delivery_points'],
            'delivery_months' => $config['delivery_months'],
            'milestone_deliveries' => $config['milestone_deliveries'],
            'milestone_points' => $config['milestone_points'],
            'min_redeem_points' => $config['min_redeem_points'],
            'cookie_days' => $config['cookie_days'],
            'terms_version' => $config['terms_version'],
            'silver_min_active_clients' => $config['tiers']['silver']['min_active_clients'],
            'silver_multiplier' => $config['tiers']['silver']['multiplier'],
            'gold_min_active_clients' => $config['tiers']['gold']['min_active_clients'],
            'gold_multiplier' => $config['tiers']['gold']['multiplier'],
        ];
    }

    /** @param  array<string, mixed>  $values */
    private static function cast(array $values): array
    {
        foreach ($values as $key => $value) {
            if (in_array($key, self::INT_KEYS, true)) {
                $values[$key] = (int) $value;
            } elseif (in_array($key, self::FLOAT_KEYS, true)) {
                $values[$key] = (float) $value;
            } else {
                $values[$key] = (string) $value;
            }
        }

        return $values;
    }
}
