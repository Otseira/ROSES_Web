<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RekapKaryawanSheet implements FromArray, WithTitle, WithStyles, WithColumnWidths, ShouldAutoSize
{
    private string $title;
    private array  $meta;
    private array  $rows;
    private string $periodLabel;

    public function __construct(string $title, array $meta, array $rows, string $periodLabel)
    {
        $this->title       = $title;
        $this->meta        = $meta;
        $this->rows        = $rows;
        $this->periodLabel = $periodLabel;
    }

    public function array(): array
    {
        return array_merge(
            [
                ['REKAP ABSENSI & LEMBUR KARYAWAN'],
                ['Periode', $this->periodLabel],
                ['Nama', $this->meta['nama']],
                ['Unit Kerja', $this->meta['unit']],
                [],
                [
                    'Tanggal',
                    'Jam Masuk',
                    'Jam Keluar',
                    'Durasi',
                    'Status',
                    'Terlambat (mnt)',
                    'Jarak (m)',
                    'Lembur (mnt)',
                    'On-Call (mnt)'
                ],
            ],
            $this->rows
        );
    }

    public function title(): string
    {
        return $this->title;
    }

    public function columnWidths(): array
    {
        return [
            'A' => 12,
            'B' => 11,
            'C' => 11,
            'D' => 10,
            'E' => 14,
            'F' => 16,
            'G' => 11,
            'H' => 14,
            'I' => 15
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 13]],
            6 => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType'   => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'DDEBF7'],
                ],
            ],
        ];
    }
}
