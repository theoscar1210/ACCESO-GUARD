<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Condominium extends Model
{
    protected $table = 'condominiums';

    protected $fillable = [
        'name', 'slug', 'address', 'city', 'country', 'phone',
        'email', 'logo', 'timezone', 'is_active', 'settings',
    ];

    protected $casts = ['is_active' => 'boolean', 'settings' => 'array'];

    protected static function booted(): void
    {
        static::creating(function (Condominium $condominium) {
            $condominium->slug ??= Str::slug($condominium->name);
        });
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(Entry::class);
    }

    public function announcements(): HasMany
    {
        return $this->hasMany(Announcement::class);
    }
}
