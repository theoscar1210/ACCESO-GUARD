<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCondominium;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Property extends Model
{
    use BelongsToCondominium;

    protected $fillable = [
        'number',
        'block',
        'type',
        'description',
    ];

    /** Propietarios que poseen este inmueble */
    public function owners(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'property_ownerships')
            ->withPivot('since_date')
            ->withTimestamps();
    }

    /** Arrendatario activo */
    public function activeRental(): HasOne
    {
        return $this->hasOne(PropertyRental::class)->where('is_active', true)->latestOfMany('start_date');
    }

    /** Historial de arrendamientos */
    public function rentals(): HasMany
    {
        return $this->hasMany(PropertyRental::class);
    }

    /** Nombre completo del inmueble para mostrar */
    public function getFullLabelAttribute(): string
    {
        return $this->block ? "{$this->block} - {$this->number}" : $this->number;
    }
}
