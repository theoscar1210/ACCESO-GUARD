<?php

namespace App\Http\Controllers\Propietario;

use App\Http\Controllers\Controller;
use App\Models\Authorization;
use App\Models\Entry;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $apartment = $user->property_number;

        $stats = [
            'authorizations_active' => Authorization::where('user_id', $user->id)->active()->count(),
            'authorizations_total' => Authorization::where('user_id', $user->id)->count(),
            'inside_now' => $apartment
                ? Entry::active()->where('apartment', $apartment)->count()
                : 0,
        ];

        $authorizations = Authorization::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'full_name' => $a->full_name,
                'cedula' => $a->cedula,
                'type' => $a->type,
                'status' => $a->effective_status,
                'start_date' => $a->start_date?->format('d/m/Y'),
                'end_date' => $a->end_date?->format('d/m/Y'),
            ]);

        return Inertia::render('propietario/Dashboard', compact('stats', 'authorizations'));
    }
}
