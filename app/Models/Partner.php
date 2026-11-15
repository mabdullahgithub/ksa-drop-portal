<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A member of the partner program. The person signs in as a normal portal
 * user with the `partner` role; this row holds the application, the code
 * in their referral link, their points and how they want to be paid.
 */
class Partner extends Model
{
    use SoftDeletes;

    public const TYPE_CLIENT = 'client';

    public const TYPE_AGENCY = 'agency';

    public const TYPE_INFLUENCER = 'influencer';

    public const TYPES = [self::TYPE_CLIENT, self::TYPE_AGENCY, self::TYPE_INFLUENCER];

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUSES = [self::STATUS_PENDING, self::STATUS_APPROVED, self::STATUS_REJECTED, self::STATUS_SUSPENDED];

    public const TIERS = ['bronze', 'silver', 'gold'];

    public const ID_DOCUMENT_TYPES = ['national_id', 'iqama', 'passport'];

    protected $fillable = [
        'user_id',
        'client_id',
        'type',
        'status',
        'code',
        'code_customized_at',
        'display_name',
        'company_name',
        'phone',
        'whatsapp',
        'country',
        'city',
        'website',
        'social_links',
        'audience_size',
        'promotion_plan',
        'motivation',
        'id_document_type',
        'tier',
        'payout_method',
        'terms_version',
        'terms_accepted_at',
        'applied_ip',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
        'approved_at',
        'admin_notes',
    ];

    protected $casts = [
        'social_links' => 'array',
        'payout_method' => 'encrypted:array',
        'points_balance' => 'integer',
        'terms_accepted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    protected $hidden = [
        'id_document_path',
        'id_document_back_path',
        'logo_path',
        'applied_ip',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /** The client account this partner also owns, if any. */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** The clients this partner brought in. */
    public function referredClients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(PartnerLead::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PartnerPointTransaction::class);
    }

    public function payoutRequests(): HasMany
    {
        return $this->hasMany(PartnerPayoutRequest::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by')->withTrashed();
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function hasIdDocument(): bool
    {
        return (bool) ($this->id_document_path || $this->id_document_back_path);
    }

    public function referralUrl(): string
    {
        return rtrim(config('app.url'), '/') . '/r/' . $this->code;
    }

    /** Referred clients that had their first delivery. */
    public function activeReferralCount(): int
    {
        return $this->referredClients()->whereNotNull('partner_activated_at')->count();
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }
}
