<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class RekapLemburExport implements WithMultipleSheets
{
    use Exportable;

    private array $dataDibayar;
    private array $dataBankJam;
    private array $dataPengambilan;
    private string $periodLabel;

    public function __construct(array $dataDibayar, array $dataBankJam, array $dataPengambilan, string $periodLabel)
    {
        $this->dataDibayar     = $dataDibayar;
        $this->dataBankJam     = $dataBankJam;
        $this->dataPengambilan = $dataPengambilan;
        $this->periodLabel     = $periodLabel;
    }

    public function sheets(): array
    {
        return [
            new RekapLemburDibayarSheet($this->dataDibayar, $this->periodLabel),
            new RekapBankJamSheet($this->dataBankJam, $this->dataPengambilan, $this->periodLabel),
        ];
    }
}

class RekapLemburDibayarSheet implements FromArray, WithTitle, WithStyles, ShouldAutoSize
{
    private array $rows;
    private string $periodLabel;

    public function __construct(array $rows, string $periodLabel)
    {
        $this->rows        = $rows;
        $this->periodLabel = $periodLabel;
    }

    public function title(): string
    {
        return 'Lembur Berbayar (Payroll)';
    }

    public function array(): array
    {
        $data = [
            ['REKAPITULASI LEMBUR BERBAYAR (PAYROLL)'],
            ['Periode: ' . $this->periodLabel],
            [''],
            ['No', 'Nama Pegawai', 'NIK', 'Unit Kerja', 'Jenis', 'Tanggal', 'Jam Mulai', 'Jam Selesai', 'Durasi (Menit)', 'Status', 'Keterangan'],
        ];

        $no = 1;
        $totalMenit = 0;
        foreach ($this->rows as $r) {
            $menit = (int) ($r['durasi_menit'] ?? round(((float) ($r['total_jam_raw'] ?? 0)) * 60));
            if (($r['status'] ?? '') === 'Disetujui') {
                $totalMenit += $menit;
            }

            $data[] = [
                $no++,
                $r['nama'] ?? '-',
                $r['nik'] ?? '-',
                $r['unit'] ?? '-',
                $r['jenis'] ?? '-',
                $r['tanggal'] ?? '-',
                $r['jam_masuk'] ?? '-',
                $r['jam_keluar'] ?? '-',
                $r['durasi'] ?? "{$menit} menit",
                $r['status'] ?? '-',
                $r['alasan'] ?? '-',
            ];
        }

        $data[] = [''];
        $data[] = ['', '', '', '', '', '', '', 'Total Lembur Disetujui:', number_format($totalMenit, 0, ',', '.') . ' Menit', '', ''];

        return $data;
    }

    public function styles(Worksheet $sheet): ?array
    {
        $sheet->mergeCells('A1:K1');
        $sheet->mergeCells('A2:K2');

        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A2')->getFont()->setItalic(true)->setSize(11);

        // Header tabel di baris 4
        $sheet->getStyle('A4:K4')->getFont()->setBold(true);
        $sheet->getStyle('A4:K4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE2E8F0');

        $lastRow = count($this->rows) + 4;
        $sheet->getStyle("A4:K{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        return [];
    }
}

class RekapBankJamSheet implements FromArray, WithTitle, WithStyles, ShouldAutoSize
{
    private array $dataBankJam;
    private array $dataPengambilan;
    private string $periodLabel;

    public function __construct(array $dataBankJam, array $dataPengambilan, string $periodLabel)
    {
        $this->dataBankJam     = $dataBankJam;
        $this->dataPengambilan = $dataPengambilan;
        $this->periodLabel     = $periodLabel;
    }

    public function title(): string
    {
        return 'Saldo Bank Jam & Kompensasi';
    }

    public function array(): array
    {
        $data = [
            ['REKAPITULASI SALDO BANK JAM LEMBUR & KOMPENSASI'],
            ['Periode: ' . $this->periodLabel],
            [''],
            ['BAGIAN 1: SALDO BANK JAM PEGAWAI (UNIT KOMPENSASI)'],
            ['No', 'Nama Pegawai', 'NIK', 'Unit Kerja', 'Total Diperoleh (Menit) (+)', 'Total Digunakan (Menit) (-)', 'Saldo Tersedia (Menit)', 'Status Saldo'],
        ];

        $no = 1;
        foreach ($this->dataBankJam as $b) {
            $data[] = [
                $no++,
                $b['nama'] ?? '-',
                $b['nik'] ?? '-',
                $b['unit'] ?? '-',
                $b['jam_diperoleh_teks'] ?? '-',
                $b['jam_digunakan_teks'] ?? '-',
                $b['saldo_tersedia_teks'] ?? '-',
                ($b['saldo_menit'] ?? 0) > 0 ? 'Memiliki Saldo' : 'Nol',
            ];
        }

        $data[] = [''];
        $data[] = ['BAGIAN 2: RIWAYAT PENGAMBILAN JAM (MASUK LAMBAT / PULANG CEPAT)'];
        $data[] = ['No', 'Nama Pegawai', 'NIK', 'Unit Kerja', 'Tanggal Dinas', 'Jenis Pengambilan', 'Durasi Diambil (Menit)', 'Status', 'Alasan', 'Divalidasi Oleh'];

        $noPengambilan = 1;
        foreach ($this->dataPengambilan as $p) {
            $data[] = [
                $noPengambilan++,
                $p['nama'] ?? '-',
                $p['nik'] ?? '-',
                $p['unit'] ?? '-',
                $p['tanggal'] ?? '-',
                $p['tipe_label'] ?? '-',
                $p['durasi_teks'] ?? '-',
                $p['status'] ?? '-',
                $p['keterangan'] ?? '-',
                $p['validator_nama'] ?? '-',
            ];
        }

        return $data;
    }

    public function styles(Worksheet $sheet): ?array
    {
        $sheet->mergeCells('A1:J1');
        $sheet->mergeCells('A2:J2');

        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A2')->getFont()->setItalic(true)->setSize(11);

        $sheet->getStyle('A4')->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('A5:H5')->getFont()->setBold(true);
        $sheet->getStyle('A5:H5')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFEDE9FE');

        return [];
    }
}

