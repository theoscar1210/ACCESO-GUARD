<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Entrada, salida o "queda en la casa" de una herramienta o material */
class ItemMovement extends Model
{
    protected $fillable = [
        'work_item_id',
        'entry_id',
        'work_worker_id',
        'direction',
        'quantity',
        'is_transfer',
        'photo_path',
        'registered_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'is_transfer' => 'boolean',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(WorkItem::class, 'work_item_id');
    }

    /** Ingreso durante el cual se registró el movimiento (trabajador o proveedor) */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(Entry::class);
    }

    /** Trabajador que movió el ítem (puede no ser el dueño: traspaso) */
    public function mover(): BelongsTo
    {
        return $this->belongsTo(WorkWorker::class, 'work_worker_id');
    }

    public function registrar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }
}
