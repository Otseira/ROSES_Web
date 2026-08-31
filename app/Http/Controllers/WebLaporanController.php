<?php

namespace App\Http\Controllers;

use App\Models\LogAbsensi;
use App\Models\LogLembur;
use App\Models\MasterUnitKerja;
use App\Models\PengaturanAplikasi;
use Illuminate\Http\Request;

class WebLaporanController extends Controller
{
    public function index(Request $request)
    {
        $bulan = (int) $request->input('bulan', now()->month);
        $tahun = (int) $request->input('tahun', now()->year);
        $unit  = $request->input('unit');

        // === LOGIKA HAK AKSES MULTI-UNIT ===
        $userLogin = $request->user();
        $allowedUnitIds = $this->getAllowedUnitIds($userLogin);

        if ($allowedUnitIds !== null) {
            if ($unit && !in_array($unit, $allowedUnitIds)) {
                abort(403, 'Anda tidak memiliki akses untuk melihat laporan unit tersebut.');
            }
        }

        [$logs, $lemburs] = $this->buildData($bulan, $tahun, $unit, $allowedUnitIds);

        // === STATISTIK RINGKASAN ===
        $stats = [
            'total'      => $logs->count(),
            'tepat'      => $logs->where('status_kehadiran', 'Tepat Waktu')->count(),
            'terlambat'  => $logs->where('status_kehadiran', 'Terlambat')->count(),
            'luar'       => $logs->where('status_kehadiran', 'Luar Jadwal')->count(),
            'menit_late' => (int) $logs->sum('menit_terlambat'),
            'jam_lembur' => round($lemburs->flatten()->sum('total_jam_lembur'), 1),
        ];

        // === FILTER DROPDOWN UNIT ===
        if ($allowedUnitIds !== null) {
            $units = MasterUnitKerja::whereIn('id', $allowedUnitIds)
                ->orderBy('nama_unit', 'asc')
                ->get();
        } else {
            $units = MasterUnitKerja::orderBy('nama_unit', 'asc')->get();
        }

        // === BARU: KELOMPOKKAN LOGS BERDASARKAN UNIT KERJA ===
        $logsGrouped = $logs->groupBy(function ($log) {
            // Coba ambil unit dari relasi user, fallback ke relasi roster->user
            $user = $log->user ?? $log->roster?->user;
            return $user?->unitKerja?->nama_unit ?? 'Tanpa Unit';
        })->sortKeys(); // urutkan berdasarkan abjad nama unit

        return view('laporan.laporan', compact(
            'bulan',
            'tahun',
            'unit',
            'logs',
            'lemburs',
            'stats',
            'units',
            'logsGrouped' // <-- variabel baru
        ));
    }

    /**
     * Export EXCEL — kolom Lembur & On-Call TERPISAH (dalam satuan menit)
     */
    public function exportExcel(Request $request)
    {
        $bulan = (int) $request->input('bulan', now()->month);
        $tahun = (int) $request->input('tahun', now()->year);
        $unit  = $request->input('unit');

        // === LOGIKA HAK AKSES MULTI-UNIT ===
        $userLogin = $request->user();
        $allowedUnitIds = $this->getAllowedUnitIds($userLogin);

        if ($allowedUnitIds !== null && $unit && !in_array($unit, $allowedUnitIds)) {
            abort(403, 'Anda tidak memiliki akses untuk mengekspor laporan unit tersebut.');
        }

        [$logs, $lemburs] = $this->buildData($bulan, $tahun, $unit, $allowedUnitIds);

        $rows = [];
        $rows[] = ['REKAP ABSENSI & LEMBUR - ' . strtoupper(now()->month($bulan)->translatedFormat('F Y'))];
        $rows[] = [];
        $rows[] = [
            'Nama',
            'Unit Kerja',
            'Tanggal',
            'Jam Masuk',
            'Jam Keluar',
            'Durasi Kerja',
            'Status',
            'Terlambat (menit)',
            'Jarak ke Pusat (m)',
            'Lembur (Menit)',
            'On-Call (Menit)',
        ];

        foreach ($logs as $log) {
            $nama = $log->user?->name ?? $log->roster?->user?->name ?? '-';
            $key  = ($log->user_id ?? $log->roster?->user_id) . '|' . optional($log->waktu_masuk)->toDateString();
            $items = $lemburs->get($key);

            // ✅ Pisahkan total menit: LEMBUR vs ON-CALL
            $mLembur = 0;
            $mOncall = 0;
            if ($items) {
                foreach ($items as $l) {
                    $mnt = (float) ($l->total_jam_lembur ?? 0) * 60;
                    $norm = str_contains(
                        strtolower(str_replace(['-', ' ', '_'], '', $l->jenis_lembur ?? '')),
                        'oncall'
                    );
                    $norm ? $mOncall += $mnt : $mLembur += $mnt;
                }
            }
            $mLembur = (int) round($mLembur);
            $mOncall = (int) round($mOncall);

            $rows[] = [
                $nama,
                $log->user?->unitKerja?->nama_unit ?? $log->roster?->user?->unitKerja?->nama_unit ?? '-',
                optional($log->waktu_masuk)->format('d/m/Y') ?? '-',
                optional($log->waktu_masuk)->format('H:i') ?? '-',
                optional($log->waktu_pulang)->format('H:i') ?? '-',
                $log->durasi_kerja ?? '-',
                $log->status_kehadiran,
                $log->menit_terlambat ?? 0,
                $log->jarak ?? '-',
                $mLembur,   // ✅ kolom lembur (menit)
                $mOncall,   // ✅ kolom on-call (menit)
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

    /**
     * Export PDF — buka di tab baru, render halaman print-friendly
     */
    public function exportPdf(Request $request)
    {
        $bulan = (int) $request->input('bulan', now()->month);
        $tahun = (int) $request->input('tahun', now()->year);
        $unit  = $request->input('unit');

        // === LOGIKA HAK AKSES MULTI-UNIT ===
        $userLogin = $request->user();
        $allowedUnitIds = $this->getAllowedUnitIds($userLogin);

        if ($allowedUnitIds !== null && $unit && !in_array($unit, $allowedUnitIds)) {
            abort(403, 'Anda tidak memiliki akses untuk melihat PDF laporan unit tersebut.');
        }

        [$logs, $lemburs] = $this->buildData($bulan, $tahun, $unit, $allowedUnitIds);

        return view('laporan.pdf', compact('bulan', 'tahun', 'logs', 'lemburs'));
    }

    /**
     * BARU: Helper untuk mendapatkan daftar unit yang boleh diakses user
     */
    private function getAllowedUnitIds($user): ?array
    {
        if ($user->hasGlobalAccess()) {
            return null; // null = akses semua unit
        }

        $ids = $user->managesUnits()->pluck('master_unit_kerja_id')->toArray();

        // Tambahkan unit utama jika belum ada di daftar kelola
        if ($user->unit_kerja_id && !in_array($user->unit_kerja_id, $ids)) {
            $ids[] = $user->unit_kerja_id;
        }

        return $ids;
    }

    private function buildData(int $bulan, int $tahun, $unit = null, ?array $allowedUnitIds = null)
    {
        $pengaturan = PengaturanAplikasi::first();
        $lat = $pengaturan ? (float) $pengaturan->latitude : 0;
        $lng = $pengaturan ? (float) $pengaturan->longitude : 0;

        $logs = LogAbsensi::with(['user.unitKerja', 'roster.user.unitKerja'])
            ->whereMonth('waktu_masuk', $bulan)
            ->whereYear('waktu_masuk', $tahun)
            // Filter 1: Berdasarkan unit spesifik dari request
            ->when($unit, fn($q) => $q->whereHas('user', fn($u) => $u->where('unit_kerja_id', $unit)))
            // Filter 2: Batasi berdasarkan hak akses user (Kepala Unit hanya lihat unitnya)
            ->when($allowedUnitIds !== null, function ($q) use ($allowedUnitIds) {
                $q->whereHas('user', fn($u) => $u->whereIn('unit_kerja_id', $allowedUnitIds));
            })
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

                // ✅ Status kehadiran
                $log->status_kehadiran = $log->jenis_absen === 'luar_jadwal'
                    ? 'Luar Jadwal'
                    : ((($log->menit_terlambat ?? 0) > 0) ? 'Terlambat' : 'Tepat Waktu');

                // ✅ Durasi kerja
                if ($log->waktu_masuk && $log->waktu_pulang) {
                    $m = $log->waktu_masuk->diffInMinutes($log->waktu_pulang);
                    $log->durasi_kerja = intdiv($m, 60) . 'j ' . ($m % 60) . 'm';
                } else {
                    $log->durasi_kerja = null;
                }

                return $log;
            });

        $lemburs = LogLembur::with('user')
            ->whereMonth('waktu_mulai_lembur', $bulan)
            ->whereYear('waktu_mulai_lembur', $tahun)
            // Filter 1: Berdasarkan unit spesifik dari request
            ->when($unit, fn($q) => $q->whereHas('user', fn($u) => $u->where('unit_kerja_id', $unit)))
            // Filter 2: Batasi berdasarkan hak akses user
            ->when($allowedUnitIds !== null, function ($q) use ($allowedUnitIds) {
                $q->whereHas('user', fn($u) => $u->whereIn('unit_kerja_id', $allowedUnitIds));
            })
            ->orderBy('waktu_mulai_lembur')
            ->get()
            ->groupBy(fn($l) => $l->user_id . '|' . $l->waktu_mulai_lembur->toDateString());

        return [$logs, $lemburs];
    }

    /**
     * Hitung jarak (meter) antara titik kantor dan titik absen
     */
    private function haversine($lat1, $lon1, $lat2, $lon2): float
    {
        $R = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
