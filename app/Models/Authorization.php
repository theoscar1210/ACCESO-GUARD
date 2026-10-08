<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCondominium;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Authorization extends Model
{
    use BelongsToCondominium;

    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'cedula',
        'plate',
        'vehicle',
        'type',
        'status',
        'start_date',
        'end_date',
        'observations',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /**
     * Estado real según la fecha: una autorización "activo" cuya vigencia ya pasó
     * se muestra como "vencido" aunque la tarea programada aún no la haya marcado.
     */
    public function getEffectiveStatusAttribute(): string
    {
        return $this->status === 'activo' && $this->end_date?->isPast()
            ? 'vencido'
            : $this->status;
    }

    /** Autorizaciones marcadas como activas cuya vigencia ya terminó */
    public function scopeExpired($query)
    {
        return $query->where('status', 'activo')
            ->whereNotNull('end_date')
            ->where('end_date', '<', now());
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'activo')
            ->where('start_date', '<=', now())
            ->where(function ($q) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', now());
            });
    }
}
