<?php

namespace App\Http\Controllers;

use App\Models\ItemMovement;
use App\Models\Property;
use App\Models\User;
use App\Models\Work;
use App\Models\WorkItem;
use App\Models\WorkWorker;
use App\Support\WorkPermissions;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Obras: las puede crear cualquier rol habilitado en la configuración;
 * administrador y superusuario aprueban, editan y revierten decisiones.
 */
class WorkController extends Controller
{
    /** Acción de decisión → estado resultante */
    private const DECISIONS = [
        'aprobar' => 'aprobada',
        'rechazar' => 'rechazada',
        'suspender' => 'suspendida',
        'cerrar' => 'cerrada',
        'reabrir' => 'aprobada',
    ];

    public function index(Request $request): Response
    {
        $user = $request->user();

        $works = $this->visibleWorks($user)
            ->with('property')
            ->withCount('workers')
            ->withSum(['items as tools_inside' => fn ($q) => $q->tools()], 'quantity_inside')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->orderByRaw("CASE status WHEN 'pendiente' THEN 0 WHEN 'aprobada' THEN 1 ELSE 2 END")
            ->latest()
            ->get()
            ->map(fn (Work $w) => [
                'id' => $w->id,
                'title' => $w->title,
                'property' => $w->property->full_label,
                'contractor' => $w->contractor_company ?: $w->contractor_name,
                'type' => $w->type,
                'status' => $w->status,
                'is_current' => $w->isCurrent(),
                'start_date' => $w->start_date->format('d/m/Y'),
                'end_date' => $w->end_date?->format('d/m/Y'),
                'workers_count' => $w->workers_count,
                'tools_inside' => (int) $w->tools_inside,
                // Herramientas dentro de una obra cerrada, suspendida o vencida: hay que cuadrarlas
                'needs_attention' => (int) $w->tools_inside > 0 && ! $w->isCurrent(),
            ]);

        return Inertia::render('works/Index', [
            'works' => $works,
            'filters' => $request->only('status'),
            'can_create' => WorkPermissions::canCreate($user),
        ]);
    }

    public function create(Request $request): Response
    {
        $user = $request->user();
        abort_unless(WorkPermissions::canCreate($user), 403, 'Tu rol no tiene habilitada la creación de obras.');

        return Inertia::render('works/Create', [
            'properties' => $this->selectableProperties($user),
            'requires_approval' => WorkPermissions::initialStatus($user) === 'pendiente',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless(WorkPermissions::canCreate($user), 403, 'Tu rol no tiene habilitada la creación de obras.');

        $data = $request->validate([
            ...$this->workRules($user),
            'workers' => 'required|array|min:1',
            'workers.*.first_name' => 'required|string|max:100',
            'workers.*.last_name' => 'required|string|max:100',
            'workers.*.cedula' => 'required|string|max:20|distinct',
            'workers.*.phone' => 'nullable|string|max:20',
        ], [
            'workers.required' => 'Agrega al menos un trabajador.',
            'workers.*.cedula.distinct' => 'Hay cédulas repetidas en la lista de trabajadores.',
        ]);

        $status = WorkPermissions::initialStatus($user);

        $work = DB::transaction(function () use ($data, $user, $status) {
            $work = Work::create([
                ...collect($data)->except('workers')->all(),
                'status' => $status,
                'created_by' => $user->id,
            ]);

            if ($status === 'aprobada') {
                $work->forceFill(['decided_by' => $user->id, 'decided_at' => now()])->save();
            }

            $work->workers()->createMany($data['workers']);
            $work->log('creada', $user, $status === 'aprobada' ? 'Aprobada al crearse' : 'Pendiente de aprobación');

            return $work;
        });

        return redirect()->route('works.show', $work)
            ->with('success', $status === 'aprobada'
                ? 'Obra registrada y aprobada.'
                : 'Obra registrada. Queda pendiente de aprobación del administrador.');
    }

    public function show(Request $request, Work $work): Response
    {
        $user = $request->user();
        abort_unless(WorkPermissions::canView($user, $work), 403);

        $work->load(['property', 'creator', 'decider', 'workers', 'logs.user']);

        $items = WorkItem::with('owner')->where('work_id', $work->id)->orderBy('name')->get();

        $movements = ItemMovement::with(['item.owner', 'mover', 'registrar'])
            ->whereIn('work_item_id', $items->pluck('id'))
            ->latest()
            ->limit(60)
            ->get();

        return Inertia::render('works/Show', [
            'work' => [
                'id' => $work->id,
                'title' => $work->title,
                'type' => $work->type,
                'status' => $work->status,
                'is_current' => $work->isCurrent(),
                'property_id' => $work->property_id,
                'property' => $work->property->full_label,
                'contractor_company' => $work->contractor_company,
                'contractor_name' => $work->contractor_name,
                'contractor_document' => $work->contractor_document,
                'contractor_phone' => $work->contractor_phone,
                'start_date' => $work->start_date->format('Y-m-d'),
                'end_date' => $work->end_date?->format('Y-m-d'),
                'schedule' => $work->schedule,
                'description' => $work->description,
                'created_by' => $work->creator?->full_name,
                'decided_by' => $work->decider?->full_name,
                'decided_at' => $work->decided_at?->format('d/m/Y H:i'),
            ],
            'workers' => $work->workers->map(fn (WorkWorker $w) => [
                'id' => $w->id,
                'full_name' => $w->full_name,
                'first_name' => $w->first_name,
                'last_name' => $w->last_name,
                'cedula' => $w->cedula,
                'phone' => $w->phone,
                // Inventario por trabajador: herramientas que siguen dentro a su nombre
                'tools' => $items->where('work_worker_id', $w->id)->where('kind', 'herramienta')
                    ->where('quantity_inside', '>', 0)->values()->map(fn ($i) => $this->itemPayload($i)),
            ]),
            'materials' => $items->where('kind', 'material')->where('quantity_inside', '>', 0)
                ->values()->map(fn ($i) => $this->itemPayload($i)),
            'movements' => $movements->map(fn (ItemMovement $m) => [
                'id' => $m->id,
                'item' => $m->item->name,
                'serial' => $m->item->serial,
                'direction' => $m->direction,
                'quantity' => $m->quantity,
                'is_transfer' => $m->is_transfer,
                'owner' => $m->item->owner?->full_name,
                'mover' => $m->mover?->full_name,
                'registered_by' => $m->registrar?->username,
                'notes' => $m->notes,
                'photo_url' => $m->photo_path ? asset('storage/'.$m->photo_path) : null,
                'at' => $m->created_at->format('d/m/Y H:i'),
            ]),
            'material_exits' => $work->materialExits()->with('requester', 'approver', 'executor', 'entry')->get()
                ->map->payload(),
            'can_approve_material_exit' => WorkPermissions::canApproveMaterialExit($user, $work),
            'logs' => $work->logs->map(fn ($l) => [
                'id' => $l->id,
                'action' => $l->action,
                'notes' => $l->notes,
                'user' => $l->user?->full_name ?? 'Sistema',
                'at' => $l->created_at->format('d/m/Y H:i'),
            ]),
            'can_decide' => WorkPermissions::canDecide($user),
            'can_manage_workers' => $this->canManageWorkers($user, $work),
            'properties' => WorkPermissions::canDecide($user) ? $this->selectableProperties($user) : [],
        ]);
    }

    /** Editar datos de la obra: solo administrador y superusuario */
    public function update(Request $request, Work $work): RedirectResponse
    {
        $user = $request->user();
        abort_unless(WorkPermissions::canDecide($user), 403);

        $work->update($request->validate($this->workRules($user)));
        $work->log('editada', $user);

        return back()->with('success', 'Obra actualizada.');
    }

    /** Aprobar, rechazar, suspender, cerrar o reabrir: administrador y superusuario */
    public function decide(Request $request, Work $work): RedirectResponse
    {
        $user = $request->user();
        abort_unless(WorkPermissions::canDecide($user), 403);

        $data = $request->validate([
            'action' => ['required', Rule::in(array_keys(self::DECISIONS))],
            'notes' => 'nullable|string|max:255',
        ]);

        $status = self::DECISIONS[$data['action']];

        if ($work->status === $status) {
            throw ValidationException::withMessages(['action' => "La obra ya está {$status}."]);
        }

        $work->forceFill([
            'status' => $status,
            'decided_by' => $user->id,
            'decided_at' => now(),
        ])->save();

        $work->log($data['action'] === 'reabrir' ? 'reabierta' : $status, $user, $data['notes'] ?? null);

        return back()->with('success', "Obra {$status}.");
    }

    public function addWorker(Request $request, Work $work): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->canManageWorkers($user, $work), 403);

        $data = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'cedula' => ['required', 'string', 'max:20', Rule::unique('work_workers')->where('work_id', $work->id)],
            'phone' => 'nullable|string|max:20',
        ], ['cedula.unique' => 'Este trabajador ya está en la obra.']);

        $worker = $work->workers()->create($data);
        $work->log('trabajador_agregado', $user, $worker->full_name);

        return back()->with('success', 'Trabajador agregado.');
    }

    public function removeWorker(Request $request, Work $work, WorkWorker $worker): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->canManageWorkers($user, $work) && $worker->work_id === $work->id, 403);

        if ($worker->items()->tools()->inside()->exists()) {
            throw ValidationException::withMessages([
                'worker' => "{$worker->full_name} todavía tiene herramientas dentro. Registra su salida antes de quitarlo.",
            ]);
        }

        $worker->delete();
        $work->log('trabajador_quitado', $user, $worker->full_name);

        return back()->with('success', 'Trabajador quitado de la obra.');
    }

    // ── Auxiliares ──────────────────────────────────────────────────────────

    private function visibleWorks(User $user)
    {
        $query = Work::query();

        if (WorkPermissions::isRestrictedToOwnProperty($user)) {
            $query->whereHas('property', fn ($q) => $q->where('number', $user->property_number ?? '__ninguno__'));
        }

        return $query;
    }

    private function selectableProperties(User $user): array
    {
        return Property::query()
            ->when(
                WorkPermissions::isRestrictedToOwnProperty($user),
                fn ($q) => $q->where('number', $user->property_number ?? '__ninguno__'),
            )
            ->orderBy('block')->orderBy('number')
            ->get()
            ->map(fn ($p) => ['id' => $p->id, 'label' => $p->full_label])
            ->all();
    }

    private function workRules(User $user): array
    {
        return [
            'property_id' => ['required', Rule::in(array_column($this->selectableProperties($user), 'id'))],
            'title' => 'required|string|max:150',
            'type' => ['required', Rule::in(Work::TYPES)],
            'contractor_company' => 'nullable|string|max:150',
            'contractor_name' => 'required|string|max:150',
            'contractor_document' => 'nullable|string|max:30',
            'contractor_phone' => 'nullable|string|max:20',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'schedule' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:2000',
        ];
    }

    /** Agregar o quitar trabajadores: quien decide, quien creó la obra y el vigilante (portería) */
    private function canManageWorkers(User $user, Work $work): bool
    {
        return WorkPermissions::canDecide($user)
            || $work->created_by === $user->id
            || $user->hasRole('Vigilante');
    }

    private function itemPayload(WorkItem $item): array
    {
        return [
            'id' => $item->id,
            'name' => $item->name,
            'serial' => $item->serial,
            'kind' => $item->kind,
            'quantity' => $item->quantity_inside,
            'owner' => $item->owner?->full_name ?? 'Proveedor',
            'photo_url' => $item->photo_url,
        ];
    }

    /** Acta en PDF de la obra: completa o solo de un día (?date=AAAA-MM-DD) */
    public function acta(Request $request, Work $work)
    {
        abort_unless(WorkPermissions::canView($request->user(), $work), 403);

        $request->validate(['date' => 'nullable|date']);
        $date = $request->filled('date') ? Carbon::parse($request->date) : null;

        $work->load(['property', 'creator', 'decider', 'workers']);
        $items = WorkItem::with('owner')->where('work_id', $work->id)->orderBy('name')->get();

        $movements = ItemMovement::with(['item.owner', 'mover', 'registrar', 'entry'])
            ->whereIn('work_item_id', $items->pluck('id'))
            ->when($date, fn ($q) => $q->whereDate('created_at', $date))
            ->oldest()
            ->get();

        $materialExits = $work->materialExits()->with('requester', 'approver', 'executor', 'entry')
            ->when($date, fn ($q) => $q->where(fn ($q) => $q->whereDate('created_at', $date)->orWhereDate('executed_at', $date)))
            ->get();

        $pdf = Pdf::loadView('reports.work-acta', [
            'work' => $work,
            'date' => $date,
            'inside' => $items->where('quantity_inside', '>', 0)->values(),
            'movements' => $movements,
            'materialExits' => $materialExits,
            'generatedBy' => $request->user()->full_name,
        ]);

        $suffix = $date ? $date->format('Ymd') : 'completa';

        return $pdf->download("acta_obra_{$work->id}_{$suffix}.pdf");
    }
}
