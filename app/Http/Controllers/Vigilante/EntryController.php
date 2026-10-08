<?php

namespace App\Http\Controllers\Vigilante;

use App\Http\Controllers\Controller;
use App\Models\Authorization;
use App\Models\Entry;
use App\Models\FamilyMember;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EntryController extends Controller
{
    public function index(): Response
    {
        $entries = Entry::with('exit')
            ->today()
            ->orderByDesc('entry_at')
            ->get()
            ->map(fn ($e) => [
                'id' => $e->id,
                'full_name' => $e->full_name,
                'cedula' => $e->cedula,
                'apartment' => $e->destination,
                'type' => $e->type,
                'vehicle' => $e->vehicle,
                'plate' => $e->plate,
                'entry_at' => $e->entry_at->format('H:i'),
                'is_inside' => is_null($e->exit),
                'registered_by' => $e->registered_by,
            ]);

        // El conteo de "adentro" usa Entry::active() para incluir entradas de días anteriores sin salida
        $activeAll = Entry::active()->get();
        $stats = [
            'total'        => $entries->count(),
            'inside'       => $activeAll->count(),
            'propietario'  => $activeAll->whereIn('type', ['propietario', 'residente'])->count(),
            'autorizado'   => $activeAll->where('type', 'autorizado')->count(),
            'visitante'    => $activeAll->where('type', 'visitante')->count(),
        ];

        return Inertia::render('vigilante/Entries/Index', compact('entries', 'stats'));
    }

    public function create(): Response
    {
        $properties = Property::orderBy('block')
            ->orderBy('number')
            ->get()
            ->map(fn ($p) => [
                'number' => $p->number,
                'label' => $p->full_label,
                'type' => $p->type,
            ]);

        return Inertia::render('vigilante/Entries/Create', compact('properties'));
    }

    public function store(Request $request): RedirectResponse
    {
        // Destino: un inmueble registrado, la administración, o ambos
        $data = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'cedula' => 'required|string|max:20',
            'apartment' => [
                'nullable',
                Rule::requiredIf(! $request->boolean('to_administration')),
                'string',
                'max:20',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (! Property::where('number', $value)->exists()) {
                        $fail('El inmueble seleccionado no está registrado.');
                    }
                },
            ],
            'to_administration' => 'boolean',
            'type' => 'required|in:propietario,residente,autorizado,visitante',
            'vehicle' => 'required|in:automovil,camioneta,moto,bicicleta,ninguno',
            'plate' => 'nullable|string|max:20',
            'observations' => 'nullable|string',
        ], [
            'apartment.required' => 'Selecciona el destino: una casa, un apartamento o Administración.',
        ]);

        $data['to_administration'] = $request->boolean('to_administration');
        $data['plate'] = filled($data['plate'] ?? null) ? strtoupper(trim($data['plate'])) : null;

        // Verificar que no tenga ingreso activo (sin salida)
        $activeEntry = Entry::where('cedula', $data['cedula'])
            ->active()
            ->first();

        if ($activeEntry) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'active_entry' => 'Ya hay un ingreso activo para esta cédula. Registra la salida antes de permitir un nuevo ingreso.',
            ]);
        }

        // Marcar autorización como usada si existe
        Authorization::active()
            ->where('cedula', $data['cedula'])
            ->first()
            ?->update(['status' => 'usado']);

        Entry::create([
            ...$data,
            'user_id' => $request->user()->id,
            'registered_by' => $request->user()->username,
            'entry_at' => now(),
        ]);

        return redirect()->route('vigilante.entries.index')
            ->with('success', 'Ingreso registrado correctamente.');
    }

    /**
     * Lookup by cedula: prioritizes registered users (Propietario/Residente),
     * then active authorizations, then past entry history.
     */
    public function lookup(Request $request): JsonResponse
    {
        $cedula = trim($request->query('cedula', ''));

        if (strlen($cedula) < 3) {
            return response()->json(null);
        }

        $person = $this->describePerson(
            $cedula,
            Authorization::active()->where('cedula', $cedula)->latest()->first(),
            Entry::where('cedula', $cedula)->latest('entry_at')->first(),
        );

        return response()->json($person);
    }

    /**
     * Lookup by license plate: first an active authorization registered with
     * that plate, then the most recent entry with it.
     */
    public function lookupByPlate(Request $request): JsonResponse
    {
        $plate = $this->normalizePlate($request->query('plate', ''));

        if (strlen($plate) < 3) {
            return response()->json(null);
        }

        $authorization = $this->wherePlate(Authorization::active(), $plate)->latest()->first();
        $lastEntry = $this->wherePlate(Entry::query(), $plate)->latest('entry_at')->first();

        $cedula = $authorization?->cedula ?? $lastEntry?->cedula;

        if (! $cedula) {
            return response()->json(null);
        }

        // Si la placa está en una autorización, el último ingreso solo cuenta si es de esa misma persona
        if ($lastEntry && $lastEntry->cedula !== $cedula) {
            $lastEntry = Entry::where('cedula', $cedula)->latest('entry_at')->first();
        }

        $authorization ??= Authorization::active()->where('cedula', $cedula)->latest()->first();

        return response()->json([
            'cedula' => $cedula,
            ...$this->describePerson($cedula, $authorization, $lastEntry),
        ]);
    }

    /**
     * Datos para precargar el formulario de ingreso. Prioridad: usuario o familiar
     * registrado, luego la autorización activa y por último el ingreso anterior.
     */
    private function describePerson(string $cedula, ?Authorization $authorization, ?Entry $lastEntry): ?array
    {
        $user = User::where('cedula', $cedula)->first();
        $familyMember = $user ? null : FamilyMember::with('user')->where('cedula', $cedula)->first();

        if (! $user && ! $familyMember && ! $authorization && ! $lastEntry) {
            return null;
        }

        $registered = $user ?? $familyMember;

        $type = match (true) {
            $user !== null => match ($user->getRoleNames()->first()) {
                'Propietario' => 'propietario',
                'Residente' => 'residente',
                default => null,
            },
            $familyMember !== null, $authorization !== null => 'autorizado',
            default => $lastEntry?->type,
        };

        // Inmueble: el del usuario o su titular, el de quien autoriza, o el del ingreso anterior
        $apartment = $user?->property_number
            ?? $familyMember?->user->property_number
            ?? $authorization?->owner?->property_number
            ?? $lastEntry?->apartment;

        return [
            'first_name' => $registered?->first_name ?? $authorization?->first_name ?? $lastEntry?->first_name,
            'last_name' => $registered?->last_name ?? $authorization?->last_name ?? $lastEntry?->last_name,
            'apartment' => $apartment,
            'type' => $type ?? 'visitante',
            'known_in_system' => $registered !== null,
            'authorization' => $authorization ? [
                'type' => $authorization->type,
                'plate' => $authorization->plate,
                'end_date' => $authorization->end_date?->format('d/m/Y H:i'),
            ] : null,
        ];
    }

    /** Placa sin guiones ni espacios: "abc-123" → "ABC123" */
    private function normalizePlate(string $plate): string
    {
        return preg_replace('/[^A-Z0-9]/', '', strtoupper($plate));
    }

    private function wherePlate(Builder $query, string $normalizedPlate): Builder
    {
        return $query->whereRaw(
            "REPLACE(REPLACE(UPPER(plate), '-', ''), ' ', '') = ?",
            [$normalizedPlate],
        );
    }
}
