<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCondominium;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use BelongsToCondominium, HasFactory, HasRoles, Notifiable, TwoFactorAuthenticatable;

    protected $fillable = [
        'first_name',
        'last_name',
        'cedula',
        'phone',
        'username',
        'email',
        'password',
        'avatar',
    ];

    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /** Nombre completo como `name`, que es lo que espera el frontend */
    protected $appends = ['name'];

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function getNameAttribute(): string
    {
        return $this->full_name;
    }

    /** Propiedades que posee (propietario) */
    public function ownedProperties(): BelongsToMany
    {
        return $this->belongsToMany(Property::class, 'property_ownerships')
            ->withPivot('since_date')
            ->withTimestamps();
    }

    /** Arrendamiento activo (residente) */
    public function activeRental(): HasOne
    {
        return $this->hasOne(PropertyRental::class)->where('is_active', true)->latestOfMany('start_date');
    }

    /** Historial de arrendamientos (residente) */
    public function rentals(): HasMany
    {
        return $this->hasMany(PropertyRental::class);
    }

    /** Núcleo familiar registrado */
    public function familyMembers(): HasMany
    {
        return $this->hasMany(FamilyMember::class);
    }

    /** Autorizaciones emitidas (propietario) */
    public function authorizations(): HasMany
    {
        return $this->hasMany(Authorization::class);
    }

    /** Registros de entrada por este usuario (vigilante registró) */
    public function entries(): HasMany
    {
        return $this->hasMany(Entry::class);
    }

    /** Push subscriptions para notificaciones web */
    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class);
    }

    /**
     * Obtiene el número de inmueble principal de este usuario.
     * Para propietario: primera propiedad propia.
     * Para residente: propiedad del arrendamiento activo.
     */
    public function getPropertyNumberAttribute(): ?string
    {
        $role = $this->getRoleNames()->first();

        if ($role === 'Propietario') {
            return $this->ownedProperties()->first()?->number;
        }

        if ($role === 'Residente') {
            return $this->activeRental?->property?->number;
        }

        return null;
    }
}
