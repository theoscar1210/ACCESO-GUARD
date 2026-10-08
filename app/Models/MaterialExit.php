<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Autorización para sacar material de una obra (sobrantes, escombros,
 * devoluciones). Sin aprobación del propietario o del admin no sale nada.
 */
class MaterialExit extends Model
{
    public const REASONS = ['sobrante', 'escombro', 'devolucion', 'otro'];

    protected $fillable = [
        'work_id',
        'description',
        'quantity',
        'reason',
        'status',
        'requested_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'executed_at' => 'datetime',
        ];
    }

    public function work(): BelongsTo
    {
        return $this->belongsTo(Work::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function executor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executed_by');
    }

    /** Ingreso de la persona que sacó el material */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(Entry::class);
    }

    public function payload(): array
    {
        return [
            'id' => $this->id,
            'description' => $this->description,
            'quantity' => $this->quantity,
            'reason' => $this->reason,
            'status' => $this->status,
            'notes' => $this->notes,
            'requested_by' => $this->requester?->full_name,
            'approved_by' => $this->approver?->full_name,
            'executed_by' => $this->executor?->username,
            'taken_by' => $this->entry?->full_name,
            'created_at' => $this->created_at->format('d/m/Y H:i'),
            'executed_at' => $this->executed_at?->format('d/m/Y H:i'),
        ];
    }
}
