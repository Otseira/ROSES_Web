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
            'Durasi',
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
        $rows[] = ['Total Lembur', $totalLembur . ' menit (' . round($totalLembur / 60, 2) . ' jam)'];
        $rows[] = ['Total On-Call', $totalOnCall . ' menit (' . round($totalOnCall / 60, 2) . ' jam)'];
        $rows[] = ['Total Keterlambatan', $totalTerlambat . ' menit'];
        $rows[] = [];

        // ===== BREAKDOWN KETERLAMBATAN =====
        $rows[] = ['BREAKDOWN KETERLAMBATAN (PERATURAN POTONGAN)'];
        $rows[] = ['Kategori', 'Total Menit', 'Persentase', 'Potongan (menit)'];
        $rows[] = ['Terlambat 6 - 10 menit',  (int) ($s['terlambat_6_10'] ?? 0),  '25%',  (int) ($s['potongan_6_10'] ?? 0)];
        $rows[] = ['Terlambat 11 - 15 menit', (int) ($s['terlambat_11_15'] ?? 0), '50%',  (int) ($s['potongan_11_15'] ?? 0)];
        $rows[] = ['Terlambat 16 - 20 menit', (int) ($s['terlambat_16_20'] ?? 0), '100%', (int) ($s['potongan_16_20'] ?? 0)];

        $terlambat21plus = (int) ($s['terlambat_21plus'] ?? 0);
        if ($terlambat21plus > 0) {
            $rows[] = ['Terlambat > 20 menit', $terlambat21plus, '(Tindak Lanjut)', $terlambat21plus];
        }

        $rows[] = [];
        $rows[] = ['TOTAL POTONGAN KETERLAMBATAN', '', '', (int) ($s['total_potongan'] ?? 0) . ' menit'];

        return $rows;
    }

    /**
     * ✅ TANPA mergeCells sama sekali — aman untuk format .xls (BIFF8).
     *    Rapian dicapai lewat warna range, border, dan lebar kolom.
     */
    public function styles(Worksheet $sheet): array
    {
        $data = $this->array();

        // ===== Lebar kolom konsisten =====
        $widths = ['A' => 28, 'B' => 24, 'C' => 14, 'D' => 16, 'E' => 16, 'F' => 16, 'G' => 12, 'H' => 14, 'I' => 14];
        foreach ($widths as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }

        // ===== Baris 1: banner judul (warna penuh, tanpa merge) =====
        $sheet->getStyle('A1:I1')->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1B5E20']],
        ]);
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(26);

        // ===== Baris 2-4: identitas =====
        $sheet->getStyle('A2:B4')->applyFromArray([
            'font'      => ['bold' => true],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E8F5E9']],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);

        // ===== Baris 6: header tabel =====
        $sheet->getStyle('A6:I6')->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4CAF50']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);
        $sheet->getRowDimension(6)->setRowHeight(20);

        // ===== Data harian: border + baris selang-seling =====
        $n = count($this->rows);
        if ($n > 0) {
            $start = 7;
            $end   = 6 + $n;

            $sheet->getStyle("A{$start}:I{$end}")->applyFromArray([
                'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ]);
            $sheet->getStyle("A{$start}:A{$end}")->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);
            $sheet->getStyle("E{$start}:E{$end}")->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);

            // Zebra striping agar mata mudah mengikuti baris
            for ($r = $start; $r <= $end; $r++) {
                if (($r - $start) % 2 === 1) {
                    $sheet->getStyle("A{$r}:I{$r}")->applyFromArray([
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F1F8E9']],
                    ]);
                }
            }
        }

        // ===== Cari posisi tiap section =====
        $ringkasanRow = $breakdownRow = $totalPotonganRow = null;
        foreach ($data as $i => $row) {
            $v = $row[0] ?? null;
            if ($v === 'RINGKASAN TOTAL') $ringkasanRow = $i + 1;
            if ($v === 'BREAKDOWN KETERLAMBATAN (PERATURAN POTONGAN)') $breakdownRow = $i + 1;
            if ($v === 'TOTAL POTONGAN KETERLAMBATAN') $totalPotonganRow = $i + 1;
        }

        // ===== RINGKASAN (banner oranye + 3 baris) =====
        if ($ringkasanRow) {
            $sheet->getStyle("A{$ringkasanRow}:I{$ringkasanRow}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EF6C00']],
            ]);
            $sheet->getRowDimension($ringkasanRow)->setRowHeight(20);

            for ($i = 1; $i <= 3; $i++) {
                $r = $ringkasanRow + $i;
                $sheet->getStyle("A{$r}:B{$r}")->applyFromArray([
                    'font'      => ['bold' => true],
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF3E0']],
                    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);
            }
        }

        // ===== BREAKDOWN (banner merah + tabel 4 kolom) =====
        if ($breakdownRow) {
            $sheet->getStyle("A{$breakdownRow}:D{$breakdownRow}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'C62828']],
            ]);
            $sheet->getRowDimension($breakdownRow)->setRowHeight(20);

            $h = $breakdownRow + 1;
            $sheet->getStyle("A{$h}:D{$h}")->applyFromArray([
                'font'      => ['bold' => true],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFCDD2']],
                'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);

            $colors = ['FFF8E1', 'FFECB3', 'FFCCBC', 'FFAB91'];
            for ($i = 0; $i < 4; $i++) {
                $r     = $breakdownRow + 2 + $i;
                $label = $data[$r - 1][0] ?? null;
                if ($label && $label !== 'TOTAL POTONGAN KETERLAMBATAN') {
                    $sheet->getStyle("A{$r}:D{$r}")->applyFromArray([
                        'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $colors[$i]]],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                    ]);
                    $sheet->getStyle("A{$r}")->applyFromArray([
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
                    ]);
                    $sheet->getStyle("B{$r}:D{$r}")->applyFromArray([
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    ]);
                }
            }
        }

        // ===== TOTAL POTONGAN (baris penutup merah tua) =====
        if ($totalPotonganRow) {
            $sheet->getStyle("A{$totalPotonganRow}:D{$totalPotonganRow}")->applyFromArray([
                'font'    => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
                'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'B71C1C']],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_MEDIUM]],
            ]);
            $sheet->getStyle("A{$totalPotonganRow}")->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
            ]);
            $sheet->getStyle("D{$totalPotonganRow}")->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);
            $sheet->getRowDimension($totalPotonganRow)->setRowHeight(20);
        }

        // ===== Bekukan header saat scroll =====
        $sheet->freezePane('A7');

        return [];
    }
}
