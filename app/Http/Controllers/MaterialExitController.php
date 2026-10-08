<?php

namespace App\Http\Controllers;

use App\Models\MaterialExit;
use App\Models\Work;
use App\Support\MaterialExitNotifier;
use App\Support\WorkPermissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Salidas de material de una obra (sobrantes, escombros, devoluciones).
 * Cualquiera que vea la obra puede solicitarla; la aprueban el propietario de
 * la casa, el administrador o el superusuario, y la aprobación vale 48 horas.
 * Si la solicita alguien que puede aprobar, queda aprobada de una vez.
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
            'status' => 'pendiente',
            'requested_by' => $user->id,
        ]);

        $work->log('salida_material_solicitada', $user, "{$data['description']} ({$data['quantity']})");

        if ($autoApproved) {
            // Quien la pide puede aprobarla: queda lista en portería y se avisa a los vigilantes
            $exit->approve($user);
            MaterialExitNotifier::approved($exit);

            return back()->with('success', 'Salida de material autorizada por 48 horas. Los vigilantes ya fueron avisados.');
        }

        // Aprobación no inmediata: se avisa al propietario y al administrador
        MaterialExitNotifier::requested($exit);

        return back()->with('success', 'Salida de material solicitada. Se avisó al propietario y al administrador para que la aprueben.');
    }

    public function decide(Request $request, Work $work, MaterialExit $materialExit): RedirectResponse
    {
        $user = $request->user();
        abort_unless($materialExit->work_id === $work->id && WorkPermissions::canApproveMaterialExit($user, $work), 403);

        $data = $request->validate(['action' => 'required|in:aprobar,rechazar']);

        if ($materialExit->status === 'ejecutada') {
            throw ValidationException::withMessages(['material_exit' => 'Este material ya salió; no se puede cambiar.']);
        }

        if ($data['action'] === 'aprobar') {
            $materialExit->approve($user);
            MaterialExitNotifier::approved($materialExit);
        } else {
            $materialExit->forceFill([
                'status' => 'rechazada',
                'approved_by' => $user->id,
                'approved_at' => now(),
                'expires_at' => null,
            ])->save();
            MaterialExitNotifier::rejected($materialExit);
        }

        $status = $materialExit->status;
        $work->log("salida_material_{$status}", $user, $materialExit->description);

        return back()->with('success', $status === 'aprobada'
            ? 'Salida de material aprobada por 48 horas. Los vigilantes ya fueron avisados.'
            : 'Salida de material rechazada.');
    }
}
