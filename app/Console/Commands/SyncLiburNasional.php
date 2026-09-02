<?php

namespace App\Console\Commands;

use App\Models\LiburNasional;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class SyncLiburNasional extends Command
{
    protected $signature   = 'libur:sync {tahun?}';
    protected $description = 'Sinkronisasi data libur nasional dari API kalender Indonesia';

    public function handle()
    {
        $tahun = $this->argument('tahun') ?? now()->year;

        $this->info("🔄 Mengambil data libur nasional tahun {$tahun}...");

        try {
            $response = Http::timeout(15)
                ->get("https://dayoffapi.vercel.app/api?year={$tahun}");

            if (!$response->successful()) {
                $this->error("❌ API mengembalikan status: {$response->status()}");
                return 1;
            }

            $data = $response->json();

            if (empty($data)) {
                $this->warn("⚠️  Tidak ada data libur untuk tahun {$tahun}.");
                return 0;
            }

            $count = 0;

            foreach ($data as $item) {
                // Tentukan jenis: is_national_holiday atau cuti bersama
                $isNasional = $item['is_national_holiday'] ?? false;
                $jenis      = $isNasional ? 'nasional' : 'cuti_bersama';

                LiburNasional::updateOrCreate(
                    ['tanggal' => $item['tanggal']],
                    [
                        'nama'  => $item['keterangan'],
                        'jenis' => $jenis,
                    ]
                );

                $count++;
            }

            $this->info("✅ Berhasil sinkronisasi {$count} hari libur untuk tahun {$tahun}.");
            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Gagal mengambil data: {$e->getMessage()}");
            $this->warn("💡 Pastikan server memiliki akses internet.");
            return 1;
        }
    }
}