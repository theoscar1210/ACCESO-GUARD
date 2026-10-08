<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Herramienta o material de una obra, a nombre del trabajador que lo ingresó
 * y de la casa donde trabaja. quantity_inside es el saldo actual dentro.
 */
class WorkItem extends Model
{
    public const KINDS = ['herramienta', 'material'];

    protected $fillable = [
        'work_id',
        'property_id',
        'work_worker_id',
        'name',
        'serial',
        'kind',
        'quantity_inside',
        'photo_path',
    ];

    protected function casts(): array
    {
        return ['quantity_inside' => 'integer'];
    }

    public function work(): BelongsTo
    {
        return $this->belongsTo(Work::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(WorkWorker::class, 'work_worker_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(ItemMovement::class);
    }

    public function scopeInside(Builder $query): Builder
    {
        return $query->where('quantity_inside', '>', 0);
    }

    public function scopeTools(Builder $query): Builder
    {
        return $query->where('kind', 'herramienta');
    }

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->photo_path ? asset('storage/'.$this->photo_path) : null;
    }
}
