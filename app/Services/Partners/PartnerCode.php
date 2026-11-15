<?php

namespace App\Services\Partners;

use App\Models\Partner;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The code in a partner's referral link (/r/CODE). Letters and digits only,
 * upper case, 4–20 long, unique; made from the partner's name unless they
 * asked for one of their own.
 */
class PartnerCode
{
    public const PATTERN = '/^[A-Z0-9]{4,20}$/';

    public static function normalize(string $code): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? '');
    }

    public static function isValid(string $code): bool
    {
        return (bool) preg_match(self::PATTERN, $code);
    }

    public static function isAvailable(string $code, ?int $exceptId = null): bool
    {
        $inUse = Partner::withTrashed()->where('code', $code)
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))->exists();

        // A code someone used to hold stays theirs, so shared links never
        // start crediting a stranger.
        $held = DB::table('partner_code_history')->where('code', $code)
            ->when($exceptId, fn ($q) => $q->where('partner_id', '!=', $exceptId))->exists();

        return ! $inUse && ! $held;
    }

    /** A code built from a name, with digits added until it is free. */
    public static function generate(string $name): string
    {
        $base = self::normalize(Str::ascii($name));
        $base = substr($base ?: 'PARTNER', 0, 12);

        if (strlen($base) < 4) {
            $base = str_pad($base, 4, 'X');
        }

        $code = $base;

        while (! self::isAvailable($code)) {
            $code = $base . random_int(10, 9999);
        }

        return $code;
    }

    public static function find(string $code): ?Partner
    {
        $code = self::normalize($code);

        if (! self::isValid($code)) {
            return null;
        }

        $partner = Partner::approved()->where('code', $code)->first();

        if ($partner) {
            return $partner;
        }

        $id = DB::table('partner_code_history')->where('code', $code)->value('partner_id');

        return $id ? Partner::approved()->find($id) : null;
    }

    /** Move a partner to a new code and remember the one they leave behind. */
    public static function change(Partner $partner, string $code, bool $customized = false): void
    {
        if ($partner->code === $code) {
            return;
        }

        DB::transaction(function () use ($partner, $code, $customized) {
            DB::table('partner_code_history')->insertOrIgnore([
                'partner_id' => $partner->id,
                'code' => $partner->code,
                'created_at' => now(),
            ]);

            $partner->forceFill([
                'code' => $code,
                'code_customized_at' => $customized ? now() : $partner->code_customized_at,
            ])->save();
        });
    }

    /** Earlier codes of a partner, newest first. */
    public static function previous(Partner $partner): array
    {
        return DB::table('partner_code_history')->where('partner_id', $partner->id)
            ->orderByDesc('id')->pluck('code')->all();
    }
}
