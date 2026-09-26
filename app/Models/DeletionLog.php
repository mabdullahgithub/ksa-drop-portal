<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeletionLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'subject_type', 'subject_id', 'subject_label', 'action',
        'user_id', 'user_name', 'ip', 'user_agent', 'device', 'created_at',
    ];

    protected $casts = ['created_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
