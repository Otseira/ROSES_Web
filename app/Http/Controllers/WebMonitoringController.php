<?php

namespace App\Http\Controllers;

use App\Models\JadwalRoster;
use App\Models\LogAbsensi;
use App\Models\LogLembur;
use App\Models\PengambilanLembur;
use App\Models\MasterUnitKerja;
use App\Models\MasterShift;
use App\Models\PengaturanAplikasi;
use Carbon\Carbon;
use Illuminate\Http\Request;

class WebMonitoringController extends Controller
{
    public function index(Request $request)
    {
        $userLogin = $request->user();

        // 1. Tanggal Pemantauan (Default: Hari ini)
        $tanggalPilihan = $request->input('tanggal', Carbon::today()->toDateString());
        $isHariIni = ($tanggalPilihan === Carbon::today()->toDateString());

        // 2. Filter input
        $unitFilter   = $request->input('unit_id');
        $shiftFilter  = $request->input('shift_id');
        $statusFilter = $request->input('status');
        $searchQuery  = trim((string) $request->input('q'));

        // 3. Hak Akses Multi-Unit (Manager/Kepala Unit/PJ)
        $isGlobal = $userLogin->hasGlobalAccess();
        $managedUnits = [];
        if (!$isGlobal) {
            $managedUnits = $userLogin->managesUnits()->pluck('master_unit_kerja_id')->toArray();
            if ($userLogin->unit_kerja_id && !in_array($userLogin->unit_kerja_id, $managedUnits)) {
                $managedUnits[] = $userLogin->unit_kerja_id;
            }
        }

        // 4. Dropdown Units & Shifts
        $unitsQuery = MasterUnitKerja::orderBy('nama_unit', 'asc');
        if (!$isGlobal) {
            $unitsQuery->whereIn('id', $managedUnits);
        }
        $units = $unitsQuery->get();
        $shifts = MasterShift::orderBy('nama_shift', 'asc')->get();

        // 5. Radius & Koordinat RS
        $pengaturan = PengaturanAplikasi::select(['latitude', 'longitude', 'nama_instansi'])->first();
        $latRS = $pengaturan ? (float) $pengaturan->latitude : 0;
        $lngRS = $pengaturan ? (float) $pengaturan->longitude : 0;
        $namaInstansi = $pengaturan->nama_instansi ?? 'Rumah Sakit';

        // 6. Tarik Data Roster Jadwal Dinas pada Tanggal Tersebut
        $rosterQuery = JadwalRoster::query()
            ->with([
                'user:id,name,nik,unit_kerja_id,foto_profil',
                'user.unitKerja:id,nama_unit,jenis_lembur',
                'shift:id,nama_shift,jam_masuk,jam_pulang,toleransi_terlambat_menit',
                'logAbsensi' => function ($q) {
                    $q->select([
                        'id', 'user_id', 'roster_id', 'tanggal_dinas',
                        'waktu_masuk', 'waktu_pulang', 'menit_terlambat',
                        'status_kehadiran', 'foto_masuk', 'foto_pulang',
                        'latitude_masuk', 'longitude_masuk', 'latitude_pulang', 'longitude_pulang',
                        'durasi_kerja', 'jenis_absen'
                    ]);
                }
            ])
            ->whereDate('tanggal_dinas', $tanggalPilihan);

        if (!$isGlobal) {
            if (empty($managedUnits)) {
                $rosterQuery->whereRaw('1 = 0');
            } else {
                $rosterQuery->whereHas('user', fn($q) => $q->whereIn('unit_kerja_id', $managedUnits));
            }
        }

        if (!empty($unitFilter)) {
            $rosterQuery->whereHas('user', fn($q) => $q->where('unit_kerja_id', $unitFilter));
        }

        if (!empty($shiftFilter)) {
            $rosterQuery->where('shift_id', $shiftFilter);
        }

        if (!empty($searchQuery)) {
            $rosterQuery->whereHas('user', function ($q) use ($searchQuery) {
                $q->where('name', 'like', "%{$searchQuery}%")
                  ->orWhere('nik', 'like', "%{$searchQuery}%");
            });
        }

        $allRosters = $rosterQuery->get();

        // 7. Tarik Data Dispensasi Pengambilan Jam (Kompensasi) Hari Ini
        $pengambilanHariIni = PengambilanLembur::whereDate('tanggal_dinas', $tanggalPilihan)
            ->where('status_validasi', 'Disetujui')
            ->get()
            ->keyBy('user_id');

        // 8. Tarik Data Sesi On-Call Aktif Hari Ini
        $onCallQuery = LogLembur::with(['user.unitKerja'])
            ->where('jenis_lembur', 'On-Call')
            ->whereDate('waktu_mulai_lembur', $tanggalPilihan);

        if (!$isGlobal) {
            $onCallQuery->whereHas('user', fn($q) => $q->whereIn('unit_kerja_id', $managedUnits));
        }
        $onCallHariIni = $onCallQuery->get();
        $onCallAktifList = $onCallHariIni->whereNull('waktu_selesai_lembur');

        // 9. Pemrosesan Data & Status Real-time Tiap Pegawai
        $rows = [];
        $totalJadwal = $allRosters->count();
        $hadirCount = 0;
        $tepatCount = 0;
        $telatCount = 0;
        $sedangDinasCount = 0;
        $sudahPulangCount = 0;
        $belumHadirCount = 0;
        $gantiJamCount = 0;

        foreach ($allRosters as $r) {
            $user  = $r->user;
            $shift = $r->shift;
            $log   = $r->logAbsensi;

            // Jika logAbsensi belum terkait via roster_id, coba cek via user_id & tanggal
            if (!$log && $user) {
                $log = LogAbsensi::where('user_id', $user->id)
                    ->whereDate('waktu_masuk', $tanggalPilihan)
                    ->first();
            }

            $waktuMasuk  = $log?->waktu_masuk ? Carbon::parse($log->waktu_masuk) : null;
            $waktuPulang = $log?->waktu_pulang ? Carbon::parse($log->waktu_pulang) : null;

            // Status Presensi
            $isHadir       = ($waktuMasuk !== null);
            $isSedangDinas = ($isHadir && $waktuPulang === null);
            $isSudahPulang = ($isHadir && $waktuPulang !== null);
            $isTerlambat   = ($log && (int) $log->menit_terlambat > 0);

            if ($isHadir) {
                $hadirCount++;
                if ($isSedangDinas) $sedangDinasCount++;
                if ($isSudahPulang) $sudahPulangCount++;
                if ($isTerlambat) {
                    $telatCount++;
                } else {
                    $tepatCount++;
                }
            } else {
                $belumHadirCount++;
            }

            // Info Dispensasi Ganti Jam
            $gantiJam = $user ? $pengambilanHariIni->get($user->id) : null;
            if ($gantiJam) {
                $gantiJamCount++;
            }

            // Jarak Masuk dari RS
            $jarakMasukTeks = '-';
            if ($log && is_numeric($log->latitude_masuk) && is_numeric($log->longitude_masuk) && $latRS != 0) {
                $jarakM = round($this->calculateDistance($latRS, $lngRS, (float) $log->latitude_masuk, (float) $log->longitude_masuk));
                $jarakMasukTeks = $jarakM < 1000 ? "{$jarakM} m" : round($jarakM / 1000, 1) . ' km';
            }

            // Durasi Kerja Real-time
            $durasiKerjaTeks = '-';
            if ($waktuMasuk) {
                $akhir = $waktuPulang ?? Carbon::now();
                $durasiMenit = max(0, $waktuMasuk->diffInMinutes($akhir));
                $dj = intdiv($durasiMenit, 60);
                $dm = $durasiMenit % 60;
                $durasiKerjaTeks = ($dj > 0 ? "{$dj}j " : '') . "{$dm}m";
            }

            // Foto URLs
            $fotoProfilUrl = $user?->foto_profil ? url('storage/' . $user->foto_profil) : null;
            $fotoMasukUrl  = $log?->foto_masuk ? url('storage/' . $log->foto_masuk) : null;
            $fotoPulangUrl = $log?->foto_pulang ? url('storage/' . $log->foto_pulang) : null;

            // Jam Jadwal
            $jamMasukJadwal  = $r->custom_jam_masuk ?? ($shift ? substr((string) $shift->jam_masuk, 0, 5) : '-');
            $jamPulangJadwal = $r->custom_jam_pulang ?? ($shift ? substr((string) $shift->jam_pulang, 0, 5) : '-');

            $rowItem = [
                'roster_id'          => $r->id,
                'user_id'            => $user?->id,
                'nama'               => $user?->name ?? 'Tanpa Nama',
                'nik'                => $user?->nik ?? '-',
                'unit'               => $user?->unitKerja?->nama_unit ?? 'Tanpa Unit',
                'jenis_lembur_unit'  => $user?->unitKerja?->jenis_lembur ?? 'dibayar',
                'foto_profil'        => $fotoProfilUrl,
                'shift_nama'         => $r->custom_nama_shift ?? ($shift?->nama_shift ?? 'Shift Kustom'),
                'jam_masuk_jadwal'   => $jamMasukJadwal,
                'jam_pulang_jadwal'  => $jamPulangJadwal,
                'waktu_masuk_fmt'    => $waktuMasuk?->format('H:i') ?? '—',
                'waktu_pulang_fmt'   => $waktuPulang?->format('H:i') ?? ($isSedangDinas ? 'Sedang Bertugas' : '—'),
                'menit_terlambat'    => (int) ($log?->menit_terlambat ?? 0),
                'is_hadir'           => $isHadir,
                'is_sedang_dinas'    => $isSedangDinas,
                'is_sudah_pulang'    => $isSudahPulang,
                'is_terlambat'       => $isTerlambat,
                'jarak_masuk'        => $jarakMasukTeks,
                'durasi_kerja'       => $durasiKerjaTeks,
                'foto_masuk_url'     => $fotoMasukUrl,
                'foto_pulang_url'    => $fotoPulangUrl,
                'lat_masuk'          => $log?->latitude_masuk,
                'lng_masuk'          => $log?->longitude_masuk,
                'ganti_jam'          => $gantiJam ? [
                    'tipe'         => $gantiJam->tipe_pengambilan,
                    'tipe_label'   => $gantiJam->tipe_pengambilan === 'masuk_diperlambat' ? 'Masuk Lambat' : 'Pulang Cepat',
                    'durasi_menit' => $gantiJam->durasi_menit,
                    'keterangan'   => $gantiJam->keterangan,
                ] : null,
            ];

            // Filter status jika dipilih di form
            if (!empty($statusFilter)) {
                if ($statusFilter === 'tepat_waktu' && (!$isHadir || $isTerlambat)) continue;
                if ($statusFilter === 'terlambat' && !$isTerlambat) continue;
                if ($statusFilter === 'sedang_dinas' && !$isSedangDinas) continue;
                if ($statusFilter === 'sudah_pulang' && !$isSudahPulang) continue;
                if ($statusFilter === 'belum_hadir' && $isHadir) continue;
                if ($statusFilter === 'ganti_jam' && !$gantiJam) continue;
            }

            $rows[] = $rowItem;
        }

        return view('monitoring', compact(
            'tanggalPilihan',
            'isHariIni',
            'units',
            'shifts',
            'unitFilter',
            'shiftFilter',
            'statusFilter',
            'searchQuery',
            'totalJadwal',
            'hadirCount',
            'tepatCount',
            'telatCount',
            'sedangDinasCount',
            'sudahPulangCount',
            'belumHadirCount',
            'gantiJamCount',
            'onCallAktifList',
            'rows',
            'namaInstansi'
        ));
    }

    private function calculateDistance($lat1, $lon1, $lat2, $lon2): float
    {
        $R = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
