<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkWorker extends Model
{
    protected $fillable = ['work_id', 'first_name', 'last_name', 'cedula', 'phone'];

    public function work(): BelongsTo
    {
        return $this->belongsTo(Work::class);
    }

    /** Herramientas y materiales que ingresó este trabajador */
    public function items(): HasMany
    {
        return $this->hasMany(WorkItem::class);
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }
}
