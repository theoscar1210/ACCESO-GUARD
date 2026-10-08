<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1a1a1a; }

        .header { background: #1e3a56; color: white; padding: 16px 20px; margin-bottom: 16px; }
        .header h1 { font-size: 18px; font-weight: bold; }
        .header p  { font-size: 11px; opacity: 0.85; margin-top: 2px; }

        .filters { background: #f1f5f9; padding: 8px 16px; margin-bottom: 12px; border-radius: 4px; font-size: 10px; color: #475569; }
        .filters span { margin-right: 16px; }

        .stats { display: flex; gap: 12px; margin: 0 20px 14px; }
        .stat { background: #e2e8f0; padding: 8px 14px; border-radius: 4px; text-align: center; flex: 1; }
        .stat .num { font-size: 20px; font-weight: bold; color: #1e3a56; }
        .stat .lbl { font-size: 9px; color: #64748b; text-transform: uppercase; }

        table { width: 100%; border-collapse: collapse; margin: 0 0 20px; }
        thead tr { background: #1e3a56; color: white; }
        thead th { padding: 8px 10px; text-align: left; font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; }
        tbody tr:nth-child(even) { background: #f8fafc; }
        tbody tr:hover { background: #e2e8f0; }
        tbody td { padding: 7px 10px; border-bottom: 1px solid #e2e8f0; }

        .badge { display: inline-block; padding: 2px 7px; border-radius: 10px; font-size: 9px; font-weight: 600; text-transform: uppercase; }
        .badge-propietario { background: #dbeafe; color: #1d4ed8; }
        .badge-autorizado  { background: #dcfce7; color: #15803d; }
        .badge-visitante   { background: #fef9c3; color: #a16207; }

        .inside { color: #16a34a; font-weight: 600; }
        .outside { color: #94a3b8; }

        .footer { text-align: center; font-size: 9px; color: #94a3b8; margin-top: 8px; }
    </style>
</head>
<body>

<div class="header">
    <h1>{{ config('app.name') }} — Reporte de Ingresos</h1>
    <p>
        @if($condominium = \App\Support\CurrentCondominium::get()){{ $condominium->name }} · @endif
        Generado el {{ now()->format('d/m/Y H:i') }}
    </p>
</div>

{{-- Filtros aplicados --}}
<div class="filters">
    <span><strong>Registros:</strong> {{ $entries->count() }}</span>
    @if(!empty($data['date_from'])) <span><strong>Desde:</strong> {{ \Carbon\Carbon::parse($data['date_from'])->format('d/m/Y') }}</span> @endif
    @if(!empty($data['date_to']))   <span><strong>Hasta:</strong> {{ \Carbon\Carbon::parse($data['date_to'])->format('d/m/Y') }}</span> @endif
    @if(!empty($data['type']))      <span><strong>Tipo:</strong> {{ ucfirst($data['type']) }}</span> @endif
    @if(!empty($data['cedula']))    <span><strong>Cédula:</strong> {{ $data['cedula'] }}</span> @endif
</div>

{{-- Stats --}}
<table style="width:auto; margin: 0 0 14px;">
    <tr>
        <td style="padding:0 8px 0 0;">
            <div class="stat"><div class="num">{{ $entries->count() }}</div><div class="lbl">Total</div></div>
        </td>
        <td style="padding:0 8px 0 0;">
            <div class="stat"><div class="num">{{ $entries->whereNull('exit') ? $entries->filter(fn($e) => !$e->exit)->count() : 0 }}</div><div class="lbl">Dentro</div></div>
        </td>
        <td style="padding:0 8px 0 0;">
            <div class="stat"><div class="num">{{ $entries->where('type','propietario')->count() }}</div><div class="lbl">Propietarios</div></div>
        </td>
        <td style="padding:0 8px 0 0;">
            <div class="stat"><div class="num">{{ $entries->where('type','autorizado')->count() }}</div><div class="lbl">Autorizados</div></div>
        </td>
        <td>
            <div class="stat"><div class="num">{{ $entries->where('type','visitante')->count() }}</div><div class="lbl">Visitantes</div></div>
        </td>
    </tr>
</table>

{{-- Tabla principal --}}
<table>
    <thead>
        <tr>
            <th>#</th>
            <th>Nombre</th>
            <th>Cédula</th>
            <th>Destino</th>
            <th>Tipo</th>
            <th>Vehículo</th>
            <th>Ingreso</th>
            <th>Salida</th>
            <th>Registrado por</th>
        </tr>
    </thead>
    <tbody>
        @forelse($entries as $i => $entry)
        <tr>
            <td>{{ $i + 1 }}</td>
            <td><strong>{{ $entry->first_name }} {{ $entry->last_name }}</strong></td>
            <td>{{ $entry->cedula }}</td>
            <td>{{ $entry->destination }}</td>
            <td>
                <span class="badge badge-{{ $entry->type }}">{{ ucfirst($entry->type) }}</span>
            </td>
            <td>{{ ucfirst($entry->vehicle) }}</td>
            <td>{{ $entry->entry_at->format('d/m/Y H:i') }}</td>
            <td>
                @if($entry->exit)
                    <span class="outside">{{ $entry->exit->exited_at->format('d/m/Y H:i') }}</span>
                @else
                    <span class="inside">Dentro</span>
                @endif
            </td>
            <td>{{ $entry->registered_by ?? '—' }}</td>
        </tr>
        @empty
        <tr>
            <td colspan="9" style="text-align:center; padding:20px; color:#94a3b8;">
                No hay registros con los filtros seleccionados.
            </td>
        </tr>
        @endforelse
    </tbody>
</table>

<div class="footer">
    {{ config('app.name') }} · Reporte generado automáticamente · {{ now()->format('d/m/Y H:i:s') }}
</div>

</body>
</html>
