<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * An email address that turned out not to exist. Once an address is here no
 * email is sent to it again, until an admin restores it from Email Settings.
 */
class EmailGraveyard extends Model
{
    /** The mail server refused the address while we were sending. */
    public const SOURCE_SMTP = 'smtp';

    /** A bounce email reported the address afterwards. */
    public const SOURCE_BOUNCE = 'bounce';

    protected $table = 'email_graveyard';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'email',
        'source',
        'status_code',
        'reason',
        'blocked_count',
        'last_blocked_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'blocked_count' => 'integer',
        'last_blocked_at' => 'datetime',
    ];

    /**
     * Addresses are compared case-insensitively: "Ali@x.com" and "ali@x.com"
     * are the same mailbox to every provider we send through.
     */
    public static function normalize(string $email): string
    {
        return Str::lower(trim($email));
    }

    /**
     * Move an address to the graveyard. Burying it a second time changes
     * nothing, so the first reason on record is the one that is kept.
     */
    public static function bury(string $email, string $source, ?string $statusCode = null, ?string $reason = null): ?self
    {
        $email = self::normalize($email);

        if (! str_contains($email, '@')) {
            return null;
        }

        $entry = self::firstOrCreate(['email' => $email], [
            'source' => $source,
            'status_code' => $statusCode,
            'reason' => $reason === null ? null : Str::limit(trim($reason), 500),
        ]);

        if ($entry->wasRecentlyCreated) {
            Log::channel('mail')->warning('Address moved to graveyard', [
                'email' => $email,
                'source' => $source,
                'status_code' => $statusCode,
                'reason' => $entry->reason,
            ]);
        }

        return $entry;
    }

    /**
     * Which of the given addresses are in the graveyard.
     *
     * @param  array<int, string>  $emails
     * @return array<int, string> normalized addresses
     */
    public static function buriedAmong(array $emails): array
    {
        $emails = array_values(array_unique(array_map(self::normalize(...), $emails)));

        if ($emails === []) {
            return [];
        }

        return self::whereIn('email', $emails)->pluck('email')->all();
    }
}
