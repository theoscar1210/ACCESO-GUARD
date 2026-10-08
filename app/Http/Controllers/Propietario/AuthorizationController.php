<?php

namespace App\Http\Controllers\Propietario;

use App\Http\Controllers\Controller;
use App\Models\Authorization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuthorizationController extends Controller
{
    public function index(Request $request): Response
    {
        $authorizations = Authorization::where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'full_name' => $a->full_name,
                'cedula' => $a->cedula,
                'plate' => $a->plate,
                'type' => $a->type,
                'status' => $a->status,
                'start_date' => $a->start_date->format('d/m/Y'),
                'end_date' => $a->end_date?->format('d/m/Y H:i'),
                'observations' => $a->observations,
            ]);

        return Inertia::render('propietario/Authorizations/Index', compact('authorizations'));
    }

    public function create(): Response
    {
        return Inertia::render('propietario/Authorizations/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'cedula' => 'required|string|max:20',
            'plate' => 'nullable|string|max:20',
            'type' => 'required|in:visitante,autorizado',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'nullable|date|after:start_date',
            'observations' => 'nullable|string',
        ]);

        Authorization::create([
            ...$data,
            'plate' => filled($data['plate'] ?? null) ? strtoupper(trim($data['plate'])) : null,
            'user_id' => $request->user()->id,
            'status' => 'activo',
        ]);

        return redirect()->route('propietario.authorizations.index')
            ->with('success', 'Autorización creada correctamente.');
    }

    public function destroy(Request $request, Authorization $authorization): RedirectResponse
    {
        if ($authorization->user_id !== $request->user()->id) {
            abort(403);
        }

        $authorization->delete();

        return back()->with('success', 'Autorización eliminada.');
    }
}
