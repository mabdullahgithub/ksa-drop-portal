<?php

namespace App\Models;

use App\Models\Concerns\RestrictedByClientAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A support conversation between one client and the team.
 *
 * How one changes lives in App\Services\Chat\ChatWorkflow (status, details)
 * and ChatMessenger (messages); who may see it in ChatVisibility.
 */
class ChatConversation extends Model
{
    use RestrictedByClientAccess, SoftDeletes;

    public const OPEN = 'open';

    public const PENDING = 'pending';

    public const ON_HOLD = 'on_hold';

    public const RESOLVED = 'resolved';

    public const CLOSED = 'closed';

    public const STATUSES = [self::OPEN, self::PENDING, self::ON_HOLD, self::RESOLVED, self::CLOSED];

    /** Still being worked on: what the inbox shows by default. */
    public const ACTIVE = [self::OPEN, self::PENDING, self::ON_HOLD];

    public const CATEGORIES = ['order_issue', 'shipment', 'inventory', 'billing', 'account', 'technical', 'general'];

    public const PRIORITIES = ['low', 'normal', 'high', 'urgent'];

    public const BY_CLIENT = 'client';

    public const BY_STAFF = 'staff';

    protected $fillable = [
        'client_id',
        'order_id',
        'subject',
        'category',
        'priority',
        'status',
        'created_by',
        'opened_by',
        'last_message_at',
        'last_client_message_at',
        'last_staff_message_at',
        'first_response_at',
        'waiting_since',
        'resolved_at',
        'resolved_by',
        'closed_at',
        'reopened_count',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'last_client_message_at' => 'datetime',
        'last_staff_message_at' => 'datetime',
        'first_response_at' => 'datetime',
        'waiting_since' => 'datetime',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
        'reopened_count' => 'integer',
    ];

    protected static function booted(): void
    {
        static::created(function (ChatConversation $conversation) {
            $conversation->forceFill(['reference' => self::referenceFor($conversation->id)])->saveQuietly();
        });
    }

    public static function referenceFor(int $id): string
    {
        return 'CH-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by')->withTrashed();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'conversation_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ChatAttachment::class, 'conversation_id');
    }

    public function reads(): HasMany
    {
        return $this->hasMany(ChatRead::class, 'conversation_id');
    }

    /** The newest message or note: the team's list preview. */
    public function latestTeamMessage(): HasOne
    {
        return $this->hasOne(ChatMessage::class, 'conversation_id')
            ->ofMany(['id' => 'max'], fn ($query) => $query->whereIn('kind', [ChatMessage::MESSAGE, ChatMessage::NOTE]));
    }

    /** The newest message the client can see: the portal's list preview. */
    public function latestClientMessage(): HasOne
    {
        return $this->hasOne(ChatMessage::class, 'conversation_id')
            ->ofMany(['id' => 'max'], fn ($query) => $query->where('kind', ChatMessage::MESSAGE)->where('internal', false));
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', self::ACTIVE);
    }

    /**
     * Resolved or closed: nobody can write in it. The client starts a new
     * chat; the team can reopen it (`change chat status`) if it was resolved
     * by mistake.
     */
    public function isLocked(): bool
    {
        return in_array($this->status, [self::RESOLVED, self::CLOSED], true);
    }
}
