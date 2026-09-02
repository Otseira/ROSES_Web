<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class RekapAbsensiPerKaryawanExport implements WithMultipleSheets
{
    private array  $sheetsData;
    private string $periodLabel;

    public function __construct(array $sheetsData, string $periodLabel)
    {
        $this->sheetsData  = $sheetsData;
        $this->periodLabel = $periodLabel;
    }

    public function sheets(): array
    {
        $sheets = [];
        $used   = [];

        foreach ($this->sheetsData as $meta) {
            $title  = $this->sanitizeTitle($meta['nama'], $used);
            $used[] = $title;

            $sheets[] = new RekapKaryawanSheet($title, $meta, $meta['rows'], $this->periodLabel);
        }

        return $sheets;
    }

    /** Nama sheet Excel: maks 31 karakter, tanpa \ / ? * [ ] : , dan unik */
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
