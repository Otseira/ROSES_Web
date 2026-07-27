<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\LogAbsensi;
use App\Models\LogLembur;
use App\Models\PengaturanPayroll;
use Carbon\Carbon;

class WebLaporanController extends Controller
{
    private function generateDataLaporan(Request $request)
    {
        $bulan = $request->input('bulan', date('m'));
        $tahun = $request->input('tahun', date('Y'));

        $pengaturan = PengaturanPayroll::first();
        $tglMulaiRule = $pengaturan ? $pengaturan->tanggal_cut_off_mulai : 24;
        $tglSelesaiRule = $pengaturan ? $pengaturan->tanggal_cut_off_selesai : 23;
        $ratePotongan = $pengaturan ? $pengaturan->potongan_terlambat_per_menit : 0;
        $rateLembur = $pengaturan ? $pengaturan->uang_lembur_per_jam : 0;

        $startDate = Carbon::createFromDate($tahun, $bulan, $tglMulaiRule)->subMonth()->startOfDay()->toDateString();
        $endDate = Carbon::createFromDate($tahun, $bulan, $tglSelesaiRule)->endOfDay()->toDateString();

        $userLogin = $request->user();
        $isGlobal = in_array($userLogin->role, ['superadmin', 'hrd']);

        $queryUsers = User::with('unitKerja')->where('role', '!=', 'superadmin');

        if (!$isGlobal) {
            $queryUsers->where('unit_kerja_id', $userLogin->unit_kerja_id);
        }

        $users = $queryUsers->get()->map(function ($user) use ($startDate, $endDate, $ratePotongan, $rateLembur) {
            $logsAbsen = LogAbsensi::whereHas('roster', function ($query) use ($user, $startDate, $endDate) {
                $query->where('user_id', $user->id)
                    ->whereBetween('tanggal_dinas', [$startDate, $endDate]);
            })->get();

            $totalHadir = $logsAbsen->whereNotNull('waktu_masuk')->count();
            $totalMenitTerlambat = $logsAbsen->sum('menit_terlambat');
            $potongan = $totalMenitTerlambat * $ratePotongan;

            $totalJamLembur = LogLembur::where('user_id', $user->id)
                ->where('status_validasi', 'Disetujui')
                ->whereBetween('waktu_mulai_lembur', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->sum('total_jam_lembur');

            $insentifLembur = $totalJamLembur * $rateLembur;
            $netto = $insentifLembur - $potongan;

            return (object) [
                'nik' => $user->nik,
                'nama' => $user->name,
                'unit' => $user->unitKerja ? $user->unitKerja->nama_unit : '-',
                'kehadiran' => $totalHadir,
                'menit_terlambat' => $totalMenitTerlambat,
                'potongan' => $potongan,
                'jam_lembur' => $totalJamLembur,
                'insentif_lembur' => $insentifLembur,
                'netto' => $netto,
            ];
        });

        return compact('users', 'bulan', 'tahun', 'startDate', 'endDate', 'ratePotongan', 'rateLembur');
    }

    private function buildAbsensiDetail($startDate, $endDate)
    {
        $hospitalLat = -0.9471;
        $hospitalLng = 100.3511;
        if (class_exists(\App\Models\PengaturanAplikasi::class)) {
            $pa = \App\Models\PengaturanAplikasi::first();
            if ($pa) {
                if ($pa->latitude  !== null) $hospitalLat = (float) $pa->latitude;
                if ($pa->longitude !== null) $hospitalLng = (float) $pa->longitude;
            }
        }

        return LogAbsensi::with(['roster.user'])
            ->whereHas('roster', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('tanggal_dinas', [$startDate, $endDate]);
            })
            ->get()
            ->map(function ($log) use ($hospitalLat, $hospitalLng) {
                $log->jarak_masuk  = $this->calculateDistance($log->latitude_masuk, $log->longitude_masuk, $hospitalLat, $hospitalLng);
                $log->jarak_pulang = $log->latitude_pulang
                    ? $this->calculateDistance($log->latitude_pulang, $log->longitude_pulang, $hospitalLat, $hospitalLng)
                    : null;
                return $log;
            });
    }

    private function buildAbsensiDetailBulanan($tahun, $bulan)
    {
        $start = Carbon::createFromDate($tahun, $bulan, 1)->startOfDay();
        $end   = $start->copy()->endOfMonth()->endOfDay();

        $hospitalLat = -0.9471;
        $hospitalLng = 100.3511;
        if (class_exists(\App\Models\PengaturanAplikasi::class)) {
            $pa = \App\Models\PengaturanAplikasi::first();
            if ($pa) {
                if ($pa->latitude  !== null) $hospitalLat = (float) $pa->latitude;
                if ($pa->longitude !== null) $hospitalLng = (float) $pa->longitude;
            }
        }

        return LogAbsensi::with(['roster.user'])
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('waktu_masuk',  [$start->toDateTimeString(), $end->toDateTimeString()])
                    ->orWhereBetween('waktu_pulang', [$start->toDateTimeString(), $end->toDateTimeString()]);
            })
            ->orderBy('waktu_masuk')
            ->get()
            ->map(function ($log) use ($hospitalLat, $hospitalLng) {
                $log->jarak_masuk  = $this->calculateDistance($log->latitude_masuk, $log->longitude_masuk, $hospitalLat, $hospitalLng);
                $log->jarak_pulang = $log->latitude_pulang
                    ? $this->calculateDistance($log->latitude_pulang, $log->longitude_pulang, $hospitalLat, $hospitalLng)
                    : null;
                return $log;
            });
    }

    public function index(Request $request)
    {
        $data = $this->generateDataLaporan($request);

        $absensiDetail = $this->buildAbsensiDetailBulanan((int) $data['tahun'], (int) $data['bulan']);

        return view('laporan.laporan', array_merge($data, ['absensiDetail' => $absensiDetail]));
    }

    public function exportExcel(Request $request)
    {
        $data = $this->generateDataLaporan($request);

        $absensiDetail = $this->buildAbsensiDetail($data['startDate'], $data['endDate']);

        $fileName = 'Rekap_Absensi_' . $data['bulan'] . '_' . $data['tahun'] . '.csv';

        $headers = [
            "Content-type"        => "text/csv; charset=UTF-8",
            "Content-Disposition" => "attachment; filename=$fileName",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $columns = ['Nama', 'Jam Masuk', 'Jam Keluar', 'Tanggal', 'Radius (m)', 'Latitude', 'Longitude'];

        $fmtJam   = fn($t) => $t ? Carbon::parse($t)->format('H:i') : '-';
        $fmtTgl   = fn($t) => $t ? Carbon::parse($t)->format('d/m/Y') : '-';
        $fmtCoord = fn($v) => ($v === null || $v === '') ? '-' : number_format((float)$v, 4, '.', '');

        $radiusPair = function ($m, $p) {
            $f = fn($v) => ($v === null || $v === '') ? null : number_format((float)$v, 0, ',', '.');
            $a = $f($m);
            $b = $f($p);
            if ($a !== null && $b !== null) return $a . ' / ' . $b;
            if ($a !== null) return $a;
            if ($b !== null) return $b;
            return '-';
        };

        $coordPair = function ($m, $p) use ($fmtCoord) {
            $a = $fmtCoord($m);
            $b = $fmtCoord($p);
            if ($a === '-' && $b === '-') return '-';
            return $a . ' / ' . $b;
        };

        $callback = function () use ($absensiDetail, $columns, $fmtJam, $fmtTgl, $radiusPair, $coordPair) {
            $file = fopen('php://output', 'w');

            fwrite($file, "\xEF\xBB\xBF");

            fputcsv($file, $columns); // Header

            foreach ($absensiDetail as $log) {
                fputcsv($file, [
                    $log->roster?->user?->name ?? '-',
                    $fmtJam($log->waktu_masuk),
                    $fmtJam($log->waktu_pulang),
                    $fmtTgl($log->roster?->tanggal_dinas ?? null),
                    $radiusPair($log->jarak_masuk, $log->jarak_pulang),
                    $coordPair($log->latitude_masuk, $log->latitude_pulang),
                    $coordPair($log->longitude_masuk, $log->longitude_pulang),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);

        return response()->stream($callback, 200, $headers);
    }

    public function exportPdf(Request $request)
    {
        $data = $this->generateDataLaporan($request);
        $absensiDetail = $this->buildAbsensiDetail($data['startDate'], $data['endDate']);

        return view('laporan.laporan-pdf', array_merge($data, ['absensiDetail' => $absensiDetail]));
    }

    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        if (!$lat1 || !$lon1) return null;
        $earthRadius = 6371000;
        $latDelta = deg2rad($lat2 - $lat1);
        $lonDelta = deg2rad($lon2 - $lon1);
        $a = sin($latDelta / 2) * sin($latDelta / 2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($lonDelta / 2) * sin($lonDelta / 2);
        return round($earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a)));
    }

    public function rekapLembur(Request $request)
    {
        $me   = $request->user();
        $role = $me->role;

        if (!in_array($role, ['superadmin', 'hrd', 'kepala_unit', 'penanggung_jawab'])) {
            abort(403, 'Anda tidak memiliki akses rekap lembur.');
        }

        $bulan        = (int) $request->query('bulan', now()->month);
        $tahun        = (int) $request->query('tahun', now()->year);
        $statusFilter = $request->query('status'); // opsional: Pending|Disetujui|Ditolak

        $q = \App\Models\LogLembur::with(['user.unitKerja']);

        if (in_array($role, ['kepala_unit', 'penanggung_jawab'])) {
            $myUnit = $me->unit_kerja_id;
            $q->whereHas('user', function ($u) use ($myUnit) {
                $u->where('unit_kerja_id', $myUnit);
            });
        }

        $q->whereYear('waktu_mulai_lembur', $tahun)
            ->whereMonth('waktu_mulai_lembur', $bulan);

        if (!empty($statusFilter)) {
            $q->where('status_validasi', $statusFilter);
        }

        $items = $q->orderByDesc('waktu_mulai_lembur')->get();

        $set  = \App\Models\PengaturanAplikasi::first();
        $sLat = $set ? (float) $set->latitude  : null;
        $sLng = $set ? (float) $set->longitude : null;

        $rows = [];
        $totalJamDisetujui = 0.0;
        $jmlPending = 0;

        foreach ($items as $l) {
            $lat = $l->latitude_masuk  ?? $l->latitude_keluar;
            $lng = $l->longitude_masuk ?? $l->longitude_keluar;
            $jarak = null;
            if ($lat !== null && $lng !== null && $sLat !== null && $sLng !== null) {
                $jarak = $this->haversine((float) $lat, (float) $lng, $sLat, $sLng);
            }

            if (($l->status_validasi ?? '') === 'Disetujui') $totalJamDisetujui += (float) ($l->total_jam_lembur ?? 0);
            if (($l->status_validasi ?? '') === 'Pending')   $jmlPending++;

            $rows[] = [
                'nama'       => $l->user->name ?? '-',
                'nik'        => $l->user->nik ?? '-',
                'unit'       => $l->user->unitKerja->nama_unit ?? '-',
                'jenis'      => $l->jenis_lembur ?? '-',
                'tanggal'    => $l->waktu_mulai_lembur ? \Carbon\Carbon::parse($l->waktu_mulai_lembur)->format('d M Y') : '-',
                'jam_masuk'  => $l->waktu_mulai_lembur ? \Carbon\Carbon::parse($l->waktu_mulai_lembur)->format('H:i') : '-',
                'jam_keluar' => $l->waktu_selesai_lembur ? \Carbon\Carbon::parse($l->waktu_selesai_lembur)->format('H:i') : '-',
                'durasi'     => $l->total_jam_lembur !== null ? number_format((float) $l->total_jam_lembur, 2, ',', '.') . ' jam' : '-',
                'jarak'      => $this->fmtJarak($jarak),
                'status'     => $l->status_validasi ?? '-',
                'alasan'     => $l->keterangan ?? '-',
            ];
        }

        $units = in_array($role, ['hrd', 'superadmin'])
            ? \App\Models\MasterUnitKerja::orderBy('nama_unit')->get()
            : collect();

        return view('laporan.rekap-lembur', [
            'rows'              => $rows,
            'bulan'             => $bulan,
            'tahun'             => $tahun,
            'statusFilter'      => $statusFilter,
            'role'              => $role,
            'units'             => $units,
            'totalPengajuan'    => count($rows),
            'totalJamDisetujui' => $totalJamDisetujui,
            'jmlPending'        => $jmlPending,
        ]);
    }

    private function haversine($lat1, $lon1, $lat2, $lon2)
    {
        $R    = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a    = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function fmtJarak($m)
    {
        if ($m === null) return '-';
        if ($m >= 1000) return number_format($m / 1000, 2, ',', '.') . ' km';
        return round($m) . ' m';
    }
}
