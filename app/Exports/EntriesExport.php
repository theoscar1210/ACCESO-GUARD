<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class EntriesExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles, WithTitle
{
    public function __construct(private Collection $entries) {}

    public function title(): string
    {
        return 'Ingresos';
    }

    public function collection(): Collection
    {
        return $this->entries;
    }

    public function headings(): array
    {
        return [
            '#',
            'Nombres',
            'Apellidos',
            'Cédula',
            'Destino',
            'Tipo',
            'Vehículo',
            'Fecha Ingreso',
            'Hora Ingreso',
            'Fecha Salida',
            'Hora Salida',
            'Estado',
            'Registrado por',
            'Observaciones',
        ];
    }

    public function map($entry): array
    {
        static $i = 0;
        $i++;

        return [
            $i,
            $entry->first_name,
            $entry->last_name,
            $entry->cedula,
            $entry->destination,
            ucfirst($entry->type),
            ucfirst($entry->vehicle),
            $entry->entry_at->format('d/m/Y'),
            $entry->entry_at->format('H:i'),
            $entry->exit ? $entry->exit->exited_at->format('d/m/Y') : '',
            $entry->exit ? $entry->exit->exited_at->format('H:i') : '',
            $entry->exit ? 'Salida registrada' : 'Dentro del edificio',
            $entry->registered_by ?? '',
            $entry->observations ?? '',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['argb' => 'FF1E3A56']],
                'alignment' => ['horizontal' => 'center'],
            ],
        ];
    }
}
