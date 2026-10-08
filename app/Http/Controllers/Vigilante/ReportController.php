<?php

namespace App\Http\Controllers\Vigilante;

use App\Exports\EntriesExport;
use App\Http\Controllers\Controller;
use App\Models\Entry;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function index(): \Inertia\Response
    {
        return Inertia::render('vigilante/Reports/Index');
    }

    public function export(Request $request): mixed
    {
        $data = $request->validate([
            'format' => 'required|in:pdf,excel',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'type' => 'nullable|in:propietario,residente,autorizado,visitante,proveedor',
            'cedula' => 'nullable|string',
            'only' => 'nullable|in:entries,exits',
        ]);

        $entries = $this->buildQuery($data)->get();

        $title = 'Reporte de Ingresos — SafeResidence';
        $filename = 'reporte_ingresos_'.now()->format('Ymd_His');

        if ($data['format'] === 'pdf') {
            $pdf = Pdf::loadView('reports.entries', compact('entries', 'title', 'data'))
                ->setPaper('a4', 'landscape');

            return $pdf->download("{$filename}.pdf");
        }

        return Excel::download(new EntriesExport($entries), "{$filename}.xlsx");
    }

    private function buildQuery(array $filters)
    {
        $query = Entry::with('exit')->orderByDesc('entry_at');

        if (! empty($filters['date_from'])) {
            $query->whereDate('entry_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('entry_at', '<=', $filters['date_to']);
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['cedula'])) {
            $query->where('cedula', 'like', "%{$filters['cedula']}%");
        }

        if (! empty($filters['only'])) {
            if ($filters['only'] === 'entries') {
                $query->whereDoesntHave('exit');
            } elseif ($filters['only'] === 'exits') {
                $query->whereHas('exit');
            }
        }

        return $query;
    }
}
