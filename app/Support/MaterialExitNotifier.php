<?php

namespace App\Support;

use App\Models\MaterialExit;
use App\Models\User;
use App\Notifications\MaterialExitNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Decide a quién avisar en cada momento de una salida de material.
 * Los destinatarios se limitan al condominio de la obra.
 */
class MaterialExitNotifier
{
    public static function requested(MaterialExit $exit): void
    {
        self::send(self::approvers($exit), $exit, 'solicitada');
    }

    public static function approved(MaterialExit $exit): void
    {
        self::send(self::guards($exit)->push($exit->requester)->filter()->unique('id'), $exit, 'aprobada');
    }

    public static function rejected(MaterialExit $exit): void
    {
        self::send(collect([$exit->requester])->filter(), $exit, 'rechazada');
    }

    public static function retired(MaterialExit $exit): void
    {
        self::send(self::approvers($exit), $exit, 'retirada');
    }

    /** Propietarios de la casa de la obra y administradores del condominio */
    public static function approvers(MaterialExit $exit): Collection
    {
        $work = $exit->work()->with('property.owners')->first();

        $admins = User::role('Administrador')
            ->where('condominium_id', $work->condominium_id)
            ->get();

        return $work->property->owners
            ->filter(fn (User $u) => $u->hasRole('Propietario'))
            ->merge($admins)
            ->unique('id')
            ->values();
    }

    private static function guards(MaterialExit $exit): Collection
    {
        return User::role('Vigilante')->where('condominium_id', $exit->work->condominium_id)->get();
    }

    private static function send(Collection $users, MaterialExit $exit, string $event): void
    {
        if ($users->isEmpty()) {
            return;
        }

        $exit->loadMissing('work.property', 'requester', 'approver', 'entry');

        Notification::send($users, new MaterialExitNotification($exit, $event));
    }
}
