<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppMessage extends Model
{
    protected $table = 'whatsapp_messages';

    protected $fillable = [
        'order_id',
        'direction',
        'provider_message_id',
        'template_key',
        'sent_by_user_id',
        'body',
        'to_number',
        'from_number',
        'status',
        'sent_at',
        'delivered_at',
        'read_at',
        'failed_at',
        'error_code',
        'error_message',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public const DIRECTION_OUTBOUND = 'outbound';
    public const DIRECTION_INBOUND = 'inbound';

    /**
     * Meta statuses that mean the message will never arrive — almost always
     * because the number isn't registered on WhatsApp. Worth distinguishing
     * from "not read yet", since there is no point spending a follow-up
     * template on a number that can't receive it.
     *
     * The Cloud API has no `undelivered`; `failed` is the only terminal
     * state, with the reason carried in `error_code` (131026 = not a
     * WhatsApp user, 131047 = 24h window closed, 132000 = template
     * parameter mismatch).
     */
    public const TERMINAL_FAILURE_STATUSES = ['failed'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** Null for system-sent messages (the automated ping and follow-up). */
    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by_user_id');
    }

    public function hasFailed(): bool
    {
        return in_array($this->status, self::TERMINAL_FAILURE_STATUSES, true);
    }
}
