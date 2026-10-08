<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Authorization;
use App\Models\Entry;
use App\Models\MaterialExit;
use App\Models\Property;
use App\Models\User;
use App\Models\Work;
use App\Models\WorkItem;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        // Obras: pendientes de aprobación y herramientas dentro de obras que ya no están vigentes
        $toolsInStaleWorks = WorkItem::tools()->inside()
            ->whereHas('work', fn ($q) => $q->where(fn ($q) => $q
                ->where('status', '!=', 'aprobada')
                ->orWhereDate('end_date', '<', today())))
            ->sum('quantity_inside');

        $stats = [
            'works_pending' => Work::where('status', 'pendiente')->count(),
            'works_current' => Work::current()->count(),
            'tools_alert' => (int) $toolsInStaleWorks,
            'material_exits_pending' => MaterialExit::where('status', 'pendiente')
                ->whereHas('work')->count(),
            'users' => User::count(),
            'properties' => Property::count(),
            'entries_today' => Entry::today()->count(),
            'inside' => Entry::active()->count(),
            'authorizations' => Authorization::active()->count(),
            'by_type' => [
                'propietario' => Entry::active()->where('type', 'propietario')->count(),
                'autorizado' => Entry::active()->where('type', 'autorizado')->count(),
                'visitante' => Entry::active()->where('type', 'visitante')->count(),
            ],
        ];

        $recent = Entry::with('exit')
            ->today()
            ->orderByDesc('entry_at')
            ->limit(8)
            ->get()
            ->map(fn ($e) => [
                'id' => $e->id,
                'full_name' => $e->full_name,
                'apartment' => $e->destination,
                'type' => $e->type,
                'entry_at' => $e->entry_at->format('H:i'),
                'is_inside' => is_null($e->exit),
            ]);

        return Inertia::render('admin/Dashboard', compact('stats', 'recent'));
    }
}
