<?php

namespace App\Http\Controllers;

use App\Models\LogAbsensi;
use App\Models\LogLembur;
use App\Models\MasterUnitKerja;
use App\Models\PengaturanAplikasi;
use Carbon\Carbon;
use Illuminate\Http\Request;

class WebLaporanController extends Controller
{
    public function index(Request $request)
    {
        $bulan = (int) $request->input('bulan', now()->month);
        $tahun = (int) $request->input('tahun', now()->year);
        $unit  = $request->input('unit');
        $tglMulai  = $request->input('tanggal_mulai');   // ✅ BARU
        $tglSelesai = $request->input('tanggal_selesai'); // ✅ BARU

        // === LOGIKA HAK AKSES MULTI-UNIT ===
        $userLogin = $request->user();
        $allowedUnitIds = $this->getAllowedUnitIds($userLogin);

        if ($allowedUnitIds !== null) {
            if ($unit && !in_array($unit, $allowedUnitIds)) {
                abort(403, 'Anda tidak memiliki akses untuk melihat laporan unit tersebut.');
            }
        }

        // === TENTUKAN RENTANG TANGGAL ===
        $useCustomRange = $tglMulai && $tglSelesai;
        [$startDate, $endDate] = $this->resolveDateRange(
            $bulan,
            $tahun,
            $tglMulai,
            $tglSelesai
        );

        [$logs, $lemburs] = $this->buildData(
            $startDate,
            $endDate,
            $unit,
            $allowedUnitIds
        );

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

        return view('laporan.laporan', compact(
            'bulan',
            'tahun',
            'unit',
            'logs',
            'lemburs',
            'stats',
            'units',
            'logsGrouped',
            'tglMulai',
            'tglSelesai',
            'useCustomRange',
            'startDate',
            'endDate'
        ));
    }

    public function exportExcel(Request $request)
    {
        $bulan = (int) $request->input('bulan', now()->month);
        $tahun = (int) $request->input('tahun', now()->year);
        $unit  = $request->input('unit');
        $tglMulai  = $request->input('tanggal_mulai');
        $tglSelesai = $request->input('tanggal_selesai');

        $userLogin = $request->user();
        $allowedUnitIds = $this->getAllowedUnitIds($userLogin);

        if ($allowedUnitIds !== null && $unit && !in_array($unit, $allowedUnitIds)) {
            abort(403, 'Anda tidak memiliki akses untuk mengekspor laporan unit tersebut.');
        }

        [$startDate, $endDate] = $this->resolveDateRange(
            $bulan,
            $tahun,
            $tglMulai,
            $tglSelesai
        );
        [$logs, $lemburs] = $this->buildData(
            $startDate,
            $endDate,
            $unit,
            $allowedUnitIds
        );

        // Header baris
        $periodLabel = Carbon::parse($startDate)->translatedFormat('d M Y')
            . ' s/d ' . Carbon::parse($endDate)->translatedFormat('d M Y');

        $rows = [];
        $rows[] = ['REKAP ABSENSI & LEMBUR - ' . strtoupper($periodLabel)];
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
                $mLembur,
                $mOncall,
            ];
        }

        $csv = "\xEF\xBB\xBF";
        foreach ($rows as $row) {
            $csv .= implode(';', array_map(fn($c) => '"' . str_replace('"', '""', (string) $c) . '"', $row)) . "\n";
        }

        $filename = 'rekap-absensi-' . Carbon::parse($startDate)->format('Y-m-d') . '-' . Carbon::parse($endDate)->format('Y-m-d') . '.csv';

        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function exportPdf(Request $request)
    {
        $bulan = (int) $request->input('bulan', now()->month);
        $tahun = (int) $request->input('tahun', now()->year);
        $unit  = $request->input('unit');
        $tglMulai  = $request->input('tanggal_mulai');
        $tglSelesai = $request->input('tanggal_selesai');

        $userLogin = $request->user();
        $allowedUnitIds = $this->getAllowedUnitIds($userLogin);

        if ($allowedUnitIds !== null && $unit && !in_array($unit, $allowedUnitIds)) {
            abort(403, 'Anda tidak memiliki akses untuk melihat PDF laporan unit tersebut.');
        }

        [$startDate, $endDate] = $this->resolveDateRange(
            $bulan,
            $tahun,
            $tglMulai,
            $tglSelesai
        );
        [$logs, $lemburs] = $this->buildData(
            $startDate,
            $endDate,
            $unit,
            $allowedUnitIds
        );

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
     * ✅ BARU: Tentukan rentang tanggal efektif.
     * Jika user mengisi tanggal_mulai & tanggal_selesai → gunakan range custom.
     * Jika tidak → gunakan logika bulan + cut-off payroll (backwards compatible).
     */
    private function resolveDateRange(int $bulan, int $tahun, ?string $tglMulai, ?string $tglSelesai): array
    {
        if ($tglMulai && $tglSelesai) {
            $start = Carbon::parse($tglMulai)->startOfDay()->toDateTimeString();
            $end   = Carbon::parse($tglSelesai)->endOfDay()->toDateTimeString();
            return [$start, $end];
        }

        // Fallback: logika cut-off payroll lama
        $pengaturan     = PengaturanAplikasi::first();
        $tglMulaiRule   = $pengaturan ? $pengaturan->tanggal_cut_off_mulai : 24;
        $tglSelesaiRule = $pengaturan ? $pengaturan->tanggal_cut_off_selesai : 23;

        $start = Carbon::createFromDate($tahun, $bulan, $tglMulaiRule)->subMonth()->startOfDay()->toDateTimeString();
        $end   = Carbon::createFromDate($tahun, $bulan, $tglSelesaiRule)->endOfDay()->toDateTimeString();

        return [$start, $end];
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

    /**
     * ✅ UBAH SIGNATURE: sekarang menerima startDate/endDate langsung (bukan bulan/tahun).
     */
    private function buildData(string $startDate, string $endDate, $unit = null, ?array $allowedUnitIds = null)
    {
        $pengaturan = PengaturanAplikasi::first();
        $lat = $pengaturan ? (float) $pengaturan->latitude : 0;
        $lng = $pengaturan ? (float) $pengaturan->longitude : 0;

        $logs = LogAbsensi::with(['user.unitKerja', 'roster.user.unitKerja'])
            ->whereBetween('waktu_masuk', [$startDate, $endDate])
            ->when($unit, fn($q) => $q->whereHas('roster.user', fn($u) => $u->where('unit_kerja_id', $unit)))
            ->when($allowedUnitIds !== null, function ($q) use ($allowedUnitIds) {
                $q->whereHas('roster.user', fn($u) => $u->whereIn('unit_kerja_id', $allowedUnitIds));
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

                $log->status_kehadiran = $log->jenis_absen === 'luar_jadwal'
                    ? 'Luar Jadwal'
                    : ((($log->menit_terlambat ?? 0) > 0) ? 'Terlambat' : 'Tepat Waktu');

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

    private function haversine($lat1, $lon1, $lat2, $lon2): float
    {
        $R = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
