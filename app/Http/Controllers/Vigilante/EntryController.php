<?php

namespace App\Http\Controllers\Vigilante;

use App\Http\Controllers\Controller;
use App\Models\Authorization;
use App\Models\Entry;
use App\Models\FamilyMember;
use App\Models\Property;
use App\Models\User;
use App\Models\Work;
use App\Models\WorkItem;
use App\Models\WorkWorker;
use App\Support\WorkInventory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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

        // Obras vigentes: los proveedores entregan material a una de ellas
        $currentWorks = Work::current()->with('property')->orderBy('title')->get()
            ->map(fn (Work $w) => [
                'id' => $w->id,
                'title' => $w->title,
                'property_number' => $w->property->number,
                'property' => $w->property->full_label,
            ]);

        return Inertia::render('vigilante/Entries/Create', [
            'properties' => $properties,
            'current_works' => $currentWorks,
        ]);
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
            'type' => 'required|in:propietario,residente,autorizado,visitante,proveedor',
            // Proveedor: obra a la que entrega y empresa (ferretería, depósito…)
            'work_id' => 'nullable|required_if:type,proveedor|integer',
            'supplier_company' => 'nullable|required_if:type,proveedor|string|max:150',
            'vehicle' => [
                'required',
                'in:automovil,camioneta,moto,bicicleta,ninguno',
                // Con placa debe indicarse en qué tipo de vehículo llega
                Rule::notIn($request->filled('plate') ? ['ninguno'] : []),
            ],
            'plate' => 'nullable|string|max:20',
            'observations' => 'nullable|string',
            // Obras: trabajador y herramientas/materiales que ingresa
            'work_worker_id' => 'nullable|integer|exists:work_workers,id',
            'items' => 'nullable|array|max:40',
            'items.*.item_id' => 'nullable|integer',
            'items.*.name' => 'required_without:items.*.item_id|nullable|string|max:120',
            'items.*.serial' => 'nullable|string|max:60',
            // Al proveedor no se le pide el tipo: todo lo que entrega es material
            'items.*.kind' => ['exclude_if:type,proveedor', 'required_without:items.*.item_id', 'nullable', Rule::in(WorkItem::KINDS)],
            'items.*.quantity' => 'required|integer|min:1|max:9999',
            'items.*.photo' => 'nullable|image|max:8192',
        ], [
            'apartment.required' => 'Selecciona el destino.',
            'vehicle.required' => 'Selecciona el tipo de vehículo.',
            'vehicle.not_in' => 'Selecciona el tipo de vehículo de la placa.',
            'items.*.name.required_without' => 'Escribe qué herramienta o material ingresa.',
            'items.*.photo.max' => 'La foto no puede pesar más de 8 MB.',
            'work_id.required_if' => 'Selecciona la obra a la que entrega el material.',
            'supplier_company.required_if' => 'Escribe la empresa del proveedor.',
        ]);

        $worker = null;
        $deliveryWork = null;

        if ($data['type'] === 'proveedor') {
            $deliveryWork = Work::current()->with('property')->find($data['work_id']);

            if (! $deliveryWork) {
                throw ValidationException::withMessages(['work_id' => 'La obra seleccionada no está aprobada o vigente.']);
            }

            // El proveedor solo entrega material; el destino es la casa de la obra
            $data['apartment'] = $deliveryWork->property->number;
            $data['work_worker_id'] = null;
            $data['items'] = collect($data['items'] ?? [])
                ->map(fn ($row) => [...$row, 'kind' => 'material', 'item_id' => null])
                ->all();

            foreach ($data['items'] as $i => $row) {
                if (blank($row['name'] ?? null)) {
                    throw ValidationException::withMessages(["items.{$i}.name" => 'Escribe qué material entrega.']);
                }
            }
        } else {
            $data['work_id'] = null;
            $data['supplier_company'] = null;
        }

        // Trabajador de obra (la entrega de proveedor ya se validó arriba)
        if (! $deliveryWork && ! empty($data['work_worker_id'])) {
            $worker = WorkWorker::with('work')->find($data['work_worker_id']);

            if (! $worker || $worker->cedula !== $data['cedula']) {
                throw ValidationException::withMessages(['work_worker_id' => 'La cédula no corresponde al trabajador de la obra.']);
            }

            if (! $worker->work->isCurrent()) {
                throw ValidationException::withMessages([
                    'work_worker_id' => "La obra «{$worker->work->title}» no está aprobada o vigente: no se pueden registrar herramientas.",
                ]);
            }
        } elseif (! $deliveryWork && ! empty($data['items'])) {
            throw ValidationException::withMessages(['items' => 'Solo trabajadores o proveedores de una obra pueden ingresar herramientas o materiales.']);
        }

        $items = $data['items'] ?? [];
        unset($data['items']);

        $data['to_administration'] = $request->boolean('to_administration');
        $data['plate'] = filled($data['plate'] ?? null) ? strtoupper(trim($data['plate'])) : null;

        // Verificar que no tenga ingreso activo (sin salida)
        $activeEntry = Entry::where('cedula', $data['cedula'])
            ->active()
            ->first();

        if ($activeEntry) {
            throw ValidationException::withMessages([
                'active_entry' => 'Ya hay un ingreso activo para esta cédula. Registra la salida antes de permitir un nuevo ingreso.',
            ]);
        }

        DB::transaction(function () use ($data, $request, $worker, $deliveryWork, $items) {
            // Marcar autorización como usada si existe
            Authorization::active()
                ->where('cedula', $data['cedula'])
                ->first()
                ?->update(['status' => 'usado']);

            $entry = Entry::create([
                ...$data,
                'user_id' => $request->user()->id,
                'registered_by' => $request->user()->username,
                'entry_at' => now(),
            ]);

            if ($worker && $items) {
                WorkInventory::registerEntry($entry, $worker, $items, $request->user());
            }

            if ($deliveryWork && $items) {
                WorkInventory::registerDelivery($entry, $deliveryWork, $items, $request->user());
            }
        });

        $count = count($items);

        return redirect()->route('vigilante.entries.index')
            ->with('success', $count ? "Ingreso registrado con {$count} ítem(s) de obra." : 'Ingreso registrado correctamente.');
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
     * Lookup by license plate. If the plate has entries, the form is filled with
     * the last one (person, destination and vehicle); otherwise it falls back to
     * an active authorization registered with that plate.
     */
    public function lookupByPlate(Request $request): JsonResponse
    {
        $plate = $this->normalizePlate($request->query('plate', ''));

        if (strlen($plate) < 3) {
            return response()->json(null);
        }

        $lastEntry = $this->wherePlate(Entry::query(), $plate)->latest('entry_at')->first();

        if ($lastEntry) {
            $cedula = $lastEntry->cedula;
            $authorization = Authorization::active()->where('cedula', $cedula)->latest()->first();

            return response()->json([
                'cedula' => $cedula,
                ...$this->describePerson($cedula, $authorization, $lastEntry),
                // El destino y el vehículo se toman tal cual del último ingreso
                'apartment' => $lastEntry->apartment,
                'to_administration' => $lastEntry->to_administration,
                'vehicle' => $lastEntry->vehicle,
                'plate' => $lastEntry->plate,
                'source' => 'entry',
                'last_entry_at' => $lastEntry->entry_at->format('d/m/Y H:i'),
            ]);
        }

        $authorization = $this->wherePlate(Authorization::active(), $plate)->latest()->first();

        if (! $authorization) {
            return response()->json(null);
        }

        return response()->json([
            'cedula' => $authorization->cedula,
            ...$this->describePerson($authorization->cedula, $authorization, null),
            'to_administration' => false,
            'vehicle' => $authorization->vehicle,
            'plate' => $authorization->plate,
            'source' => 'authorization',
            'last_entry_at' => null,
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
        $worker = WorkInventory::workerFor($cedula);
        $currentWorker = $worker?->work->isCurrent() ? $worker : null;

        if (! $user && ! $familyMember && ! $authorization && ! $lastEntry && ! $worker) {
            return null;
        }

        $registered = $user ?? $familyMember;

        $type = match (true) {
            $user !== null => match ($user->getRoleNames()->first()) {
                'Propietario' => 'propietario',
                'Residente' => 'residente',
                default => null,
            },
            $familyMember !== null, $authorization !== null, $currentWorker !== null => 'autorizado',
            default => $lastEntry?->type,
        };

        // Inmueble: el del usuario o su titular, el de la obra vigente, el de quien autoriza, o el del ingreso anterior
        $apartment = $user?->property_number
            ?? $familyMember?->user->property_number
            ?? $currentWorker?->work->property->number
            ?? $authorization?->owner?->property_number
            ?? $lastEntry?->apartment;

        return [
            'work' => $worker ? WorkInventory::workInfo($worker) : null,
            // Proveedor recurrente: su empresa y la obra de la última entrega si sigue vigente
            'supplier' => $lastEntry?->type === 'proveedor' ? [
                'company' => $lastEntry->supplier_company,
                'work_id' => $lastEntry->work?->isCurrent() ? $lastEntry->work_id : null,
            ] : null,
            'first_name' => $registered?->first_name ?? $worker?->first_name ?? $authorization?->first_name ?? $lastEntry?->first_name,
            'last_name' => $registered?->last_name ?? $worker?->last_name ?? $authorization?->last_name ?? $lastEntry?->last_name,
            'apartment' => $apartment,
            'type' => $type ?? 'visitante',
            'known_in_system' => $registered !== null,
            'authorization' => $authorization ? [
                'type' => $authorization->type,
                'plate' => $authorization->plate,
                'vehicle' => $authorization->vehicle,
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
