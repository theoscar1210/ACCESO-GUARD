<?php

namespace App\Models\Concerns;

use App\Models\Condominium;
use App\Support\CurrentCondominium;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Aísla los registros por condominio: filtra las consultas por el condominio
 * del usuario autenticado y asigna ese condominio a los registros nuevos.
 * Sin condominio actual (consola, seeders, usuarios globales) no se filtra.
 */
trait BelongsToCondominium
{
    public static function bootBelongsToCondominium(): void
    {
        static::addGlobalScope('condominium', function (Builder $query) {
            if ($id = CurrentCondominium::id()) {
                $query->where($query->qualifyColumn('condominium_id'), $id);
            }
        });

        static::creating(function ($model) {
            $model->condominium_id ??= CurrentCondominium::id();
        });
    }

    public function condominium(): BelongsTo
    {
        return $this->belongsTo(Condominium::class);
    }
}
