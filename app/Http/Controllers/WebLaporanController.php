<?php

namespace App\Http\Controllers;

use App\Models\LogAbsensi;
use App\Models\LogLembur;
use App\Models\PengaturanAplikasi;
use Illuminate\Http\Request;

class WebLaporanController extends Controller
{
    public function index(Request $request)
    {
        $bulan = (int) $request->input('bulan', now()->month);
        $tahun = (int) $request->input('tahun', now()->year);

        [$logs, $lemburs] = $this->buildData($bulan, $tahun);

        return view('laporan.laporan', compact('bulan', 'tahun', 'logs', 'lemburs'));
    }

    public function exportExcel(Request $request)
    {
        $bulan = (int) $request->input('bulan', now()->month);
        $tahun = (int) $request->input('tahun', now()->year);

        [$logs, $lemburs] = $this->buildData($bulan, $tahun);

        $rows = [];
        $rows[] = ['REKAP ABSENSI & LEMBUR - ' . strtoupper(now()->month($bulan)->translatedFormat('F Y'))];
        $rows[] = [];
        $rows[] = ['Nama', 'Tanggal', 'Jam Masuk', 'Jam Keluar', 'Jarak ke Pusat (m)', 'Lembur / On-Call'];

        foreach ($logs as $log) {
            $nama = $log->user?->name ?? $log->roster?->user?->name ?? '-';
            $key  = ($log->user_id ?? $log->roster?->user_id) . '|' . optional($log->waktu_masuk)->toDateString();
            $items = $lemburs->get($key);

            $lemburText = '-';
            if ($items && $items->isNotEmpty()) {
                $lemburText = $items->map(
                    fn($l) =>
                    $l->jenis_lembur . ' (' . number_format($l->total_jam_lembur ?? 0, 1) . ' jam, ' . $l->status_validasi . ')'
                )->implode(' ; ');
            }

            $rows[] = [
                $nama,
                optional($log->waktu_masuk)->format('d/m/Y') ?? '-',
                optional($log->waktu_masuk)->format('H:i') ?? '-',
                optional($log->waktu_pulang)->format('H:i') ?? '-',
                $log->jarak ?? '-',
                $lemburText,
            ];
        }

        $csv = "\xEF\xBB\xBF";
        foreach ($rows as $row) {
            $csv .= implode(';', array_map(fn($c) => '"' . str_replace('"', '""', (string) $c) . '"', $row)) . "\n";
        }

        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="rekap-absensi-' . $tahun . '-' . str_pad($bulan, 2, '0', STR_PAD_LEFT) . '.csv"',
        ]);
    }

    public function exportPdf(Request $request)
    {
        $bulan = (int) $request->input('bulan', now()->month);
        $tahun = (int) $request->input('tahun', now()->year);

        [$logs, $lemburs] = $this->buildData($bulan, $tahun);

        return view('laporan.pdf', compact('bulan', 'tahun', 'logs', 'lemburs'));
    }

    private function buildData(int $bulan, int $tahun)
    {
        $pengaturan = PengaturanAplikasi::first();
        $lat = $pengaturan ? (float) $pengaturan->latitude : 0;
        $lng = $pengaturan ? (float) $pengaturan->longitude : 0;

        $logs = LogAbsensi::with(['user', 'roster.user'])
            ->whereMonth('waktu_masuk', $bulan)
            ->whereYear('waktu_masuk', $tahun)
            ->orderBy('waktu_masuk')
            ->get()
            ->map(function ($log) use ($lat, $lng) {
                $log->jarak_masuk = is_numeric($log->latitude_masuk)
                    ? round($this->haversine($lat, $lng, (float) $log->latitude_masuk, (float) $log->longitude_masuk))
                    : null;
                $log->jarak_pulang = is_numeric($log->latitude_pulang)
                    ? round($this->haversine($lat, $lng, (float) $log->latitude_pulang, (float) $log->longitude_pulang))
                    : null;

                $log->jarak = $log->jarak_masuk ?? $log->jarak_pulang;

                return $log;
            });

        $lemburs = LogLembur::with('user')
            ->whereMonth('waktu_mulai_lembur', $bulan)
            ->whereYear('waktu_mulai_lembur', $tahun)
            ->orderBy('waktu_mulai_lembur')
            ->get()
            ->groupBy(fn($l) => $l->user_id . '|' . $l->waktu_mulai_lembur->toDateString());

        return [$logs, $lemburs];
    }

    private function haversine($lat1, $lon1, $lat2, $lon2): float
    {
        $R = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
