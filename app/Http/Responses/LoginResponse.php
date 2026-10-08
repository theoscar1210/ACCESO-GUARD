<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request)
    {
        $user = $request->user();

        $redirect = match (true) {
            $user->hasAnyRole(['Superusuario', 'Administrador']) => route('admin.dashboard'),
            $user->hasRole('Vigilante') => route('vigilante.dashboard'),
            $user->hasRole('Propietario') => route('propietario.dashboard'),
            $user->hasRole('Residente') => route('residente.dashboard'),
            default => route('dashboard'),
        };

        return redirect()->intended($redirect);
    }
}
