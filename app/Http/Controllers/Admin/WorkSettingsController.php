<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\WorkPermissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Configuración de obras por rol: solo el superusuario */
class WorkSettingsController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('admin/Settings/Works', [
            'roles' => WorkPermissions::roles(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $rules = [];
        foreach (array_keys(WorkPermissions::DEFAULTS) as $role) {
            $rules["roles.{$role}.can_create"] = 'required|boolean';
            $rules["roles.{$role}.requires_approval"] = 'required|boolean';
        }

        $data = $request->validate($rules);

        WorkPermissions::save($data['roles']);

        return back()->with('success', 'Configuración de obras guardada.');
    }
}
