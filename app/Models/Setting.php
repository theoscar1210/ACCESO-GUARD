<?php

namespace App\Models;

use App\Support\CurrentCondominium;
use Illuminate\Database\Eloquent\Model;

/** Configuración clave/valor, por condominio (o global si no hay condominio) */
class Setting extends Model
{
    protected $fillable = ['condominium_id', 'key', 'value'];

    protected function casts(): array
    {
        return ['value' => 'array'];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return static::where('key', $key)
            ->where('condominium_id', CurrentCondominium::id())
            ->value('value') ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        static::updateOrCreate(
            ['key' => $key, 'condominium_id' => CurrentCondominium::id()],
            ['value' => $value],
        );
    }
}
