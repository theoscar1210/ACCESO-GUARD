<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Entry;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EntryController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Entry::with('exit')
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('entry_at', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('entry_at', '<=', $request->date_to))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->type))
            ->when($request->filled('apartment'), fn ($q) => $q->where(function ($q) use ($request) {
                $q->where('apartment', 'like', "%{$request->apartment}%");

                // "admin", "administración"... también busca los ingresos a la administración
                if (str_contains('administracion administración', mb_strtolower($request->apartment))) {
                    $q->orWhere('to_administration', true);
                }
            }))
            ->when($request->filled('search'), fn ($q) => $q->where(function ($q) use ($request) {
                $q->where('first_name', 'like', "%{$request->search}%")
                    ->orWhere('last_name', 'like', "%{$request->search}%")
                    ->orWhere('cedula', 'like', "%{$request->search}%");
            }))
            ->orderByDesc('entry_at');

        $entries = $query->paginate(25)->onEachSide(1)->withQueryString()->through(fn ($e) => [
            'id' => $e->id,
            'full_name' => $e->full_name,
            'cedula' => $e->cedula,
            'apartment' => $e->destination,
            'type' => $e->type,
            'vehicle' => $e->vehicle,
            'plate' => $e->plate,
            'entry_at' => $e->entry_at->format('d/m/Y H:i'),
            'exit_at' => $e->exit?->exited_at?->format('d/m/Y H:i'),
            'is_inside' => is_null($e->exit),
            'registered_by' => $e->registered_by,
        ]);

        $filters = $request->only(['date_from', 'date_to', 'type', 'apartment', 'search']);

        return Inertia::render('admin/Entries/Index', compact('entries', 'filters'));
    }
}
