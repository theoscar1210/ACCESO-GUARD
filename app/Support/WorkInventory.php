<?php

namespace App\Support;

use App\Models\Entry;
use App\Models\ItemMovement;
use App\Models\MaterialExit;
use App\Models\User;
use App\Models\Work;
use App\Models\WorkItem;
use App\Models\WorkWorker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Inventario de herramientas y materiales por casa y por trabajador.
 *
 * - Cada ítem queda a nombre del trabajador que lo ingresó y de la casa de la obra.
 * - Una herramienta sale por defecto con su dueño; si sale con otro trabajador
 *   de la misma casa queda registrado como traspaso.
 * - Herramientas de otra casa no pueden salir por esta.
 * - El material se registra al entrar y se queda en la obra.
 */
class WorkInventory
{
    /** Trabajador de obra asociado a una cédula (prioriza obras vigentes) */
    public static function workerFor(string $cedula): ?WorkWorker
    {
        $workers = WorkWorker::with('work.property')
            ->where('cedula', $cedula)
            ->whereHas('work')
            ->latest()
            ->get();

        return $workers->first(fn ($w) => $w->work->isCurrent()) ?? $workers->first();
    }

    /** Datos de la obra para el formulario de ingreso */
    public static function workInfo(WorkWorker $worker): array
    {
        $work = $worker->work;
        $items = $worker->items()->orderBy('name')->get();

        return [
            'worker_id' => $worker->id,
            'worker_name' => $worker->full_name,
            'work_id' => $work->id,
            'title' => $work->title,
            'company' => $work->contractor_company ?: $work->contractor_name,
            'property_number' => $work->property->number,
            'property' => $work->property->full_label,
            'status' => $work->status,
            'is_current' => $work->isCurrent(),
            // Lo que ya tiene dentro a su nombre
            'tools_inside' => $items->where('kind', 'herramienta')->where('quantity_inside', '>', 0)
                ->values()->map(fn ($i) => self::payload($i)),
            // Para "repetir su último ingreso": herramientas que ya sacó
            'reentry_items' => $items->where('kind', 'herramienta')->where('quantity_inside', 0)
                ->values()->map(fn ($i) => self::payload($i)),
        ];
    }

    /**
     * Registra los ítems que entran con un trabajador.
     *
     * @param  array<int, array{item_id?: int|null, name?: string, serial?: string|null, kind?: string, quantity: int, photo?: UploadedFile|null}>  $items
     */
    public static function registerEntry(Entry $entry, WorkWorker $worker, array $items, User $guard): void
    {
        foreach ($items as $row) {
            $photo = ($row['photo'] ?? null) instanceof UploadedFile
                ? $row['photo']->store('work-items', 'public')
                : null;

            if (! empty($row['item_id'])) {
                // Reingreso de una herramienta que ya tiene registrada
                $item = $worker->items()->findOrFail($row['item_id']);
                if ($photo && ! $item->photo_path) {
                    $item->photo_path = $photo;
                }
            } else {
                $item = $worker->items()->make([
                    'work_id' => $worker->work_id,
                    'property_id' => $worker->work->property_id,
                    'name' => trim($row['name']),
                    'serial' => filled($row['serial'] ?? null) ? strtoupper(trim($row['serial'])) : null,
                    'kind' => $row['kind'],
                    'quantity_inside' => 0,
                    'photo_path' => $photo,
                ]);
            }

            $item->quantity_inside += (int) $row['quantity'];
            $item->save();

            $item->movements()->create([
                'entry_id' => $entry->id,
                'work_worker_id' => $worker->id,
                'direction' => 'ingreso',
                'quantity' => (int) $row['quantity'],
                'photo_path' => $photo,
                'registered_by' => $guard->id,
            ]);
        }
    }

    /**
     * Registra el material que entrega un proveedor (ferretería, depósito…).
     * Queda a nombre de la casa, sin trabajador dueño; si ya hay un material
     * con el mismo nombre en la obra se suma a su saldo.
     *
     * @param  array<int, array{name: string, serial?: string|null, quantity: int, photo?: UploadedFile|null}>  $items
     */
    public static function registerDelivery(Entry $entry, Work $work, array $items, User $guard): void
    {
        foreach ($items as $row) {
            $photo = ($row['photo'] ?? null) instanceof UploadedFile
                ? $row['photo']->store('work-items', 'public')
                : null;

            $name = trim($row['name']);

            $item = WorkItem::where('work_id', $work->id)
                ->whereNull('work_worker_id')
                ->where('kind', 'material')
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                ->first()
                ?? new WorkItem([
                    'work_id' => $work->id,
                    'property_id' => $work->property_id,
                    'work_worker_id' => null,
                    'name' => $name,
                    'kind' => 'material',
                    'quantity_inside' => 0,
                ]);

            if ($photo && ! $item->photo_path) {
                $item->photo_path = $photo;
            }

            $item->quantity_inside += (int) $row['quantity'];
            $item->save();

            $item->movements()->create([
                'entry_id' => $entry->id,
                'work_worker_id' => null,
                'direction' => 'ingreso',
                'quantity' => (int) $row['quantity'],
                'photo_path' => $photo,
                'registered_by' => $guard->id,
                'notes' => 'Entrega de '.$entry->supplier_company,
            ]);
        }
    }

    /** Salidas de material aprobadas y pendientes de ejecutar en la casa de una obra */
    public static function approvedMaterialExits(int $propertyId): Collection
    {
        return MaterialExit::with('requester', 'approver')
            ->where('status', 'aprobada')
            ->whereHas('work', fn ($q) => $q->where('property_id', $propertyId))
            ->oldest()
            ->get();
    }

    /**
     * Lo que puede sacar quien sale: sus herramientas, las de compañeros de la
     * misma casa (traspaso) y las salidas de material ya aprobadas.
     * Los proveedores no sacan herramientas, solo material autorizado.
     */
    public static function toolsForExit(Entry $entry): array
    {
        $entry->loadMissing('workWorker.work', 'work');
        $work = $entry->relatedWork();

        if (! $work) {
            return ['own' => [], 'others' => [], 'material_exits' => []];
        }

        $materialExits = self::approvedMaterialExits($work->property_id)->map->payload()->all();
        $worker = $entry->workWorker;

        if (! $worker) {
            return ['own' => [], 'others' => [], 'material_exits' => $materialExits];
        }

        $tools = WorkItem::with('owner')
            ->where('property_id', $work->property_id)
            ->tools()
            ->inside()
            ->orderBy('name')
            ->get();

        [$own, $others] = $tools->partition(fn ($i) => $i->owner?->cedula === $worker->cedula);

        return [
            'own' => $own->values()->map(fn ($i) => self::payload($i))->all(),
            'others' => $others->values()->map(fn ($i) => self::payload($i))->all(),
            'material_exits' => $materialExits,
        ];
    }

    /**
     * Marca como ejecutadas las salidas de material que se llevan quienes salen.
     *
     * @param  Collection<int, Entry>  $entries
     * @param  array<int, array{entry_id: int, id: int}>  $exits
     */
    public static function registerMaterialExits(Collection $entries, array $exits, User $guard): void
    {
        foreach ($exits as $row) {
            $entry = $entries->firstWhere('id', (int) $row['entry_id']);
            $exit = MaterialExit::with('work')->lockForUpdate()->find($row['id']);
            $work = $entry?->relatedWork();

            if (! $entry || ! $exit || ! $work || $exit->status !== 'aprobada' || $exit->work->property_id !== $work->property_id) {
                throw ValidationException::withMessages([
                    'material_exits' => 'Una de las salidas de material no está aprobada o no corresponde a la casa de quien sale.',
                ]);
            }

            $exit->forceFill([
                'status' => 'ejecutada',
                'entry_id' => $entry->id,
                'executed_by' => $guard->id,
                'executed_at' => now(),
            ])->save();
        }
    }

    /** Cantidad de herramientas a nombre del trabajador que siguen dentro de su casa */
    public static function ownToolsInside(WorkWorker $worker): int
    {
        return (int) WorkItem::where('property_id', $worker->work->property_id)
            ->tools()
            ->inside()
            ->whereHas('owner', fn ($q) => $q->where('cedula', $worker->cedula))
            ->sum('quantity_inside');
    }

    /**
     * Registra lo que pasa con las herramientas al salir.
     *
     * @param  Collection<int, Entry>  $entries  ingresos que salen
     * @param  array<int, array{entry_id: int, item_id: int, action: string, quantity?: int|null}>  $moves
     */
    public static function registerExit(Collection $entries, array $moves, User $guard): void
    {
        $byEntry = collect($moves)->groupBy('entry_id');

        foreach ($entries as $entry) {
            $worker = $entry->workWorker;
            if (! $worker) {
                continue;
            }

            $worker->loadMissing('work');
            $propertyId = $worker->work->property_id;
            $entryMoves = $byEntry->get($entry->id, collect());

            // Cada herramienta propia que sigue dentro necesita una decisión: sale o queda
            $ownIds = WorkItem::where('property_id', $propertyId)->tools()->inside()
                ->whereHas('owner', fn ($q) => $q->where('cedula', $worker->cedula))
                ->pluck('id');

            $decided = $entryMoves->pluck('item_id')->map(fn ($id) => (int) $id);
            if ($ownIds->diff($decided)->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'tool_moves' => "Indica si las herramientas de {$worker->full_name} salen o quedan en la casa.",
                ]);
            }

            foreach ($entryMoves as $move) {
                $item = WorkItem::with('owner')->lockForUpdate()->find($move['item_id']);

                if (! $item || $item->kind !== 'herramienta' || $item->property_id !== $propertyId) {
                    throw ValidationException::withMessages([
                        'tool_moves' => 'Una de las herramientas no pertenece a la casa donde trabaja esta persona.',
                    ]);
                }

                $isTransfer = $item->owner?->cedula !== $worker->cedula;

                if ($move['action'] === 'queda') {
                    if (! $isTransfer) {
                        self::movement($item, $entry, $worker, 'queda', $item->quantity_inside, false, $guard);
                    }

                    continue;
                }

                $quantity = (int) ($move['quantity'] ?? $item->quantity_inside);
                if ($quantity < 1 || $quantity > $item->quantity_inside) {
                    throw ValidationException::withMessages([
                        'tool_moves' => "La cantidad de «{$item->name}» que sale no es válida (dentro: {$item->quantity_inside}).",
                    ]);
                }

                $item->decrement('quantity_inside', $quantity);
                self::movement($item, $entry, $worker, 'salida', $quantity, $isTransfer, $guard);
            }
        }
    }

    private static function movement(WorkItem $item, Entry $entry, WorkWorker $mover, string $direction, int $quantity, bool $isTransfer, User $guard): ItemMovement
    {
        return $item->movements()->create([
            'entry_id' => $entry->id,
            'work_worker_id' => $mover->id,
            'direction' => $direction,
            'quantity' => $quantity,
            'is_transfer' => $isTransfer,
            'registered_by' => $guard->id,
            'notes' => $isTransfer ? "Traspaso: ingresó {$item->owner?->full_name}, salió con {$mover->full_name}" : null,
        ]);
    }

    public static function payload(WorkItem $item): array
    {
        return [
            'id' => $item->id,
            'name' => $item->name,
            'serial' => $item->serial,
            'kind' => $item->kind,
            'quantity' => $item->quantity_inside,
            'owner' => $item->owner?->full_name,
            'owner_cedula' => $item->owner?->cedula,
            'photo_url' => $item->photo_url,
        ];
    }
}
