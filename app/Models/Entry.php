<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCondominium;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Entry extends Model
{
    use BelongsToCondominium;

    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'cedula',
        'apartment',
        'to_administration',
        'work_worker_id',
        'work_id',
        'supplier_company',
        'type',
        'vehicle',
        'plate',
        'registered_by',
        'observations',
        'entry_at',
    ];

    protected function casts(): array
    {
        return [
            'entry_at' => 'datetime',
            'to_administration' => 'boolean',
        ];
    }

    /** Destino legible: "101", "Administración" o "101 · Administración" */
    public function getDestinationAttribute(): string
    {
        return collect([$this->apartment, $this->to_administration ? 'Administración' : null])
            ->filter()
            ->implode(' · ');
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Trabajador de obra con el que se registró el ingreso (si aplica) */
    public function workWorker(): BelongsTo
    {
        return $this->belongsTo(WorkWorker::class);
    }

    /** Obra a la que un proveedor entregó material (si aplica) */
    public function work(): BelongsTo
    {
        return $this->belongsTo(Work::class);
    }

    /** Obra relacionada: la del trabajador o la de la entrega del proveedor */
    public function relatedWork(): ?Work
    {
        return $this->workWorker?->work ?? $this->work;
    }

    public function exit(): HasOne
    {
        return $this->hasOne(ExitRecord::class);
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function scopeActive($query)
    {
        return $query->whereDoesntHave('exit');
    }

    public function scopeToday($query)
    {
        return $query->whereDate('entry_at', today());
    }
}
