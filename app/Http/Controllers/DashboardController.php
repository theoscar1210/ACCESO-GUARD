<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request): RedirectResponse
    {
        $user = $request->user();

        return match (true) {
            $user->hasAnyRole(['Superusuario', 'Administrador']) => redirect()->route('admin.dashboard'),
            $user->hasRole('Vigilante') => redirect()->route('vigilante.dashboard'),
            $user->hasRole('Propietario') => redirect()->route('propietario.dashboard'),
            $user->hasRole('Residente') => redirect()->route('residente.dashboard'),
            default => redirect()->route('home'),
        };
    }
}
