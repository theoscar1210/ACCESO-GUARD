<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Bitácora de decisiones sobre una obra: quién la creó, aprobó, cambió o cerró */
class WorkLog extends Model
{
    protected $fillable = ['work_id', 'user_id', 'action', 'notes'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
