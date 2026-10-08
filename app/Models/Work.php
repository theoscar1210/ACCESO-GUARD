<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCondominium;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Obra en un inmueble: arreglo locativo o construcción, con sus trabajadores
 * y el inventario de herramientas y materiales que ingresan.
 */
class Work extends Model
{
    use BelongsToCondominium;

    public const STATUSES = ['pendiente', 'aprobada', 'rechazada', 'suspendida', 'cerrada'];

    public const TYPES = ['locativa', 'construccion'];

    protected $fillable = [
        'property_id',
        'title',
        'type',
        'contractor_company',
        'contractor_name',
        'contractor_document',
        'contractor_phone',
        'start_date',
        'end_date',
        'schedule',
        'description',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'decided_at' => 'datetime',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function workers(): HasMany
    {
        return $this->hasMany(WorkWorker::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(WorkItem::class);
    }

    public function materialExits(): HasMany
    {
        return $this->hasMany(MaterialExit::class)->latest();
    }

    public function logs(): HasMany
    {
        return $this->hasMany(WorkLog::class)->latest();
    }

    /** Aprobada y dentro de sus fechas: sus trabajadores pueden ingresar herramientas */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('status', 'aprobada')
            ->whereDate('start_date', '<=', today())
            ->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', today()));
    }

    public function isCurrent(): bool
    {
        return $this->status === 'aprobada'
            && $this->start_date->lte(today())
            && ($this->end_date === null || $this->end_date->gte(today()));
    }

    public function log(string $action, ?User $user = null, ?string $notes = null): void
    {
        $this->logs()->create([
            'user_id' => $user?->id,
            'action' => $action,
            'notes' => $notes,
        ]);
    }
}
