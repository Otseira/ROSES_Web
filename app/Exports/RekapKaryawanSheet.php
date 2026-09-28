<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RekapKaryawanSheet implements FromArray, WithStyles, WithTitle, ShouldAutoSize, WithColumnWidths
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

    /**
     * ✅ Lebar kolom MINIMAL (fallback bila auto-size tidak cukup).
     *    Auto-size akan menambah lebar jika konten lebih panjang.
     */
    public function columnWidths(): array
    {
        return [
            'A' => 20,  // Tanggal / Label
            'B' => 14,  // Jam Masuk / Nilai
            'C' => 14,  // Jam Keluar
            'D' => 14,  // Durasi
            'E' => 16,  // Status
            'F' => 16,  // Terlambat
            'G' => 12,  // Jarak
            'H' => 14,  // Lembur
            'I' => 14,  // On-Call
        ];
    }

    public function array(): array
    {
        $pad = fn(array $r): array => array_slice(array_pad($r, 9, ''), 0, 9);

        $rows = [];

        // ===== HEADER IDENTITAS =====
        $rows[] = $pad(['REKAP ABSENSI KARYAWAN']);
        $rows[] = $pad(['Nama', $this->meta['nama']]);
        $rows[] = $pad(['Unit Kerja', $this->meta['unit']]);
        $rows[] = $pad(['Periode', $this->periodLabel]);
        $rows[] = $pad([]);

        // ===== HEADER TABEL HARIAN =====
        $rows[] = $pad([
            'Tanggal',
            'Jam Masuk',
            'Jam Keluar',
            'Durasi (mnt)',
            'Status',
            'Terlambat (mnt)',
            'Jarak (m)',
            'Lembur (mnt)',
            'On-Call (mnt)',
        ]);

        // ===== DATA HARIAN =====
        foreach ($this->rows as $row) {
            $rows[] = $pad($row);
        }

        $rows[] = $pad([]);

        // ===== RINGKASAN =====
        $s = $this->meta['summary'] ?? [];
        $totalLembur    = (int) ($s['total_lembur_menit'] ?? 0);
        $totalOnCall    = (int) ($s['total_oncall_menit'] ?? 0);
        $totalTerlambat = (int) ($s['total_terlambat_menit'] ?? 0);

        $rows[] = $pad(['RINGKASAN TOTAL']);
        $rows[] = $pad(['Total Lembur', $totalLembur . ' menit (' . round($totalLembur / 60, 2) . ' jam)']);
        $rows[] = $pad(['Total On-Call', $totalOnCall . ' menit (' . round($totalOnCall / 60, 2) . ' jam)']);
        $rows[] = $pad(['Total Keterlambatan', $totalTerlambat . ' menit']);
        $rows[] = $pad([]);

        // ===== BREAKDOWN KETERLAMBATAN =====
        $rows[] = $pad(['BREAKDOWN KETERLAMBATAN (PERATURAN POTONGAN)']);
        $rows[] = $pad(['Kategori', 'Total Menit', 'Persentase', 'Potongan (mnt)']);
        $rows[] = $pad(['Terlambat 6 - 10 menit',  (int) ($s['terlambat_6_10'] ?? 0),  '25%',  (int) ($s['potongan_6_10'] ?? 0)]);
        $rows[] = $pad(['Terlambat 11 - 15 menit', (int) ($s['terlambat_11_15'] ?? 0), '50%',  (int) ($s['potongan_11_15'] ?? 0)]);
        $rows[] = $pad(['Terlambat 16 - 20 menit', (int) ($s['terlambat_16_20'] ?? 0), '100%', (int) ($s['potongan_16_20'] ?? 0)]);

        $terlambat21plus = (int) ($s['terlambat_21plus'] ?? 0);
        if ($terlambat21plus > 0) {
            $rows[] = $pad(['Terlambat > 20 menit', $terlambat21plus, 'Tindak Lanjut', $terlambat21plus]);
        }

        $rows[] = $pad([]);
        $rows[] = $pad(['TOTAL POTONGAN', (int) ($s['total_potongan'] ?? 0) . ' menit']);

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        $data = $this->array();
        $cols = range('A', 'I');

        // ===== Cari posisi section =====
        $ringkasanRow = $breakdownRow = $totalPotonganRow = null;
        foreach ($data as $i => $row) {
            $v = $row[0] ?? null;
            if ($v === 'RINGKASAN TOTAL') $ringkasanRow = $i + 1;
            if ($v === 'BREAKDOWN KETERLAMBATAN (PERATURAN POTONGAN)') $breakdownRow = $i + 1;
            if ($v === 'TOTAL POTONGAN') $totalPotonganRow = $i + 1;
        }

        // ===== 1. BANNER JUDUL (hijau tua) =====
        foreach ($cols as $col) {
            $sheet->getStyle($col . '1')->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1B5E20']],
            ]);
        }
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(28);

        // ===== 2. IDENTITAS (hijau muda, kolom A & B) =====
        for ($r = 2; $r <= 4; $r++) {
            $sheet->getStyle("A{$r}")->applyFromArray([
                'font'    => ['bold' => true],
                'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E8F5E9']],
                'borders' => $this->thinBorder(),
            ]);
            $sheet->getStyle("B{$r}")->applyFromArray([
                'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E8F5E9']],
                'borders' => $this->thinBorder(),
            ]);
        }

        // ===== 3. HEADER TABEL (hijau) =====
        foreach ($cols as $col) {
            $sheet->getStyle($col . '6')->applyFromArray([
                'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4CAF50']],
                'borders'   => $this->thinBorder(),
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ]);
        }
        $sheet->getRowDimension(6)->setRowHeight(22);

        // ===== 4. DATA HARIAN: border + zebra =====
        $n = count($this->rows);
        if ($n > 0) {
            $start = 7;
            $end   = 6 + $n;

            $sheet->getStyle("A{$start}:I{$end}")->applyFromArray([
                'borders'   => $this->thinBorder(),
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ]);

            for ($r = $start; $r <= $end; $r++) {
                if (($r - $start) % 2 === 1) {
                    foreach ($cols as $col) {
                        $sheet->getStyle($col . $r)->getFill()
                            ->setFillType(Fill::FILL_SOLID)
                            ->getStartColor()->setRGB('F1F8E9');
                    }
                }
            }
        }

        // ===== 5. RINGKASAN (banner oranye + 3 baris) =====
        if ($ringkasanRow) {
            foreach ($cols as $col) {
                $sheet->getStyle($col . $ringkasanRow)->applyFromArray([
                    'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EF6C00']],
                ]);
            }
            $sheet->getStyle("A{$ringkasanRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getRowDimension($ringkasanRow)->setRowHeight(22);

            for ($i = 1; $i <= 3; $i++) {
                $r = $ringkasanRow + $i;
                $sheet->getStyle("A{$r}")->applyFromArray([
                    'font'    => ['bold' => true],
                    'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF3E0']],
                    'borders' => $this->thinBorder(),
                ]);
                $sheet->getStyle("B{$r}")->applyFromArray([
                    'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF3E0']],
                    'borders' => $this->thinBorder(),
                ]);
            }
        }

        // ===== 6. BREAKDOWN (banner merah + header + baris warna) =====
        if ($breakdownRow) {
            foreach ($cols as $col) {
                $sheet->getStyle($col . $breakdownRow)->applyFromArray([
                    'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'C62828']],
                ]);
            }
            $sheet->getStyle("A{$breakdownRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getRowDimension($breakdownRow)->setRowHeight(22);

            $h = $breakdownRow + 1;
            foreach (['A', 'B', 'C', 'D'] as $col) {
                $sheet->getStyle($col . $h)->applyFromArray([
                    'font'      => ['bold' => true],
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFCDD2']],
                    'borders'   => $this->thinBorder(),
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);
            }

            $colors = ['FFF8E1', 'FFECB3', 'FFCCBC', 'FFAB91'];
            for ($i = 0; $i < 4; $i++) {
                $r     = $breakdownRow + 2 + $i;
                $label = $data[$r - 1][0] ?? null;

                if ($label && $label !== 'TOTAL POTONGAN') {
                    foreach (['A', 'B', 'C', 'D'] as $col) {
                        $sheet->getStyle($col . $r)->applyFromArray([
                            'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $colors[$i]]],
                            'borders' => $this->thinBorder(),
                        ]);
                    }
                    $sheet->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    foreach (['B', 'C', 'D'] as $col) {
                        $sheet->getStyle($col . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    }
                }
            }
        }

        // ===== 7. TOTAL POTONGAN (merah tua) =====
        if ($totalPotonganRow) {
            $sheet->getStyle("A{$totalPotonganRow}")->applyFromArray([
                'font'    => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
                'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'B71C1C']],
                'borders' => $this->mediumBorder(),
            ]);
            $sheet->getStyle("B{$totalPotonganRow}")->applyFromArray([
                'font'      => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'B71C1C']],
                'borders'   => $this->mediumBorder(),
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);
            $sheet->getRowDimension($totalPotonganRow)->setRowHeight(22);
        }

        // ===== 8. Padding & margin kolom =====
        foreach ($cols as $col) {
            $sheet->getStyle($col)->getAlignment()->setWrapText(false);
        }

        return [];
    }

    private function thinBorder(): array
    {
        return [
            'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'BDBDBD']],
        ];
    }

    private function mediumBorder(): array
    {
        return [
            'allBorders' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '757575']],
        ];
    }
}
