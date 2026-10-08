<?php

namespace App\Http\Controllers;

use App\Models\MaterialExit;
use App\Models\Work;
use App\Support\WorkPermissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Salidas de material de una obra (sobrantes, escombros, devoluciones).
 * Cualquiera que vea la obra puede solicitarla; la aprueban el propietario o
 * residente de la casa, el administrador o el superusuario. Si la solicita
 * alguien que puede aprobar, queda aprobada de una vez.
 */
class MaterialExitController extends Controller
{
    public function store(Request $request, Work $work): RedirectResponse
    {
        $user = $request->user();
        abort_unless(WorkPermissions::canView($user, $work), 403);

        $data = $request->validate([
            'description' => 'required|string|max:150',
            'quantity' => 'required|string|max:50',
            'reason' => ['required', Rule::in(MaterialExit::REASONS)],
            'notes' => 'nullable|string|max:255',
        ]);

        $autoApproved = WorkPermissions::canApproveMaterialExit($user, $work);

        $exit = $work->materialExits()->create([
            ...$data,
            'status' => $autoApproved ? 'aprobada' : 'pendiente',
            'requested_by' => $user->id,
        ]);

        if ($autoApproved) {
            $exit->forceFill(['approved_by' => $user->id, 'approved_at' => now()])->save();
        }

        $work->log('salida_material_solicitada', $user, "{$data['description']} ({$data['quantity']})");

        return back()->with('success', $autoApproved
            ? 'Salida de material autorizada. El vigilante la verá al registrar la salida.'
            : 'Salida de material solicitada. Queda pendiente de aprobación.');
    }

    public function decide(Request $request, Work $work, MaterialExit $materialExit): RedirectResponse
    {
        $user = $request->user();
        abort_unless($materialExit->work_id === $work->id && WorkPermissions::canApproveMaterialExit($user, $work), 403);

        $data = $request->validate(['action' => 'required|in:aprobar,rechazar']);

        if ($materialExit->status === 'ejecutada') {
            throw ValidationException::withMessages(['material_exit' => 'Este material ya salió; no se puede cambiar.']);
        }

        $status = $data['action'] === 'aprobar' ? 'aprobada' : 'rechazada';

        $materialExit->forceFill([
            'status' => $status,
            'approved_by' => $user->id,
            'approved_at' => now(),
        ])->save();

        $work->log("salida_material_{$status}", $user, $materialExit->description);

        return back()->with('success', "Salida de material {$status}.");
    }
}
