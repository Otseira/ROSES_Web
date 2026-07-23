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
     * Tampilan Utama Halaman Web Laporan
     */
    public function index(Request $request)
{
    // Ambil data untuk payroll (fungsi yang sudah ada)
    $data = $this->generateDataLaporan($request);
    
    // Ambil Pengaturan koordinat
    $pengaturan = PengaturanPayroll::first(); // Asumsi koordinat di tabel payroll/pengaturan
    // Jika koordinat ada di PengaturanAplikasi, ganti dengan: PengaturanAplikasi::first();
    $hospitalLat = -0.9471; // Ganti sesuai koordinat RS Anda
    $hospitalLng = 100.3511;

    // Ambil data log absensi detail dengan relasi user
    $absensiDetail = LogAbsensi::with(['roster.user'])
        ->whereHas('roster', function($q) use ($data) {
            $q->whereBetween('tanggal_dinas', [$data['startDate'], $data['endDate']]);
        })
        ->get()
        ->map(function ($log) use ($hospitalLat, $hospitalLng) {
            $log->jarak_masuk = $this->calculateDistance($log->latitude_masuk, $log->longitude_masuk, $hospitalLat, $hospitalLng);
            $log->jarak_pulang = ($log->latitude_pulang) ? $this->calculateDistance($log->latitude_pulang, $log->longitude_pulang, $hospitalLat, $hospitalLng) : null;
            return $log;
        });

    return view('laporan', array_merge($data, ['absensiDetail' => $absensiDetail]));
}

private function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    if (!$lat1 || !$lon1) return null;
    $earthRadius = 6371000;
    $latDelta = deg2rad($lat2 - $lat1);
    $lonDelta = deg2rad($lon2 - $lon1);
    $a = sin($latDelta / 2) * sin($latDelta / 2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($lonDelta / 2) * sin($lonDelta / 2);
    return round($earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a)));
}

    /**
     * Ekspor Data ke Format Excel (CSV)
     */
    public function exportExcel(Request $request)
    {
        $data = $this->generateDataLaporan($request);
        $fileName = 'Rekap_Absensi_' . $data['bulan'] . '_' . $data['tahun'] . '.csv';

        $headers = [
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=$fileName",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        // Header Kolom di Excel
        $columns = ['NIK', 'Nama Pegawai', 'Unit Kerja', 'Hadir (Hari)', 'Terlambat (Menit)', 'Potongan (Rp)', 'Lembur (Jam)', 'Insentif Lembur (Rp)', 'Total Netto (Rp)'];

        $callback = function() use($data, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns); // Tulis Header

            foreach ($data['users'] as $user) {
                fputcsv($file, [
                    $user->nik,
                    $user->nama,
                    $user->unit,
                    $user->kehadiran,
                    $user->menit_terlambat,
                    $user->potongan,
                    $user->jam_lembur,
                    $user->insentif_lembur,
                    $user->netto
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Ekspor Data ke PDF (via Print View)
     */
    public function exportPdf(Request $request)
    {
        $data = $this->generateDataLaporan($request);
        return view('laporan-pdf', $data);
    }
}