<?php

namespace App\Http\Controllers\Residente;

use App\Http\Controllers\Controller;
use App\Models\Entry;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HistoryController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $apartment = $user->property_number;

        $entries = Entry::with('exit')
            // Sin arrendamiento activo no hay historial que mostrar
            ->when($apartment, fn ($q) => $q->where('apartment', $apartment), fn ($q) => $q->whereRaw('1 = 0'))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->type))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('entry_at', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('entry_at', '<=', $request->date_to))
            ->orderByDesc('entry_at')
            ->paginate(20)->onEachSide(1)
            ->withQueryString()
            ->through(fn ($e) => [
                'id'       => $e->id,
                'full_name'=> $e->full_name,
                'cedula'   => $e->cedula,
                'type'     => $e->type,
                'vehicle'  => $e->vehicle,
                'plate'    => $e->plate,
                'entry_at' => $e->entry_at->format('d/m/Y H:i'),
                'exit_at'  => $e->exit?->exited_at?->format('d/m/Y H:i'),
                'is_inside'=> is_null($e->exit),
            ]);

        $filters = $request->only(['type', 'date_from', 'date_to']);

        return Inertia::render('residente/History/Index', compact('entries', 'filters', 'apartment'));
    }
}
