<?php

namespace App\Http\Controllers;

use App\Models\LogAbsensi;
use App\Models\LogLembur;
use App\Models\MasterUnitKerja;
use App\Models\PengaturanAplikasi;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\RekapAbsensiPerKaryawanExport;

class WebLaporanController extends Controller
{
    public function index(Request $request)
    {
        $unit       = $request->input('unit');
        $tglMulai   = $request->input('tanggal_mulai');
        $tglSelesai = $request->input('tanggal_selesai');

        // === LOGIKA HAK AKSES MULTI-UNIT ===
        $userLogin      = $request->user();
        $allowedUnitIds = $this->getAllowedUnitIds($userLogin);

        if ($allowedUnitIds !== null && $unit && !in_array($unit, $allowedUnitIds)) {
            abort(403, 'Anda tidak memiliki akses untuk melihat laporan unit tersebut.');
        }

        // === RENTANG TANGGAL (custom atau default) ===
        [$startDate, $endDate] = $this->resolveDateRange($tglMulai, $tglSelesai);

        [$logs, $lemburs] = $this->buildData($startDate, $endDate, $unit, $allowedUnitIds);

        // Statistik ringkasan
        $stats = [
            'total'      => $logs->count(),
            'tepat'      => $logs->where('status_kehadiran', 'Tepat Waktu')->count(),
            'terlambat'  => $logs->where('status_kehadiran', 'Terlambat')->count(),
            'luar'       => $logs->where('status_kehadiran', 'Luar Jadwal')->count(),
            'menit_late' => (int) $logs->sum('menit_terlambat'),
            'jam_lembur' => round($lemburs->flatten()->sum('total_jam_lembur'), 1),
        ];

        // Dropdown unit
        if ($allowedUnitIds !== null) {
            $units = MasterUnitKerja::whereIn('id', $allowedUnitIds)
                ->orderBy('nama_unit', 'asc')->get();
        } else {
            $units = MasterUnitKerja::orderBy('nama_unit', 'asc')->get();
        }

        // Kelompokkan per unit
        $logsGrouped = $logs->groupBy(function ($log) {
            $user = $log->user ?? $log->roster?->user;
            return $user?->unitKerja?->nama_unit ?? 'Tanpa Unit';
        })->sortKeys();

        // Untuk kompatibilitas view PDF (label periode)
        $bulan = Carbon::parse($startDate)->month;
        $tahun = Carbon::parse($startDate)->year;

        return view('laporan.laporan', compact(
            'unit',
            'logs',
            'lemburs',
            'stats',
            'units',
            'logsGrouped',
            'tglMulai',
            'tglSelesai',
            'startDate',
            'endDate',
            'bulan',
            'tahun'
        ));
    }

    public function exportExcel(Request $request)
    {
        $unit       = $request->input('unit');
        $tglMulai   = $request->input('tanggal_mulai');
        $tglSelesai = $request->input('tanggal_selesai');

        $userLogin      = $request->user();
        $allowedUnitIds = $this->getAllowedUnitIds($userLogin);

        if ($allowedUnitIds !== null && $unit && !in_array($unit, $allowedUnitIds)) {
            abort(403, 'Anda tidak memiliki akses untuk mengekspor laporan unit tersebut.');
        }

        [$startDate, $endDate] = $this->resolveDateRange($tglMulai, $tglSelesai);
        [$logs, $lemburs] = $this->buildData($startDate, $endDate, $unit, $allowedUnitIds);

        $periodLabel = Carbon::parse($startDate)->translatedFormat('d M Y')
            . ' s/d ' . Carbon::parse($endDate)->translatedFormat('d M Y');

        // ===== ✅ KELOMPOKKAN ABSENSI PER KARYAWAN (Untuk Multi-Sheet) =====
        $grouped = $logs->groupBy(function ($log) {
            $user = $log->user ?? $log->roster?->user;
            return $user?->id ?? ('log-' . $log->id);
        });

        $sheetsData = [];
        foreach ($grouped as $userId => $groupLogs) {
            $first = $groupLogs->first();
            $user  = $first->user ?? $first->roster?->user;

            $rows = [];
            foreach ($groupLogs as $log) {
                $key   = ($log->user_id ?? $log->roster?->user_id) . '|' . optional($log->waktu_masuk)->toDateString();
                $items = $lemburs->get($key);

                $mLembur = 0;
                $mOncall = 0;
                if ($items) {
                    foreach ($items as $l) {
                        $mnt  = (float) ($l->total_jam_lembur ?? 0) * 60;
                        $norm = str_contains(strtolower(str_replace(['-', ' ', '_'], '', $l->jenis_lembur ?? '')), 'oncall');
                        $norm ? $mOncall += $mnt : $mLembur += $mnt;
                    }
                }

                $rows[] = [
                    optional($log->waktu_masuk)->format('d/m/Y') ?? '-',
                    optional($log->waktu_masuk)->format('H:i') ?? '-',
                    optional($log->waktu_pulang)->format('H:i') ?? '-',
                    $log->durasi_kerja ?? '-',
                    $log->status_kehadiran,
                    (int) ($log->menit_terlambat ?? 0),
                    $log->jarak ?? '-',
                    (int) round($mLembur),
                    (int) round($mOncall),
                ];
            }

            $sheetsData[] = [
                'nama' => $user?->name ?? 'Tanpa Nama',
                'unit' => $user?->unitKerja?->nama_unit ?? '-',
                'rows' => $rows,
            ];
        }

        // Urutkan sheet sesuai abjad nama
        usort($sheetsData, fn($a, $b) => strcasecmp($a['nama'], $b['nama']));

        // ✅ EKSTENSI .xls (bukan .xlsx)
        $filename = 'rekap-absensi-'
            . Carbon::parse($startDate)->format('Y-m-d') . '-'
            . Carbon::parse($endDate)->format('Y-m-d') . '.xls';

        // ✅ WRITER Xls (BIFF8) — TIDAK butuh extension zip
        return Excel::download(
            new \App\Exports\RekapAbsensiPerKaryawanExport($sheetsData, $periodLabel),   // ✅ huruf besar
            $filename,
            \Maatwebsite\Excel\Excel::XLS
        );
    }

    public function exportPdf(Request $request)
    {
        $unit       = $request->input('unit');
        $tglMulai   = $request->input('tanggal_mulai');
        $tglSelesai = $request->input('tanggal_selesai');

        $userLogin      = $request->user();
        $allowedUnitIds = $this->getAllowedUnitIds($userLogin);

        if ($allowedUnitIds !== null && $unit && !in_array($unit, $allowedUnitIds)) {
            abort(403, 'Anda tidak memiliki akses untuk melihat PDF laporan unit tersebut.');
        }

        [$startDate, $endDate] = $this->resolveDateRange($tglMulai, $tglSelesai);
        [$logs, $lemburs] = $this->buildData($startDate, $endDate, $unit, $allowedUnitIds);

        $bulan = Carbon::parse($startDate)->month;
        $tahun = Carbon::parse($startDate)->year;

        return view('laporan.pdf', compact(
            'bulan',
            'tahun',
            'logs',
            'lemburs',
            'tglMulai',
            'tglSelesai',
            'startDate',
            'endDate'
        ));
    }

    /**
     * ✅ Rentang tanggal: pakai input user; jika kosong → 1 bulan berjalan s/d hari ini.
     */
    private function resolveDateRange(?string $tglMulai, ?string $tglSelesai): array
    {
        if ($tglMulai && $tglSelesai) {
            return [
                Carbon::parse($tglMulai)->startOfDay()->toDateTimeString(),
                Carbon::parse($tglSelesai)->endOfDay()->toDateTimeString(),
            ];
        }

        return [
            now()->startOfMonth()->toDateTimeString(),
            now()->endOfDay()->toDateTimeString(),
        ];
    }

    private function getAllowedUnitIds($user): ?array
    {
        if ($user->hasGlobalAccess()) {
            return null;
        }
        $ids = $user->managesUnits()->pluck('master_unit_kerja_id')->toArray();
        if ($user->unit_kerja_id && !in_array($user->unit_kerja_id, $ids)) {
            $ids[] = $user->unit_kerja_id;
        }
        return $ids;
    }

    private function buildData(string $startDate, string $endDate, $unit = null, ?array $allowedUnitIds = null)
    {
        $pengaturan = PengaturanAplikasi::first();
        $lat = $pengaturan ? (float) $pengaturan->latitude : 0;
        $lng = $pengaturan ? (float) $pengaturan->longitude : 0;

        $logs = LogAbsensi::with(['user.unitKerja', 'roster.user.unitKerja', 'roster.shift'])
            ->whereBetween('waktu_masuk', [$startDate, $endDate])
            ->when($unit, function ($q) use ($unit) {
                $q->where(function ($q2) use ($unit) {
                    $q2->whereHas('roster.user', fn($u) => $u->where('unit_kerja_id', $unit))
                        ->orWhereHas('user', fn($u) => $u->where('unit_kerja_id', $unit));
                });
            })
            ->when($allowedUnitIds !== null, function ($q) use ($allowedUnitIds) {
                $q->where(function ($q2) use ($allowedUnitIds) {
                    $q2->whereHas('roster.user', fn($u) => $u->whereIn('unit_kerja_id', $allowedUnitIds))
                        ->orWhereHas('user', fn($u) => $u->whereIn('unit_kerja_id', $allowedUnitIds));
                });
            })
            ->orderBy('waktu_masuk')
            ->get()
            ->map(function ($log) use ($lat, $lng) {
                // Hitung jarak
                $log->jarak_masuk = is_numeric($log->latitude_masuk)
                    ? round($this->haversine($lat, $lng, (float) $log->latitude_masuk, (float) $log->longitude_masuk))
                    : null;
                $log->jarak_pulang = is_numeric($log->latitude_pulang)
                    ? round($this->haversine($lat, $lng, (float) $log->latitude_pulang, (float) $log->longitude_pulang))
                    : null;
                $log->jarak = $log->jarak_masuk ?? $log->jarak_pulang;

                // ✅ HITUNG ULANG STATUS berdasarkan roster (real-time)
                $this->recalculateStatusForReport($log);

                // Hitung durasi kerja
                if ($log->waktu_masuk && $log->waktu_pulang) {
                    $m = $log->waktu_masuk->diffInMinutes($log->waktu_pulang);
                    $log->durasi_kerja = intdiv($m, 60) . 'j ' . ($m % 60) . 'm';
                } else {
                    $log->durasi_kerja = null;
                }

                return $log;
            });

        $lemburs = LogLembur::with('user')
            ->whereBetween('waktu_mulai_lembur', [$startDate, $endDate])
            ->when($unit, fn($q) => $q->whereHas('user', fn($u) => $u->where('unit_kerja_id', $unit)))
            ->when($allowedUnitIds !== null, function ($q) use ($allowedUnitIds) {
                $q->whereHas('user', fn($u) => $u->whereIn('unit_kerja_id', $allowedUnitIds));
            })
            ->orderBy('waktu_mulai_lembur')
            ->get()
            ->groupBy(fn($l) => $l->user_id . '|' . $l->waktu_mulai_lembur->toDateString());

        return [$logs, $lemburs];
    }

    /**
     * ✅ Hitung ulang status untuk laporan (real-time, tidak mengubah database).
     * Jika ada roster → hitung keterlambatan berdasarkan shift.
     * Jika tidak ada roster → "Tanpa Jadwal" atau "Luar Jadwal".
     */
    private function recalculateStatusForReport($log): void
    {
        $roster = $log->roster;

        // Tanpa roster → Tanpa Jadwal atau Luar Jadwal
        if (!$roster || !$roster->shift || !$log->waktu_masuk) {
            $log->status_kehadiran = ($log->jenis_absen === 'luar_jadwal')
                ? 'Luar Jadwal'
                : 'Tanpa Jadwal';
            $log->menit_terlambat = 0;
            return;
        }

        // Ada roster → hitung keterlambatan
        $shift = $roster->shift;
        $expected = \Carbon\Carbon::parse($roster->tanggal_dinas . ' ' . $shift->jam_masuk);

        // Shift malam (overnight): jika absen pagi tapi shift dimulai kemarin malam
        if ($shift->jam_pulang < $shift->jam_masuk && $log->waktu_masuk->hour < 12) {
            $expected->subDay();
        }

        $selisih = $expected->diffInMinutes($log->waktu_masuk, false); // positif = terlambat
        $toleransi = (int) ($shift->toleransi_terlambat_menit ?? 5);

        $log->menit_terlambat = ($selisih > $toleransi) ? (int) $selisih : 0;

        $log->status_kehadiran = ($log->jenis_absen === 'luar_jadwal')
            ? 'Luar Jadwal'
            : ($log->menit_terlambat > 0 ? 'Terlambat' : 'Tepat Waktu');
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
