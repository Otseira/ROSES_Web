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

        // ===== HEADER IDENTITAS (4 kolom: label | value) =====
        $rows[] = ['REKAP ABSENSI KARYAWAN'];
        $rows[] = ['Nama', $this->meta['nama']];
        $rows[] = ['Unit Kerja', $this->meta['unit']];
        $rows[] = ['Periode', $this->periodLabel];
        $rows[] = []; // spacer

        // ===== HEADER TABEL HARIAN (9 kolom) =====
        $rows[] = [
            'Tanggal',
            'Jam Masuk',
            'Jam Keluar',
            'Durasi',
            'Status',
            'Terlambat',
            'Jarak',
            'Lembur',
            'On-Call',
        ];

        // ===== DATA HARIAN =====
        foreach ($this->rows as $row) {
            // Pastikan selalu 9 kolom
            $r = array_pad($row, 9, '-');
            $rows[] = $r;
        }

        $rows[] = []; // spacer

        // ===== RINGKASAN =====
        $s = $this->meta['summary'] ?? [];
        $totalLembur    = (int) ($s['total_lembur_menit'] ?? 0);
        $totalOnCall    = (int) ($s['total_oncall_menit'] ?? 0);
        $totalTerlambat = (int) ($s['total_terlambat_menit'] ?? 0);

        $rows[] = ['RINGKASAN TOTAL'];
        $rows[] = ['Total Lembur', $totalLembur . ' menit (' . round($totalLembur / 60, 2) . ' jam)'];
        $rows[] = ['Total On-Call', $totalOnCall . ' menit (' . round($totalOnCall / 60, 2) . ' jam)'];
        $rows[] = ['Total Keterlambatan', $totalTerlambat . ' menit'];
        $rows[] = []; // spacer

        // ===== BREAKDOWN KETERLAMBATAN (4 kolom: label | menit | % | potongan) =====
        $rows[] = ['BREAKDOWN KETERLAMBATAN'];
        $rows[] = ['Kategori', 'Total Menit', 'Persentase', 'Potongan (mnt)'];
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
                'Tindak Lanjut',
                $terlambat21plus,
            ];
        }

        $rows[] = []; // spacer
        $rows[] = ['TOTAL POTONGAN', (int) ($s['total_potongan'] ?? 0) . ' menit'];

        return $rows;
    }

    /**
     * ✅ TANPA mergeCells — layout dibangun dengan:
     *    - Lebar kolom tetap & konsisten
     *    - Warna penuh untuk banner section
     *    - Border di semua sel data
     *    - Alignment spesifik per kolom
     *    - Zebra striping untuk data harian
     */
    public function styles(Worksheet $sheet): array
    {
        $data = $this->array();

        // ===== 1. LEBAR KOLOM KONSISTEN =====
        // A = label panjang, B-I = data standar
        $sheet->getColumnDimension('A')->setWidth(32); // label section & tanggal
        $sheet->getColumnDimension('B')->setWidth(18); // nama/jam masuk
        $sheet->getColumnDimension('C')->setWidth(16); // unit/jam keluar
        $sheet->getColumnDimension('D')->setWidth(14); // periode/durasi
        $sheet->getColumnDimension('E')->setWidth(14); // status
        $sheet->getColumnDimension('F')->setWidth(14); // terlambat
        $sheet->getColumnDimension('G')->setWidth(12); // jarak
        $sheet->getColumnDimension('H')->setWidth(12); // lembur
        $sheet->getColumnDimension('I')->setWidth(12); // on-call

        // ===== 2. STYLE DEFAULT SELURUH SHEET =====
        $sheet->getStyle('A1:I200')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

        // ===== 3. BARIS 1: JUDUL UTAMA (hijau tua, tanpa merge — isi hanya di A1) =====
        $sheet->getStyle('A1:I1')->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1B5E20']],
        ]);
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(28);

        // ===== 4. BARIS 2-4: IDENTITAS (hijau muda) =====
        for ($r = 2; $r <= 4; $r++) {
            // Kolom A (label)
            $sheet->getStyle("A{$r}")->applyFromArray([
                'font'      => ['bold' => true],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E8F5E9']],
                'borders'   => $this->thinBorder(),
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
            ]);
            // Kolom B (value)
            $sheet->getStyle("B{$r}")->applyFromArray([
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E8F5E9']],
                'borders'   => $this->thinBorder(),
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
            ]);
        }
        $sheet->getRowDimension(2)->setRowHeight(18);
        $sheet->getRowDimension(3)->setRowHeight(18);
        $sheet->getRowDimension(4)->setRowHeight(18);

        // ===== 5. BARIS 6: HEADER TABEL HARIAN (hijau) =====
        for ($col = 'A'; $col <= 'I'; $col++) {
            $sheet->getStyle("{$col}6")->applyFromArray([
                'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4CAF50']],
                'borders'   => $this->thinBorder(),
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);
        }
        $sheet->getRowDimension(6)->setRowHeight(22);

        // ===== 6. DATA HARIAN (baris 7 s/d akhir data, zebra striping) =====
        $dataStart = 7;
        $dataEnd   = 6 + count($this->rows);

        if (count($this->rows) > 0) {
            for ($r = $dataStart; $r <= $dataEnd; $r++) {
                $isZebra = ($r - $dataStart) % 2 === 1;
                $bgColor = $isZebra ? 'F1F8E9' : 'FFFFFF';

                for ($col = 'A'; $col <= 'I'; $col++) {
                    // Alignment per kolom
                    $align = in_array($col, ['A', 'E'])
                        ? Alignment::HORIZONTAL_CENTER
                        : Alignment::HORIZONTAL_CENTER;

                    $sheet->getStyle("{$col}{$r}")->applyFromArray([
                        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bgColor]],
                        'borders'   => $this->thinBorder(),
                        'alignment' => ['horizontal' => $align],
                    ]);
                }
                $sheet->getRowDimension($r)->setRowHeight(17);
            }
        }

        // ===== 7. CARI POSISI BARIS TIAP SECTION =====
        $ringkasanRow = $breakdownRow = $totalPotonganRow = null;
        foreach ($data as $i => $row) {
            $v = $row[0] ?? null;
            if ($v === 'RINGKASAN TOTAL') $ringkasanRow = $i + 1;
            if ($v === 'BREAKDOWN KETERLAMBATAN') $breakdownRow = $i + 1;
            if ($v === 'TOTAL POTONGAN') $totalPotonganRow = $i + 1;
        }

        // ===== 8. RINGKASAN (banner oranye + 3 baris detail) =====
        if ($ringkasanRow) {
            // Banner: warna oranye penuh di semua kolom A-I
            for ($col = 'A'; $col <= 'I'; $col++) {
                $sheet->getStyle("{$col}{$ringkasanRow}")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EF6C00']],
                ]);
            }
            $sheet->getStyle("A{$ringkasanRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getRowDimension($ringkasanRow)->setRowHeight(22);

            // 3 baris detail (Total Lembur, On-Call, Keterlambatan)
            for ($i = 1; $i <= 3; $i++) {
                $r = $ringkasanRow + $i;
                $sheet->getStyle("A{$r}")->applyFromArray([
                    'font'      => ['bold' => true],
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF3E0']],
                    'borders'   => $this->thinBorder(),
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
                ]);
                $sheet->getStyle("B{$r}")->applyFromArray([
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF3E0']],
                    'borders'   => $this->thinBorder(),
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
                ]);
            }
        }

        // ===== 9. BREAKDOWN (banner merah + header kolom + 3-4 baris data) =====
        if ($breakdownRow) {
            // Banner merah (A-I)
            for ($col = 'A'; $col <= 'I'; $col++) {
                $sheet->getStyle("{$col}{$breakdownRow}")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'C62828']],
                ]);
            }
            $sheet->getStyle("A{$breakdownRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getRowDimension($breakdownRow)->setRowHeight(22);

            // Header kolom (A-D)
            $headerRow = $breakdownRow + 1;
            for ($col = 'A'; $col <= 'D'; $col++) {
                $sheet->getStyle("{$col}{$headerRow}")->applyFromArray([
                    'font'      => ['bold' => true],
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFCDD2']],
                    'borders'   => $this->thinBorder(),
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);
            }

            // Warna baris breakdown (kuning → oranye)
            $colors = ['FFF8E1', 'FFECB3', 'FFCCBC', 'FFAB91'];
            for ($i = 0; $i < 4; $i++) {
                $r     = $breakdownRow + 2 + $i;
                $label = $data[$r - 1][0] ?? null;

                if ($label && $label !== 'TOTAL POTONGAN') {
                    for ($col = 'A'; $col <= 'D'; $col++) {
                        $sheet->getStyle("{$col}{$r}")->applyFromArray([
                            'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $colors[$i]]],
                            'borders' => $this->thinBorder(),
                        ]);
                    }
                    // Label rata kiri, angka rata tengah
                    $sheet->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    for ($col = 'B'; $col <= 'D'; $col++) {
                        $sheet->getStyle("{$col}{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    }
                }
            }
        }

        // ===== 10. TOTAL POTONGAN (baris penutup merah tua) =====
        if ($totalPotonganRow) {
            $sheet->getStyle("A{$totalPotonganRow}")->applyFromArray([
                'font'      => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'B71C1C']],
                'borders'   => $this->mediumBorder(),
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
            ]);
            $sheet->getStyle("B{$totalPotonganRow}")->applyFromArray([
                'font'      => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'B71C1C']],
                'borders'   => $this->mediumBorder(),
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);
            $sheet->getRowDimension($totalPotonganRow)->setRowHeight(22);
        }

        // ===== 11. FREEZE PANE — header tabel tetap terlihat saat scroll =====
        $sheet->freezePane('A7');

        return [];
    }

    /** Helper: border tipis. */
    private function thinBorder(): array
    {
        return [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color'       => ['rgb' => 'BDBDBD'],
            ],
        ];
    }

    /** Helper: border sedang (untuk total potongan). */
    private function mediumBorder(): array
    {
        return [
            'allBorders' => [
                'borderStyle' => Border::BORDER_MEDIUM,
                'color'       => ['rgb' => '757575'],
            ],
        ];
    }
}
