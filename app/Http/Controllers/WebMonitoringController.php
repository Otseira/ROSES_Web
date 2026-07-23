<?php

namespace App\Http\Controllers;

use App\Models\JadwalRoster;
use Carbon\Carbon;
use Illuminate\Http\Request;

class WebMonitoringController extends Controller
{
    public function index(Request $request)
    {
        // Ambil tanggal hari ini
        $hariIni = Carbon::today()->toDateString();
        
        // Tarik semua jadwal dinas hari ini beserta data pegawai, unit kerja, shift, dan log absensinya
        $rosters = JadwalRoster::with(['user.unitKerja', 'shift', 'logAbsensi'])
            ->where('tanggal_dinas', $hariIni)
            ->get();

        // Hitung statistik ringkas untuk visualisasi Dashboard
        $totalJadwal = $rosters->count();
        $hadir = $rosters->filter(function ($roster) {
            return $roster->logAbsensi && $roster->logAbsensi->waktu_masuk !== null;
        })->count();
        
        $terlambat = $rosters->filter(function ($roster) {
            return $roster->logAbsensi && $roster->logAbsensi->menit_terlambat > 0;
        })->count();
        
        $belumHadir = $totalJadwal - $hadir;

        return view('monitoring', compact('rosters', 'totalJadwal', 'hadir', 'terlambat', 'belumHadir', 'hariIni'));
    }
}