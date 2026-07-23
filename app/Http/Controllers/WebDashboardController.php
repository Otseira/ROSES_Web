<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\LogAbsensi;
use App\Models\JadwalRoster; // Pastikan nama model sesuai dengan yang Anda gunakan
use Carbon\Carbon;

class WebDashboardController extends Controller
{
    public function index(Request $request)
    {
        $userLogin = $request->user();
        $hariIni = Carbon::today()->toDateString();
        
        // Cek wewenang
        $isGlobal = $userLogin->role === 'superadmin';

        // 1. Ambil query roster jadwal hari ini
        $queryJadwal = JadwalRoster::where('tanggal_dinas', $hariIni);
        
        // Jika bukan superadmin, kunci jadwal hanya untuk unit kerja user yang login
        if (!$isGlobal) {
            $queryJadwal->whereHas('user', function($q) use ($userLogin) {
                $q->where('unit_kerja_id', $userLogin->unit_kerja_id);
            });
        }

        // 2. Kalkulasi Data Statistik
        $rosterHariIni = $queryJadwal->get();
        $totalJadwal = $rosterHariIni->count();
        $rosterIds = $rosterHariIni->pluck('id');

        // Tarik log absensi yang berkaitan dengan roster hari ini
        $logsAbsen = LogAbsensi::whereIn('roster_id', $rosterIds)->get();

        $hadir = $logsAbsen->whereNotNull('waktu_masuk')->count();
        $terlambat = $logsAbsen->where('menit_terlambat', '>', 0)->count();
        $belumHadir = $totalJadwal - $hadir;

        // MENGIRIM VARIABEL KE VIEW DASHBOARD
        return view('dashboard', compact('totalJadwal', 'hadir', 'terlambat', 'belumHadir', 'hariIni'));
    }
}