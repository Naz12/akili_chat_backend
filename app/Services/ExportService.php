<?php

namespace App\Services;

use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;

class ExportService
{
    /**
     * Export data to Excel
     */
    public static function exportToExcel(Collection $data, array $headers, string $filename): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $export = new class($data, $headers) implements \Maatwebsite\Excel\Concerns\FromCollection, \Maatwebsite\Excel\Concerns\WithHeadings, \Maatwebsite\Excel\Concerns\WithMapping {
            protected $data;
            protected $headers;

            public function __construct($data, $headers)
            {
                $this->data = $data;
                $this->headers = $headers;
            }

            public function collection()
            {
                return $this->data;
            }

            public function headings(): array
            {
                return $this->headers;
            }

            public function map($row): array
            {
                // If row is already an array, ensure it matches header order
                if (is_array($row)) {
                    $result = [];
                    foreach ($this->headers as $header) {
                        $result[] = $row[$header] ?? '';
                    }
                    return $result;
                }
                
                // If row is an object, try to get properties
                if (is_object($row)) {
                    $result = [];
                    foreach ($this->headers as $header) {
                        $result[] = $row->{$header} ?? (isset($row[$header]) ? $row[$header] : '');
                    }
                    return $result;
                }
                
                return array_fill(0, count($this->headers), '');
            }
        };

        return Excel::download($export, $filename . '.xlsx');
    }

    /**
     * Export data to PDF
     */
    public static function exportToPdf(Collection $data, array $headers, string $title, string $filename)
    {
        $html = view('admin.exports.table-pdf', [
            'title' => $title,
            'headers' => $headers,
            'data' => $data,
        ])->render();

        return Pdf::loadHTML($html)
            ->setPaper('a4', 'landscape')
            ->download($filename . '.pdf');
    }

    /**
     * Prepare data for export (convert models to arrays)
     */
    public static function prepareData(Collection $data, callable $mapper = null): Collection
    {
        if ($mapper) {
            return $data->map($mapper);
        }

        return $data->map(function ($item) {
            if (is_object($item) && method_exists($item, 'toArray')) {
                return $item->toArray();
            }
            return (array) $item;
        });
    }
}

