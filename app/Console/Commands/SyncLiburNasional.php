<?php

namespace App\Console\Commands;

use App\Models\LiburNasional;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SyncLiburNasional extends Command
{
    protected $signature   = 'libur:sync {tahun?}';
    protected $description = 'Sinkronisasi libur nasional + cuti bersama Indonesia (upset.dev/tanggalmerah, fallback Nager.Date)';

    public function handle()
    {
        $tahun = (int) ($this->argument('tahun') ?? now()->year);

        $sources = [
            'TanggalMerah (upset.dev)' => fn() => $this->fromTanggalMerah($tahun),
            'Nager.Date'               => fn() => $this->fromNager($tahun),
        ];

        foreach ($sources as $nama => $fetcher) {
            $this->info("🔄 Mencoba sumber: {$nama} (tahun {$tahun})...");

            try {
                $data = $fetcher();

                if (empty($data)) {
                    $this->warn("   ⚠️  {$nama}: tidak ada data, mencoba sumber lain...");
                    continue;
                }

                $count = 0;
                foreach ($data as [$tanggal, $namaLibur, $jenis]) {
                    try {
                        Carbon::parse($tanggal);
                    } catch (\Exception $e) {
                        continue;
                    }
                    if ((int) substr($tanggal, 0, 4) !== $tahun) continue;

                    LiburNasional::updateOrCreate(
                        ['tanggal' => $tanggal],
                        ['nama' => $namaLibur, 'jenis' => $jenis]
                    );
                    $count++;
                }

                if ($count === 0) {
                    $this->warn("   ⚠️  {$nama}: data tidak cocok, mencoba sumber lain...");
                    continue;
                }

                $this->info("✅ Berhasil: {$count} hari libur tersimpan dari {$nama}.");
                return 0;
            } catch (\Exception $e) {
                $this->warn("   ❌ {$nama} gagal: " . $e->getMessage());
            }
        }

        $this->error('❌ Semua sumber API gagal. Cadangan: php artisan db:seed --class=LiburNasionalSeeder');
        return 1;
    }

    /**
     * ✅ SUMBER UTAMA: https://upset.dev/tanggalmerah
     * type "holiday" = Libur Nasional (merah) | type "leave" = Cuti Bersama (ungu)
     */
    private function fromTanggalMerah(int $tahun): array
    {
        $res = Http::timeout(15)->get('https://upset.dev/tanggalmerah/api/holidays', ['year' => $tahun]);

        if (!$res->successful()) {
            throw new \Exception('HTTP ' . $res->status());
        }

        $body = $res->json();
        if (!is_array($body) || ($body['success'] ?? false) !== true) {
            throw new \Exception('Format respons tidak valid');
        }

        $out = [];
        foreach ($body['data'] ?? [] as $item) {
            $out[] = [
                $item['date'] ?? null,
                $item['name'] ?? 'Libur',
                (($item['type'] ?? 'holiday') === 'leave' ? 'cuti_bersama' : 'nasional'),
            ];
        }

        return array_filter($out, fn($r) => !empty($r[0]));
    }

    /** CADANGAN: Nager.Date (libur nasional resmi, tanpa API key) */
    private function fromNager(int $tahun): array
    {
        $res = Http::timeout(15)->get("https://date.nager.at/api/v3/PublicHolidays/{$tahun}/ID");

        if (!$res->successful()) {
            throw new \Exception('HTTP ' . $res->status());
        }

        $out = [];
        foreach ($res->json() ?? [] as $item) {
            $out[] = [
                $item['date'] ?? null,
                $item['localName'] ?? $item['name'] ?? 'Libur',
                'nasional',
            ];
        }

        return array_filter($out, fn($r) => !empty($r[0]));
    }
}
