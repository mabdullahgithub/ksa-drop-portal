<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * How far one person has read one conversation.
 */
class ChatRead extends Model
{
    public $timestamps = false;

    protected $fillable = ['conversation_id', 'user_id', 'last_read_message_id', 'read_at'];

    protected $casts = [
        'last_read_message_id' => 'integer',
        'read_at' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'conversation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
