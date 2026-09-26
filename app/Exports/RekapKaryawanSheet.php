<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RekapKaryawanSheet implements FromArray, WithStyles, WithTitle, ShouldAutoSize
{
    private string $title;
    private array $meta;
    private array $rows;
    private string $periodLabel;

    public function __construct(string $title, array $meta, array $rows, string $periodLabel)
    {
        $this->title       = $title;
        $this->meta        = $meta;
        $this->rows        = $rows;
        $this->periodLabel = $periodLabel;
    }

    /**
     * ✅ WAJIB: agar tab sheet bernama sesuai nama karyawan (bukan "Sheet1", "Sheet2", ...)
     */
    public function title(): string
    {
        return $this->title;
    }

    public function array(): array
    {
        $rows = [];

        // ===== HEADER IDENTITAS =====
        $rows[] = ['REKAP ABSENSI KARYAWAN'];
        $rows[] = ['Nama', $this->meta['nama']];
        $rows[] = ['Unit Kerja', $this->meta['unit']];
        $rows[] = ['Periode', $this->periodLabel];
        $rows[] = [];

        // ===== HEADER TABEL =====
        $rows[] = [
            'Tanggal',
            'Jam Masuk',
            'Jam Keluar',
            'Durasi (mnt)',
            'Status',
            'Terlambat (mnt)',
            'Jarak (m)',
            'Lembur (mnt)',
            'On-Call (mnt)',
        ];

        // ===== DATA HARIAN =====
        foreach ($this->rows as $row) {
            $rows[] = $row;
        }

        $rows[] = [];

        // ===== RINGKASAN TOTAL =====
        $s = $this->meta['summary'] ?? [];
        $totalLembur    = (int) ($s['total_lembur_menit'] ?? 0);
        $totalOnCall    = (int) ($s['total_oncall_menit'] ?? 0);
        $totalTerlambat = (int) ($s['total_terlambat_menit'] ?? 0);

        $rows[] = ['RINGKASAN TOTAL'];
        $rows[] = [
            'Total Lembur',
            $totalLembur . ' menit (' . round($totalLembur / 60, 2) . ' jam)',
        ];
        $rows[] = [
            'Total On-Call',
            $totalOnCall . ' menit (' . round($totalOnCall / 60, 2) . ' jam)',
        ];
        $rows[] = [
            'Total Keterlambatan',
            $totalTerlambat . ' menit',
        ];
        $rows[] = [];

        // ===== BREAKDOWN KETERLAMBATAN =====
        $rows[] = ['BREAKDOWN KETERLAMBATAN (PERATURAN POTONGAN)'];
        $rows[] = ['Kategori', 'Total Menit', 'Persentase', 'Potongan (menit)'];

        $rows[] = [
            'Terlambat 6 - 10 menit',
            (int) ($s['terlambat_6_10'] ?? 0),
            '25%',
            (int) ($s['potongan_6_10'] ?? 0),
        ];
        $rows[] = [
            'Terlambat 11 - 15 menit',
            (int) ($s['terlambat_11_15'] ?? 0),
            '50%',
            (int) ($s['potongan_11_15'] ?? 0),
        ];
        $rows[] = [
            'Terlambat 16 - 20 menit',
            (int) ($s['terlambat_16_20'] ?? 0),
            '100%',
            (int) ($s['potongan_16_20'] ?? 0),
        ];

        $terlambat21plus = (int) ($s['terlambat_21plus'] ?? 0);
        if ($terlambat21plus > 0) {
            $rows[] = [
                'Terlambat > 20 menit',
                $terlambat21plus,
                '(Tindak Lanjut)',
                $terlambat21plus,
            ];
        }

        $rows[] = [];
        $rows[] = [
            'TOTAL POTONGAN KETERLAMBATAN',
            '',
            '',
            (int) ($s['total_potongan'] ?? 0) . ' menit',
        ];

        return $rows;
    }

    /**
     * ✅ FIX: return type ": array" WAJIB sesuai interface WithStyles.
     */
    public function styles(Worksheet $sheet): array
    {
        $data = $this->array();

        // ===== JUDUL UTAMA (Baris 1) =====
        $sheet->mergeCells('A1:I1');
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1B5E20']],
        ]);

        // ===== IDENTITAS (Baris 2-4) =====
        $sheet->getStyle('A2:B4')->applyFromArray([
            'font'      => ['bold' => true],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E8F5E9']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);

        // ===== HEADER TABEL (Baris 6) =====
        $sheet->getStyle('A6:I6')->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4CAF50']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);

        // ===== DATA HARIAN (Baris 7 s/d akhir data) =====
        $dataStartRow = 7;
        $dataEndRow   = $dataStartRow + count($this->rows) - 1;
        if ($dataEndRow >= $dataStartRow) {
            $sheet->getStyle("A{$dataStartRow}:I{$dataEndRow}")->applyFromArray([
                'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ]);
        }

        // ===== Cari posisi baris tiap section =====
        $ringkasanRow     = null;
        $breakdownRow     = null;
        $totalPotonganRow = null;

        foreach ($data as $i => $row) {
            if (!empty($row[0])) {
                if ($row[0] === 'RINGKASAN TOTAL') $ringkasanRow = $i + 1;
                if ($row[0] === 'BREAKDOWN KETERLAMBATAN (PERATURAN POTONGAN)') $breakdownRow = $i + 1;
                if ($row[0] === 'TOTAL POTONGAN KETERLAMBATAN') $totalPotonganRow = $i + 1;
            }
        }

        // ===== RINGKASAN =====
        if ($ringkasanRow) {
            $sheet->mergeCells("A{$ringkasanRow}:I{$ringkasanRow}");
            $sheet->getStyle("A{$ringkasanRow}")->applyFromArray([
                'font'      => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FF6F00']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);

            for ($i = 1; $i <= 3; $i++) {
                $row = $ringkasanRow + $i;
                $sheet->mergeCells("B{$row}:I{$row}");
                $sheet->getStyle("A{$row}:I{$row}")->applyFromArray([
                    'font'    => ['bold' => true],
                    'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF3E0']],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                ]);
                $sheet->getStyle("A{$row}")->applyFromArray([
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
                ]);
                $sheet->getStyle("B{$row}")->applyFromArray([
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
                ]);
            }
        }

        // ===== BREAKDOWN =====
        if ($breakdownRow) {
            $sheet->mergeCells("A{$breakdownRow}:D{$breakdownRow}");
            $sheet->getStyle("A{$breakdownRow}")->applyFromArray([
                'font'      => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'C62828']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);

            $headerRow = $breakdownRow + 1;
            $sheet->getStyle("A{$headerRow}:D{$headerRow}")->applyFromArray([
                'font'      => ['bold' => true],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFCDD2']],
                'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);

            $colors = ['FFF8E1', 'FFECB3', 'FFCCBC', 'FFAB91'];
            for ($i = 0; $i < 4; $i++) {
                $row = $breakdownRow + 2 + $i;
                if (
                    isset($data[$row - 1]) && !empty($data[$row - 1][0])
                    && $data[$row - 1][0] !== 'TOTAL POTONGAN KETERLAMBATAN'
                ) {
                    $sheet->getStyle("A{$row}:D{$row}")->applyFromArray([
                        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $colors[$i]]],
                        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    ]);
                }
            }
        }

        // ===== TOTAL POTONGAN =====
        if ($totalPotonganRow) {
            $sheet->mergeCells("A{$totalPotonganRow}:C{$totalPotonganRow}");
            $sheet->getStyle("A{$totalPotonganRow}:D{$totalPotonganRow}")->applyFromArray([
                'font'      => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'B71C1C']],
                'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_MEDIUM]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ]);
        }

        // ===== Lebar kolom =====
        $sheet->getColumnDimension('A')->setWidth(18);
        $sheet->getColumnDimension('B')->setWidth(14);
        $sheet->getColumnDimension('C')->setWidth(14);
        $sheet->getColumnDimension('D')->setWidth(16);
        $sheet->getColumnDimension('E')->setWidth(14);
        $sheet->getColumnDimension('F')->setWidth(18);
        $sheet->getColumnDimension('G')->setWidth(12);
        $sheet->getColumnDimension('H')->setWidth(16);
        $sheet->getColumnDimension('I')->setWidth(16);

        return [];
    }
}
