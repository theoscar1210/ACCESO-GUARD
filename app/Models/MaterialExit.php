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

    /** Horas que dura una aprobación antes de vencer si nadie retira el material */
    public const VALID_HOURS = 48;

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
            'expires_at' => 'datetime',
            'executed_at' => 'datetime',
        ];
    }

    /** Aprobadas y dentro de su vigencia: listas para retirar en portería */
    public function scopeRetirable($query)
    {
        return $query->where('status', 'aprobada')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    /** Aprobadas cuya vigencia ya pasó sin que nadie las retirara */
    public function scopeExpired($query)
    {
        return $query->where('status', 'aprobada')->whereNotNull('expires_at')->where('expires_at', '<=', now());
    }

    /** Estado real: una aprobación vencida se muestra como vencida aunque la tarea no haya corrido */
    public function getEffectiveStatusAttribute(): string
    {
        return $this->status === 'aprobada' && $this->expires_at?->isPast() ? 'vencida' : $this->status;
    }

    public function approve(User $user): void
    {
        $this->forceFill([
            'status' => 'aprobada',
            'approved_by' => $user->id,
            'approved_at' => now(),
            'expires_at' => now()->addHours(self::VALID_HOURS),
        ])->save();
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
            'status' => $this->effective_status,
            'notes' => $this->notes,
            'house' => $this->work?->property?->full_label,
            'work_title' => $this->work?->title,
            'requested_by' => $this->requester?->full_name,
            'approved_by' => $this->approver?->full_name,
            'expires_at' => $this->expires_at?->format('d/m/Y H:i'),
            'executed_by' => $this->executor?->username,
            // Quién lo retiró: nombre, cédula, placa, fecha y hora
            'taken_by' => $this->entry?->full_name,
            'taken_cedula' => $this->entry?->cedula,
            'exit_plate' => $this->exit_plate,
            'created_at' => $this->created_at->format('d/m/Y H:i'),
            'executed_at' => $this->executed_at?->format('d/m/Y H:i'),
        ];
    }
}
