<?php

namespace App\Http\Controllers\Vigilante;

use App\Http\Controllers\Controller;
use App\Models\Entry;
use App\Models\ExitRecord;
use App\Models\MaterialExit;
use App\Support\MaterialExitNotifier;
use App\Support\WorkInventory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ExitController extends Controller
{
    public function index(): Response
    {
        // Salidas de material aprobadas y vigentes: listas para retirar en portería
        $retirable = MaterialExit::retirable()
            ->with('work.property', 'requester', 'approver')
            ->oldest('approved_at')
            ->get();

        $materialExitsByProperty = $retirable->countBy(fn ($m) => $m->work->property_id);

        $inside = Entry::with('exit', 'workWorker.work.property', 'work.property')
            ->active()
            ->orderByDesc('entry_at')
            ->get()
            ->map(fn ($e) => [
                'id'           => $e->id,
                'full_name'    => $e->full_name,
                'cedula'       => $e->cedula,
                'apartment'    => $e->destination,
                'type'         => $e->type,
                'vehicle'      => $e->vehicle,
                'plate'        => $e->plate,
                'observations' => $e->observations,
                'entry_at'     => $e->entry_at->format('d/m/Y H:i'),
                // Trabajadores y proveedores de obra: herramientas propias dentro y salidas de material aprobadas
                'work'         => ($work = $e->relatedWork()) ? [
                    'title' => $work->title,
                    'property' => $work->property->full_label,
                    'is_supplier' => $e->workWorker === null,
                    'tools_inside' => $e->workWorker ? WorkInventory::ownToolsInside($e->workWorker) : 0,
                    'material_exits' => $materialExitsByProperty->get($work->property_id, 0),
                ] : null,
            ]);

        return Inertia::render('vigilante/Exits/Index', [
            'inside' => $inside,
            'material_exits' => $retirable->map->payload(),
        ]);
    }

    /**
     * Retiro de material autorizado por una persona con ingreso activo:
     * queda nombre, cédula, placa, fecha y hora, y se registra su salida.
     */
    public function retireMaterial(Request $request, MaterialExit $materialExit): RedirectResponse
    {
        $data = $request->validate([
            'entry_id' => 'required|integer|exists:entries,id',
            'plate' => 'nullable|string|max:20',
        ], ['entry_id.required' => 'Selecciona la persona que retira el material.']);

        $exit = DB::transaction(fn () => WorkInventory::retire(
            $materialExit,
            Entry::findOrFail($data['entry_id']),
            $data['plate'] ?? null,
            $request->user(),
        ));

        MaterialExitNotifier::retired($exit);

        return redirect()->route('vigilante.exits.index')
            ->with('success', "Material retirado y salida registrada: {$exit->description}.");
    }

    /** Herramientas que puede sacar un trabajador: las suyas y las de compañeros de su casa */
    public function tools(Entry $entry): JsonResponse
    {
        return response()->json(WorkInventory::toolsForExit($entry));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'entry_ids' => 'required|array|min:1',
            'entry_ids.*' => 'exists:entries,id',
            'observations' => 'nullable|string',
            'tool_moves' => 'nullable|array',
            'tool_moves.*.entry_id' => 'required|integer|in_array:entry_ids.*',
            'tool_moves.*.item_id' => 'required|integer|exists:work_items,id',
            'tool_moves.*.action' => 'required|in:sale,queda',
            'tool_moves.*.quantity' => 'nullable|integer|min:1',
            'material_exits' => 'nullable|array',
            'material_exits.*.entry_id' => 'required|integer|in_array:entry_ids.*',
            'material_exits.*.id' => 'required|integer|distinct',
        ]);

        $retired = collect();

        $count = DB::transaction(function () use ($request, &$retired) {
            $entries = Entry::with('workWorker.work', 'work')->active()->whereIn('id', $request->entry_ids)->get();

            // Primero herramientas y material: si algo no cuadra no se registra ninguna salida
            WorkInventory::registerExit($entries, $request->input('tool_moves', []), $request->user());
            $retired = WorkInventory::registerMaterialExits($entries, $request->input('material_exits', []), $request->user());

            foreach ($entries as $entry) {
                ExitRecord::create([
                    'entry_id' => $entry->id,
                    'exited_at' => now(),
                    'exited_by' => $request->user()->username,
                    'observations' => $request->observations,
                ]);
            }

            return $entries->count();
        });

        // El propietario y el administrador quedan avisados de cada material retirado
        $retired->each(fn ($exit) => MaterialExitNotifier::retired($exit));

        $msg = $count === 1 ? '1 salida registrada.' : "{$count} salidas registradas.";

        return redirect()->route('vigilante.exits.index')
            ->with('success', $msg);
    }
}
