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
    /**
     * Fungsi Privat untuk mengumpulkan dan menghitung data laporan
     * (Agar tidak perlu menulis ulang kode untuk fitur Export)
     */
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

        // KUNCI UTAMA: Sembunyikan 'superadmin' dari daftar laporan
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

    /**
     * ✅ BARU: Membangun detail log absensi harian (dipakai index & PDF agar identik).
     * Menghitung jarak (radius) tiap absen dari titik RS.
     */
    private function buildAbsensiDetail($startDate, $endDate)
    {
        // Koordinat titik RS: ambil dari Pengaturan Aplikasi bila tersedia,
        // selain itu fallback ke nilai default (aman walau model belum dibuat).
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

    /**
     * Tampilan Utama Halaman Web Laporan
     */
    public function index(Request $request)
    {
        $data = $this->generateDataLaporan($request);
        $absensiDetail = $this->buildAbsensiDetail($data['startDate'], $data['endDate']); // ✅ pakai method bersama

        return view('laporan', array_merge($data, ['absensiDetail' => $absensiDetail]));
    }

    /**
     * Ekspor Data ke Format Excel (CSV) — TIDAK DIUBAH
     */
    /**
     * Ekspor Data ke Format Excel (CSV) — SEKARANG MENGIKUTI KOLOM WEB/PDF
     */
    public function exportExcel(Request $request)
    {
        $data = $this->generateDataLaporan($request);

        // ✅ Ambil log harian (sumber yang SAMA dengan web & PDF)
        $absensiDetail = $this->buildAbsensiDetail($data['startDate'], $data['endDate']);

        $fileName = 'Rekap_Absensi_' . $data['bulan'] . '_' . $data['tahun'] . '.csv';

        $headers = [
            "Content-type"        => "text/csv; charset=UTF-8",
            "Content-Disposition" => "attachment; filename=$fileName",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        // Header kolom = SAMA PERSIS dengan header tabel web & PDF
        $columns = ['Nama', 'Jam Masuk', 'Jam Keluar', 'Tanggal', 'Radius (m)', 'Latitude', 'Longitude'];

        // Helper format
        $fmtJam   = fn($t) => $t ? Carbon::parse($t)->format('H:i') : '-';
        $fmtTgl   = fn($t) => $t ? Carbon::parse($t)->format('d/m/Y') : '-';
        $fmtCoord = fn($v) => ($v === null || $v === '') ? '-' : number_format((float)$v, 4, '.', '');

        // Radius: gabung Masuk / Pulang dalam satu sel (konsisten dengan web)
        $radiusPair = function ($m, $p) {
            $f = fn($v) => ($v === null || $v === '') ? null : number_format((float)$v, 0, ',', '.');
            $a = $f($m);
            $b = $f($p);
            if ($a !== null && $b !== null) return $a . ' / ' . $b;
            if ($a !== null) return $a;
            if ($b !== null) return $b;
            return '-';
        };

        // Koordinat: gabung Masuk / Pulang dalam satu sel
        $coordPair = function ($m, $p) use ($fmtCoord) {
            $a = $fmtCoord($m);
            $b = $fmtCoord($p);
            if ($a === '-' && $b === '-') return '-';
            return $a . ' / ' . $b;
        };

        $callback = function () use ($absensiDetail, $columns, $fmtJam, $fmtTgl, $radiusPair, $coordPair) {
            $file = fopen('php://output', 'w');

            // BOM UTF-8 agar Excel membaca karakter Indonesia dengan benar
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

    /**
     * Ekspor Data ke PDF (via Print View)
     */
    public function exportPdf(Request $request)
    {
        $data = $this->generateDataLaporan($request);
        // ✅ PERBAIKAN: bangun & kirim 'absensiDetail' (sama persis dengan index)
        $absensiDetail = $this->buildAbsensiDetail($data['startDate'], $data['endDate']);

        return view('laporan-pdf', array_merge($data, ['absensiDetail' => $absensiDetail]));
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
}
