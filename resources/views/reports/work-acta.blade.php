<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Acta de obra — {{ $work->title }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1a1a1a; }

        .header { background: #1e3a56; color: white; padding: 14px 18px; margin-bottom: 12px; }
        .header h1 { font-size: 16px; font-weight: bold; }
        .header p  { font-size: 10px; opacity: 0.85; margin-top: 2px; }

        h2 { font-size: 12px; color: #1e3a56; margin: 14px 0 6px; padding-bottom: 3px; border-bottom: 2px solid #1e3a56; }

        .data { width: 100%; margin-bottom: 4px; }
        .data td { padding: 3px 6px; vertical-align: top; }
        .data .lbl { color: #64748b; width: 18%; }

        table.list { width: 100%; border-collapse: collapse; }
        table.list thead tr { background: #e2e8f0; }
        table.list th { padding: 5px 6px; text-align: left; font-size: 9px; text-transform: uppercase; color: #334155; }
        table.list td { padding: 5px 6px; border-bottom: 1px solid #e2e8f0; }

        .in { color: #15803d; font-weight: bold; }
        .out { color: #1d4ed8; font-weight: bold; }
        .stay { color: #64748b; }
        .transfer { color: #b45309; font-size: 9px; }
        .empty { color: #94a3b8; font-style: italic; padding: 6px; }

        .signatures { width: 100%; margin-top: 40px; }
        .signatures td { width: 33%; text-align: center; padding: 0 10px; }
        .signatures .line { border-top: 1px solid #334155; padding-top: 4px; font-size: 9px; color: #475569; }

        .footer { text-align: center; font-size: 8px; color: #94a3b8; margin-top: 18px; }
    </style>
</head>
<body>

<div class="header">
    <h1>{{ config('app.name') }} — Acta de obra</h1>
    <p>
        @if($condominium = \App\Support\CurrentCondominium::get()){{ $condominium->name }} · @endif
        {{ $work->title }} ·
        {{ $date ? 'Movimientos del '.$date->format('d/m/Y') : 'Acta completa de la obra' }}
        · Generada el {{ now()->format('d/m/Y H:i') }} por {{ $generatedBy }}
    </p>
</div>

<h2>Datos de la obra</h2>
<table class="data">
    <tr>
        <td class="lbl">Inmueble</td><td>{{ $work->property->full_label }}</td>
        <td class="lbl">Tipo</td><td>{{ $work->type === 'construccion' ? 'Construcción' : 'Arreglo locativo' }}</td>
    </tr>
    <tr>
        <td class="lbl">Contratista</td><td>{{ $work->contractor_company ?: '—' }} · {{ $work->contractor_name }}</td>
        <td class="lbl">Cédula / NIT</td><td>{{ $work->contractor_document ?: '—' }}</td>
    </tr>
    <tr>
        <td class="lbl">Fechas</td><td>{{ $work->start_date->format('d/m/Y') }} → {{ $work->end_date?->format('d/m/Y') ?? 'sin fin' }}</td>
        <td class="lbl">Estado</td><td>{{ ucfirst($work->status) }}</td>
    </tr>
    <tr>
        <td class="lbl">Creada por</td><td>{{ $work->creator?->full_name ?? '—' }}</td>
        <td class="lbl">Última decisión</td><td>{{ $work->decider?->full_name ?? '—' }} {{ $work->decided_at?->format('d/m/Y H:i') }}</td>
    </tr>
</table>

<h2>Trabajadores autorizados</h2>
<table class="list">
    <thead><tr><th>Nombre</th><th>Cédula</th><th>Teléfono</th></tr></thead>
    <tbody>
    @forelse($work->workers as $w)
        <tr><td>{{ $w->full_name }}</td><td>{{ $w->cedula }}</td><td>{{ $w->phone ?: '—' }}</td></tr>
    @empty
        <tr><td colspan="3" class="empty">Sin trabajadores registrados.</td></tr>
    @endforelse
    </tbody>
</table>

<h2>Inventario dentro de la casa</h2>
<table class="list">
    <thead><tr><th>Ítem</th><th>Serial</th><th>Tipo</th><th>A nombre de</th><th>Cantidad</th></tr></thead>
    <tbody>
    @forelse($inside as $item)
        <tr>
            <td>{{ $item->name }}</td>
            <td>{{ $item->serial ?: '—' }}</td>
            <td>{{ ucfirst($item->kind) }}</td>
            <td>{{ $item->owner?->full_name ?? 'Proveedor (casa)' }}</td>
            <td>{{ $item->quantity_inside }}</td>
        </tr>
    @empty
        <tr><td colspan="5" class="empty">No hay herramientas ni materiales dentro.</td></tr>
    @endforelse
    </tbody>
</table>

<h2>Movimientos de herramientas y materiales</h2>
<table class="list">
    <thead><tr><th>Fecha</th><th>Movimiento</th><th>Ítem</th><th>Cant.</th><th>Persona</th><th>Guarda</th></tr></thead>
    <tbody>
    @forelse($movements as $m)
        <tr>
            <td>{{ $m->created_at->format('d/m/Y H:i') }}</td>
            <td class="{{ $m->direction === 'ingreso' ? 'in' : ($m->direction === 'salida' ? 'out' : 'stay') }}">
                {{ ['ingreso' => 'Ingresó', 'salida' => 'Salió', 'queda' => 'Quedó en la casa'][$m->direction] ?? $m->direction }}
            </td>
            <td>{{ $m->item->name }} {{ $m->item->serial ? '· '.$m->item->serial : '' }}</td>
            <td>{{ $m->quantity }}</td>
            <td>
                {{ $m->mover?->full_name ?? $m->entry?->full_name ?? '—' }}
                @if($m->entry?->supplier_company)<br><span class="transfer">{{ $m->entry->supplier_company }}</span>@endif
                @if($m->is_transfer)<br><span class="transfer">Traspaso: ingresó {{ $m->item->owner?->full_name }}</span>@endif
            </td>
            <td>{{ $m->registrar?->username ?? '—' }}</td>
        </tr>
    @empty
        <tr><td colspan="6" class="empty">Sin movimientos {{ $date ? 'este día' : '' }}.</td></tr>
    @endforelse
    </tbody>
</table>

<h2>Salidas de material</h2>
<table class="list">
    <thead><tr><th>Material</th><th>Cantidad</th><th>Motivo</th><th>Estado</th><th>Autorizó</th><th>Lo sacó</th></tr></thead>
    <tbody>
    @forelse($materialExits as $x)
        <tr>
            <td>{{ $x->description }}</td>
            <td>{{ $x->quantity }}</td>
            <td>{{ ucfirst($x->reason) }}</td>
            <td>{{ ucfirst($x->status) }}</td>
            <td>{{ $x->approver?->full_name ?? '—' }}</td>
            <td>
                @if($x->entry)
                    {{ $x->entry->full_name }} · CC {{ $x->entry->cedula }}
                    @if($x->exit_plate)<br>Placa {{ $x->exit_plate }}@endif
                    <br>{{ $x->executed_at?->format('d/m/Y H:i') }}
                @else
                    —
                @endif
            </td>
        </tr>
    @empty
        <tr><td colspan="6" class="empty">Sin salidas de material.</td></tr>
    @endforelse
    </tbody>
</table>

<table class="signatures">
    <tr>
        <td><div class="line">Contratista</div></td>
        <td><div class="line">Propietario / Residente</div></td>
        <td><div class="line">Vigilancia</div></td>
    </tr>
</table>

<p class="footer">{{ config('app.name') }} — Acta generada automáticamente</p>

</body>
</html>
