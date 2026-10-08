<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\User;
use App\Models\Work;

/**
 * Reglas de obras: qué roles pueden crearlas, si necesitan aprobación y quién
 * decide. El superusuario configura las reglas; administrador y superusuario
 * pueden revisar y revertir cualquier decisión.
 */
class WorkPermissions
{
    public const SETTING_KEY = 'works.roles';

    /** Roles configurables y sus valores por defecto */
    public const DEFAULTS = [
        'Administrador' => ['can_create' => true, 'requires_approval' => false],
        'Propietario' => ['can_create' => true, 'requires_approval' => true],
        'Residente' => ['can_create' => true, 'requires_approval' => true],
        'Vigilante' => ['can_create' => true, 'requires_approval' => false],
    ];

    /** @return array<string, array{can_create: bool, requires_approval: bool}> */
    public static function roles(): array
    {
        $saved = Setting::get(self::SETTING_KEY, []);

        $roles = [];
        foreach (self::DEFAULTS as $role => $defaults) {
            $roles[$role] = [
                'can_create' => (bool) ($saved[$role]['can_create'] ?? $defaults['can_create']),
                'requires_approval' => (bool) ($saved[$role]['requires_approval'] ?? $defaults['requires_approval']),
            ];
        }

        return $roles;
    }

    public static function save(array $roles): void
    {
        $clean = [];
        foreach (array_keys(self::DEFAULTS) as $role) {
            $clean[$role] = [
                'can_create' => (bool) ($roles[$role]['can_create'] ?? false),
                'requires_approval' => (bool) ($roles[$role]['requires_approval'] ?? false),
            ];
        }

        Setting::put(self::SETTING_KEY, $clean);
    }

    public static function isSuperuser(User $user): bool
    {
        return $user->hasRole('Superusuario');
    }

    /** Administrador y superusuario: aprueban, rechazan, editan, suspenden, cierran y reabren */
    public static function canDecide(User $user): bool
    {
        return $user->hasAnyRole(['Superusuario', 'Administrador']);
    }

    public static function canCreate(User $user): bool
    {
        if (self::isSuperuser($user)) {
            return true;
        }

        $role = $user->getRoleNames()->first();

        return (bool) (self::roles()[$role]['can_create'] ?? false);
    }

    /** Estado con el que nace una obra según quién la crea */
    public static function initialStatus(User $user): string
    {
        if (self::canDecide($user)) {
            return 'aprobada';
        }

        $role = $user->getRoleNames()->first();

        return (self::roles()[$role]['requires_approval'] ?? true) ? 'pendiente' : 'aprobada';
    }

    /** Propietario y residente solo ven y crean obras de su propio inmueble */
    public static function isRestrictedToOwnProperty(User $user): bool
    {
        return $user->hasAnyRole(['Propietario', 'Residente']) && ! self::canDecide($user);
    }

    /**
     * Aprobar salidas de material: administrador, superusuario y el propietario
     * de la casa. Residente y vigilante solo pueden solicitarlas.
     */
    public static function canApproveMaterialExit(User $user, Work $work): bool
    {
        return self::canDecide($user)
            || ($user->hasRole('Propietario') && $user->ownedProperties()->whereKey($work->property_id)->exists());
    }

    public static function canView(User $user, Work $work): bool
    {
        if (! self::isRestrictedToOwnProperty($user)) {
            return true;
        }

        return $work->property?->number === $user->property_number;
    }
}
