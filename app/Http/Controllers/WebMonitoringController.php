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
        
        $userLogin = $request->user();
        
        // Tarik jadwal dinas hari ini dengan pembatasan hak akses unit kerja
        $query = JadwalRoster::select(['id', 'user_id', 'shift_id', 'tanggal_dinas'])
            ->with(['logAbsensi:id,roster_id,waktu_masuk,menit_terlambat'])
            ->where('tanggal_dinas', $hariIni);

        if (!$userLogin->hasGlobalAccess()) {
            $managedUnits = $userLogin->managesUnits()->pluck('master_unit_kerja_id')->toArray();
            if ($userLogin->unit_kerja_id && !in_array($userLogin->unit_kerja_id, $managedUnits)) {
                $managedUnits[] = $userLogin->unit_kerja_id;
            }
            if (empty($managedUnits)) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereHas('user', function ($q) use ($managedUnits) {
                    $q->whereIn('unit_kerja_id', $managedUnits);
                });
            }
        }

        $rosters = $query->get();

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