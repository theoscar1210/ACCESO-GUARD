<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Authorization;
use App\Models\Property;
use App\Models\Entry;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $stats = [
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
