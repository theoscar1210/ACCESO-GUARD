<?php

namespace App\Http\Controllers\Vigilante;

use App\Http\Controllers\Controller;
use App\Models\Authorization;
use App\Models\Entry;
use App\Models\ExitRecord;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $stats = [
            'inside' => Entry::active()->count(),
            'entries_today' => Entry::today()->count(),
            'exits_today' => ExitRecord::whereDate('exited_at', today())->count(),
            'authorizations' => Authorization::active()->count(),
        ];

        $inside = Entry::active()
            ->orderByDesc('entry_at')
            ->limit(10)
            ->get()
            ->map(fn ($e) => [
                'id' => $e->id,
                'full_name' => $e->full_name,
                'apartment' => $e->destination,
                'type' => $e->type,
                'entry_at' => $e->entry_at->format('H:i'),
            ]);

        return Inertia::render('vigilante/Dashboard', compact('stats', 'inside'));
    }
}
