<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
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

    /** Lebar minimal kolom (auto-size tetap aktif bila konten lebih panjang). */
    public function columnWidths(): array
    {
        return [
            'A' => 14,  // Tanggal
            'B' => 12,  // Jam Masuk
            'C' => 12,  // Jam Keluar
            'D' => 14,  // Durasi
            'E' => 16,  // Terlambat
            'F' => 12,  // Jarak
            'G' => 14,  // Lembur
            'H' => 14,  // On-Call
        ];
    }

    public function array(): array
    {
        $pad = fn(array $r): array => array_slice(array_pad($r, 8, ''), 0, 8);

        $rows = [];

        // ===== HEADER IDENTITAS =====
        $rows[] = $pad(['REKAP ABSENSI KARYAWAN']);
        $rows[] = $pad(['Nama', $this->meta['nama']]);
        $rows[] = $pad(['Unit Kerja', $this->meta['unit']]);
        $rows[] = $pad(['Periode', $this->periodLabel]);
        $rows[] = $pad([]);

        // ===== HEADER TABEL — ✅ TANPA kolom Status =====
        $rows[] = $pad([
            'Tanggal',
            'Jam Masuk',
            'Jam Keluar',
            'Durasi (mnt)',
            'Terlambat (mnt)',
            'Jarak (m)',
            'Lembur (mnt)',
            'On-Call (mnt)',
        ]);

        // ===== DATA HARIAN — ✅ kolom Status (index 4) dibuang =====
        foreach ($this->rows as $r) {
            $rows[] = $pad([
                $r[0] ?? '-',   // tanggal
                $r[1] ?? '-',   // jam masuk
                $r[2] ?? '-',   // jam keluar
                $r[3] ?? '-',   // durasi
                $r[5] ?? 0,     // terlambat (mnt)
                $r[6] ?? '-',   // jarak
                $r[7] ?? 0,     // lembur
                $r[8] ?? 0,     // on-call
            ]);
        }

        $rows[] = $pad([]);

        // ===== RINGKASAN (tetap seperti sebelumnya) =====
        $s = $this->meta['summary'] ?? [];
        $rows[] = $pad(['RINGKASAN TOTAL']);
        $rows[] = $pad(['Total Lembur', ((int) ($s['total_lembur_menit'] ?? 0)) . ' menit (' . round(($s['total_lembur_menit'] ?? 0) / 60, 2) . ' jam)']);
        $rows[] = $pad(['Total On-Call', ((int) ($s['total_oncall_menit'] ?? 0)) . ' menit (' . round(($s['total_oncall_menit'] ?? 0) / 60, 2) . ' jam)']);
        $rows[] = $pad(['Total Keterlambatan', ((int) ($s['total_terlambat_menit'] ?? 0)) . ' menit']);
        $rows[] = $pad([]);

        // ===== BREAKDOWN KETERLAMBATAN — 3 kategori + potongan Rupiah =====
        $rows[] = $pad(['BREAKDOWN KETERLAMBATAN (PERATURAN POTONGAN)']);
        $rows[] = $pad(['Kategori', 'Persentase', 'Total Menit', 'Potongan (Rupiah)']);

        $j1 = (int) ($s['jumlah_6_10'] ?? 0);
        $j2 = (int) ($s['jumlah_11_15'] ?? 0);
        $j3 = (int) ($s['jumlah_16plus'] ?? 0);

        $rows[] = $pad([
            'Terlambat 6 - 10 menit' . ($j1 > 1 ? " ({$j1}x)" : ''),
            '25%',
            (int) ($s['terlambat_6_10'] ?? 0),
            (int) ($s['rupiah_6_10'] ?? 0),
        ]);
        $rows[] = $pad([
            'Terlambat 11 - 15 menit' . ($j2 > 1 ? " ({$j2}x)" : ''),
            '50%',
            (int) ($s['terlambat_11_15'] ?? 0),
            (int) ($s['rupiah_11_15'] ?? 0),
        ]);
        $rows[] = $pad([
            'Terlambat ≥ 16 menit' . ($j3 > 1 ? " ({$j3}x)" : ''),
            '100%',
            (int) ($s['terlambat_16plus'] ?? 0),
            (int) ($s['rupiah_16plus'] ?? 0),
        ]);

        $rows[] = $pad([]);
        $rows[] = $pad(['TOTAL POTONGAN', '', '', (int) ($s['total_potongan_rupiah'] ?? 0)]);

        return $rows;

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        $data = $this->array();
        $cols = range('A', 'H');   // ✅ kini 8 kolom

        // ===== Posisi section =====
        $ringkasanRow = $breakdownRow = $totalPotonganRow = null;
        foreach ($data as $i => $row) {
            $v = $row[0] ?? null;
            if ($v === 'RINGKASAN TOTAL') $ringkasanRow = $i + 1;
            if ($v === 'BREAKDOWN KETERLAMBATAN (PERATURAN POTONGAN)') $breakdownRow = $i + 1;
            if ($v === 'TOTAL POTONGAN') $totalPotonganRow = $i + 1;
        }

        // ===== 1. BANNER JUDUL =====
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

        // ===== 2. IDENTITAS =====
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

        // ===== 3. HEADER TABEL (8 kolom) =====
        foreach ($cols as $col) {
            $sheet->getStyle($col . '6')->applyFromArray([
                'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4CAF50']],
                'borders'   => $this->thinBorder(),
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ]);
        }
        $sheet->getRowDimension(6)->setRowHeight(22);

        // ===== 4. DATA HARIAN — ✅ BARIS MERAH bila terlambat, POLOS bila tepat waktu =====
        $n = count($this->rows);
        if ($n > 0) {
            $start = 7;
            $end   = 6 + $n;

            // Border tipis semua baris (struktur tabel)
            $sheet->getStyle("A{$start}:H{$end}")->applyFromArray([
                'borders'   => $this->thinBorder(),
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ]);

            // Warnai per baris berdasarkan kolom Terlambat (index 4)
            for ($r = $start; $r <= $end; $r++) {
                $telat = (int) ($data[$r - 1][4] ?? 0);

                if ($telat > 0) {
                    // ✅ TERLAMBAT → satu baris penuh merah
                    foreach ($cols as $col) {
                        $sheet->getStyle($col . $r)->applyFromArray([
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FADBD8']],
                            'font' => ['bold' => true, 'color' => ['rgb' => 'B71C1C']],
                        ]);
                    }
                }
                // ✅ TEPAT WAKTU / lainnya → polos, tanpa tanda apa pun
            }
        }

        // ===== 5. RINGKASAN =====
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

        // ===== 6. BREAKDOWN (4 kolom, seperti sebelumnya) =====
        // ===== 6. BREAKDOWN (3 baris data + total) =====
        if ($breakdownRow) {
            // Banner header (hanya 4 kolom)
            foreach (['A', 'B', 'C', 'D'] as $col) {
                $sheet->getStyle($col . $breakdownRow)->applyFromArray([
                    'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'C62828']],
                ]);
            }
            $sheet->getStyle("A{$breakdownRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getRowDimension($breakdownRow)->setRowHeight(22);

            // Header kolom
            $h = $breakdownRow + 1;
            foreach (['A', 'B', 'C', 'D'] as $col) {
                $sheet->getStyle($col . $h)->applyFromArray([
                    'font'      => ['bold' => true],
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFCDD2']],
                    'borders'   => $this->thinBorder(),
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);
            }

            // 3 baris data breakdown
            $colors = ['FFF8E1', 'FFECB3', 'FFCCBC'];
            for ($i = 0; $i < 3; $i++) {
                $r = $breakdownRow + 2 + $i;
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
                // Format Rupiah di kolom D
                $sheet->getStyle("D{$r}")->getNumberFormat()->setFormatCode('"Rp" #,##0');
            }
        }

        // ===== 7. TOTAL POTONGAN (di kolom D, format Rupiah) =====
        if ($totalPotonganRow) {
            foreach (['A', 'D'] as $col) {
                $sheet->getStyle($col . $totalPotonganRow)->applyFromArray([
                    'font'    => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
                    'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'B71C1C']],
                    'borders' => $this->mediumBorder(),
                ]);
            }
            $sheet->getStyle("A{$totalPotonganRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle("D{$totalPotonganRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("D{$totalPotonganRow}")->getNumberFormat()->setFormatCode('"Rp" #,##0');
            $sheet->getRowDimension($totalPotonganRow)->setRowHeight(22);
        }

        // ===== 7. TOTAL POTONGAN =====
        if ($totalPotonganRow) {
            foreach (['A', 'D'] as $col) {
                $sheet->getStyle($col . $totalPotonganRow)->applyFromArray([
                    'font'    => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
                    'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'B71C1C']],
                    'borders' => $this->mediumBorder(),
                ]);
            }
            $sheet->getStyle("A{$totalPotonganRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle("D{$totalPotonganRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getRowDimension($totalPotonganRow)->setRowHeight(22);
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
