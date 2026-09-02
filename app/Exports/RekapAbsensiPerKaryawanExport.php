<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Export as ExcelExport;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class RekapAbsensiPerKaryawanExport implements ExcelExport, WithMultipleSheets
{
    private array $sheetsData;
    private string $periodLabel;

    public function __construct(array $sheetsData, string $periodLabel)
    {
        $this->sheetsData  = $sheetsData;
        $this->periodLabel = $periodLabel;
    }

    public function sheets(): array
    {
        // Pengaman jika periode tidak ada data
        if (empty($this->sheetsData)) {
            return [
                new RekapKaryawanSheet(
                    'Tidak Ada Data',
                    [
                        'nama' => '-',
                        'unit' => '-',
                    ],
                    [
                        ['Tidak ada data absensi pada periode yang dipilih.'],
                    ],
                    $this->periodLabel
                ),
            ];
        }

        $sheets = [];
        $used   = [];

        foreach ($this->sheetsData as $meta) {
            $title  = $this->sanitizeTitle($meta['nama'], $used);
            $used[] = $title;

            $sheets[] = new RekapKaryawanSheet(
                $title,
                $meta,
                $meta['rows'],
                $this->periodLabel
            );
        }

        return $sheets;
    }

    /**
     * Nama sheet Excel:
     * - Maksimal 31 karakter
     * - Tidak boleh mengandung: \ / ? * [ ] :
     * - Harus unik
     */
    private function sanitizeTitle(string $name, array $used): string
    {
        $clean = preg_replace('/[\\\\\/\?\*\[\]:]+/', '-', trim($name));
        $clean = substr($clean, 0, 28) ?: 'Karyawan';

        $title = $clean;
        $i = 1;

        while (in_array($title, $used)) {
            $suffix = ' (' . $i . ')';
            $title  = substr($clean, 0, 31 - strlen($suffix)) . $suffix;
            $i++;
        }

        return $title;
    }
}
