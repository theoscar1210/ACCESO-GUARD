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
