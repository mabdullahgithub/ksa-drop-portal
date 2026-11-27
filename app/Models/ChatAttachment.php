<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A file sent with a chat message, on the private disk. Its URL is a gated
 * route (admin or portal), never a public path; see ChatPresenter.
 */
class ChatAttachment extends Model
{
    protected $fillable = [
        'message_id',
        'conversation_id',
        'disk',
        'path',
        'original_name',
        'mime',
        'size',
        'is_voice',
        'duration_seconds',
        'waveform',
        'width',
        'height',
    ];

    protected $casts = [
        'size' => 'integer',
        'is_voice' => 'boolean',
        'duration_seconds' => 'integer',
        'waveform' => 'array',
        'width' => 'integer',
        'height' => 'integer',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'message_id')->withTrashed();
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'conversation_id');
    }

    public function isImage(): bool
    {
        return in_array($this->mime, config('chat.attachments.images'), true);
    }
}
