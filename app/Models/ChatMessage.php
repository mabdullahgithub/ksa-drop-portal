<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One line of a conversation's timeline: a message, an internal note, or a
 * system event. `internal` rows are the team's alone.
 */
class ChatMessage extends Model
{
    use SoftDeletes;

    public const MESSAGE = 'message';

    public const NOTE = 'note';

    public const EVENT = 'event';

    public const FROM_CLIENT = 'client';

    public const FROM_STAFF = 'staff';

    public const FROM_SYSTEM = 'system';

    protected $fillable = [
        'conversation_id',
        'sender_id',
        'sender_type',
        'sender_name',
        'sender_role',
        'kind',
        'internal',
        'body',
        'meta',
        'client_uuid',
        'edited_at',
    ];

    protected $casts = [
        'internal' => 'boolean',
        'meta' => 'array',
        'edited_at' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'conversation_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id')->withTrashed();
    }

    /**
     * Who is sending, as they are now: kept on the message so it still says
     * who wrote it after a rename, a role change or a deleted account.
     *
     * @return array{sender_id: ?int, sender_name: ?string, sender_role: ?string}
     */
    public static function senderSnapshot(?User $user, string $senderType): array
    {
        if (! $user) {
            return ['sender_id' => null, 'sender_name' => null, 'sender_role' => null];
        }

        return [
            'sender_id' => $user->id,
            'sender_name' => $user->name,
            'sender_role' => $senderType === self::FROM_CLIENT ? 'client' : ($user->getRoleNames()->first() ?? 'staff'),
        ];
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ChatAttachment::class, 'message_id');
    }

    /** What the client may see: no notes, no team-only events. */
    public function scopeVisibleToClient(Builder $query): Builder
    {
        return $query->where('internal', false);
    }

    public function isNote(): bool
    {
        return $this->kind === self::NOTE;
    }

    public function isEvent(): bool
    {
        return $this->kind === self::EVENT;
    }
}
