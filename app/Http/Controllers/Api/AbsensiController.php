<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JadwalRoster;
use App\Models\LogAbsensi;
use App\Models\PengaturanAplikasi;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AbsensiController extends Controller
{
    /**
     * ABSEN MASUK
     * Aman untuk shift harian maupun shift malam (lewat tengah malam).
     */
    const MASUK_CEPAT_MAKS_MENIT = 120;
    public function clockIn(Request $request)
    {
        $request->validate([
            'latitude'  => 'required|numeric',
            'longitude' => 'required|numeric',
            'foto'      => 'required|image|mimes:jpeg,png,jpg|max:2048',
            'mode'      => 'nullable|in:normal,luar_jadwal',
        ]);

        $mode = $request->input('mode', 'normal');
        $user = $request->user();

        if ($user->role === 'superadmin') {
            return response()->json(['success' => false, 'message' => 'Akun Administrator tidak diizinkan melakukan absensi.'], 403);
        }

        $today = Carbon::today()->toDateString();
        $now   = Carbon::now();

        $roster = JadwalRoster::with('shift')
            ->where('user_id', $user->id)
            ->where('tanggal_dinas', $today)
            ->first();

        // FALLBACK SHIFT MALAM: cek shift kemarin yang lewat tengah malam
        if (!$roster) {
            $rosterKemarin = $this->cariRosterShiftMalamAktif($user->id, $now);
            if ($rosterKemarin) {
                $roster = $rosterKemarin;
                $today  = Carbon::parse($roster->tanggal_dinas)->toDateString();
            }
        }

        if (!$roster) {
            return response()->json(['success' => false, 'message' => 'Absen gagal. Anda tidak memiliki jadwal dinas yang terdaftar untuk hari ini.'], 422);
        }

        $existingLog = LogAbsensi::where('roster_id', $roster->id)->first();
        if ($existingLog && $existingLog->waktu_masuk !== null) {
            return response()->json(['success' => false, 'message' => 'Anda sudah melakukan absen masuk untuk shift ini.'], 422);
        }

        // ===== GEOFENCING =====
        $pengaturan  = PengaturanAplikasi::first();
        $hospitalLat = $pengaturan ? (float) $pengaturan->latitude : -0.9471;
        $hospitalLng = $pengaturan ? (float) $pengaturan->longitude : 100.3511;
        $maxRadius   = $pengaturan ? (int) $pengaturan->radius_meter : 50;

        $distance = $this->calculateDistance((float) $request->latitude, (float) $request->longitude, $hospitalLat, $hospitalLng);

        if ($distance > $maxRadius) {
            return response()->json(['success' => false, 'message' => 'Absen ditolak. Anda berada di luar radius rumah sakit (Jarak Anda: ' . round($distance) . ' meter).'], 403);
        }

        // ===== JENDELA SHIFT ROSTER =====
        $shift          = $roster->shift;
        $jamMasukShift  = Carbon::parse($today . ' ' . $shift->jam_masuk);
        $jamPulangShift = Carbon::parse($today . ' ' . $shift->jam_pulang);
        if ($jamPulangShift->lessThanOrEqualTo($jamMasukShift)) $jamPulangShift->addDay();

        // ✅ BATAS MASUK CEPAT: maksimal 2 jam sebelum jam shift (dulu 1 jam)
        $inWindow = $now->betweenIncluded(
            $jamMasukShift->copy()->subMinutes(self::MASUK_CEPAT_MAKS_MENIT),
            $jamPulangShift->copy()->addMinutes(120)
        );

        // ===== PENENTUAN JENIS & TERLAMBAT =====
        if ($mode === 'normal' && !$inWindow) {
            return response()->json([
                'success' => false,
                'code'    => 'OUTSIDE_WINDOW',
                'message' => 'Absen masuk hanya dapat dilakukan maksimal 2 jam sebelum jam shift dimulai. Jika terjadi perubahan/pergeseran jam dinas, silakan gunakan menu ABSENSI LUAR JADWAL.',
            ], 422);
        }

        if ($mode === 'luar_jadwal' && !$inWindow) {
            // Di luar jendela (terlalu cepat > 2 jam) tapi mode luar_jadwal → SAH, tidak telat
            $jenis          = 'luar_jadwal';
            $menitTerlambat = 0;
        } else {
            $jenis          = 'normal';
            $toleransi      = (int) $shift->toleransi_terlambat_menit;
            $menitSelisih   = intdiv(max(0, $now->getTimestamp() - $jamMasukShift->getTimestamp()), 60);
            $menitTerlambat = ($menitSelisih > $toleransi) ? $menitSelisih : 0;
        }

        // ===== SIMPAN =====
        $nik      = $user->nik ?? $user->id;
        $file     = $request->file('foto');
        $filename = 'in_' . $nik . '_' . time() . '.' . $file->extension();
        $path     = $file->storeAs('absensi_masuk', $filename, 'public');

        LogAbsensi::updateOrCreate(
            ['roster_id' => $roster->id],
            [
                'user_id'           => $user->id,
                'tanggal'           => $today,
                'jenis_absen'       => $jenis,
                'status_konfirmasi' => 'Normal',
                'waktu_masuk'       => $now,
                'menit_terlambat'   => $menitTerlambat,
                'foto_masuk'        => $path,
                'latitude_masuk'    => $request->latitude,
                'longitude_masuk'   => $request->longitude,
                'ip_address_masuk'  => $request->ip(),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => $jenis === 'luar_jadwal'
                ? 'Absensi LUAR JADWAL berhasil dicatat. Tetap SAH dan tidak dihitung terlambat.'
                : 'Absen masuk berhasil dicatat pada ' . $now->format('H:i:s'),
            'data' => [
                'waktu_masuk'      => $now->toDateTimeString(),
                'terlambat_menit'  => $menitTerlambat,
                'jenis_absen'      => $jenis,
                'status_kehadiran' => $menitTerlambat > 0 ? 'Terlambat' : 'Tepat Waktu',
            ],
        ], 200);
    }

    /**
     * ABSEN PULANG
     * FIX UTAMA: mendukung shift malam yang pulangnya lewat tengah malam / besok pagi.
     * Tidak lagi menebak tanggal roster, melainkan mencari log absensi yang SEDANG AKTIF.
     */
    public function clockOut(Request $request)
    {
        $request->validate([
            'latitude'  => 'required|numeric',
            'longitude' => 'required|numeric',
            'foto'      => 'required|image|mimes:jpeg,png,jpg|max:2048',
            'mode'      => 'nullable|in:normal,luar_jadwal',
        ]);

        $mode = $request->input('mode', 'normal');
        $user = $request->user();
        $now  = Carbon::now();

        // ===== CARI LOG ABSENSI YANG SEDANG AKTIF =====
        // Aktif = sudah absen masuk, belum absen pulang.
        // Cara ini 100% akurat untuk shift apa pun (pagi / siang / malam / 24 jam).
        $activeLog = LogAbsensi::with('roster.shift')
            ->whereHas('roster', fn($q) => $q->where('user_id', $user->id))
            ->whereNotNull('waktu_masuk')
            ->whereNull('waktu_pulang')
            ->latest('waktu_masuk')
            ->first();

        if (!$activeLog) {
            return response()->json(['success' => false, 'message' => 'Anda belum melakukan absen masuk yang aktif untuk shift ini.'], 422);
        }

        $roster = $activeLog->roster;

        // ===== GEOFENCING =====
        $pengaturan = PengaturanAplikasi::first();
        $distance = $this->calculateDistance(
            (float) $request->latitude,
            (float) $request->longitude,
            (float) $pengaturan->latitude,
            (float) $pengaturan->longitude
        );
        if ($distance > (int) $pengaturan->radius_meter) {
            return response()->json(['success' => false, 'message' => 'Absen ditolak. Anda berada di luar radius rumah sakit (Jarak Anda: ' . round($distance) . ' meter).'], 403);
        }

        // ===== JENDELA PULANG (berdasarkan TANGGAL DINAS roster, bukan hari ini) =====
        $shift          = $roster->shift;
        $tanggalDinas   = Carbon::parse($roster->tanggal_dinas)->toDateString();
        $jamMasukShift  = Carbon::parse($tanggalDinas . ' ' . $shift->jam_masuk);
        $jamPulangShift = Carbon::parse($tanggalDinas . ' ' . $shift->jam_pulang);

        // Jika shift overnight (jam pulang <= jam masuk), geser pulang ke besok
        if ($jamPulangShift->lessThanOrEqualTo($jamMasukShift)) {
            $jamPulangShift->addDay();
        }

        $outStart = $jamPulangShift->copy()->subMinutes(120);
        $outEnd   = $jamPulangShift->copy()->addMinutes(180);

        if ($mode === 'normal') {
            if ($now->lessThan($outStart)) {
                return response()->json(['success' => false, 'message' => 'Terlalu awal untuk absen pulang. Jendela pulang normal dimulai pukul ' . $outStart->format('H:i') . '.'], 422);
            }
            if ($now->greaterThan($outEnd)) {
                return response()->json(['success' => false, 'code' => 'OUTSIDE_WINDOW', 'message' => 'Anda berada di luar jendela jam pulang. Jika jam dinas bergeser, gunakan menu ABSENSI LUAR JADWAL.'], 422);
            }
        }

        // ===== SIMPAN PULANG (jenis_absen & terlambat TIDAK berubah) =====
        $nik      = $user->nik ?? $user->id;
        $file     = $request->file('foto');
        $filename = 'out_' . $nik . '_' . time() . '.' . $file->extension();
        $path     = $file->storeAs('absensi_pulang', $filename, 'public');

        $activeLog->update([
            'waktu_pulang'      => $now,
            'foto_pulang'       => $path,
            'latitude_pulang'   => $request->latitude,
            'longitude_pulang'  => $request->longitude,
            'ip_address_pulang' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => $mode === 'luar_jadwal'
                ? 'Absen pulang LUAR JADWAL berhasil dicatat.'
                : 'Absen pulang berhasil dicatat pada ' . $now->format('H:i:s'),
            'data' => ['waktu_pulang' => $now->toDateTimeString()],
        ], 200);
    }

    /**
     * INFO SHIFT HARI INI (untuk Mobile)
     * Sekarang juga mendeteksi shift malam kemarin yang masih berjalan.
     */
    public function infoShiftHariIni(Request $request)
    {
        $user  = $request->user();
        $now   = Carbon::now();
        $today = $now->toDateString();

        $roster = JadwalRoster::with('shift')
            ->where('user_id', $user->id)
            ->where('tanggal_dinas', $today)
            ->first();

        // FALLBACK: shift malam kemarin yang masih berjalan (lewat tengah malam)
        if (!$roster) {
            $roster = $this->cariRosterShiftMalamAktif($user->id, $now);
        }

        if (!$roster) {
            return response()->json(['success' => true, 'data' => null], 200);
        }

        $tanggalDinas = Carbon::parse($roster->tanggal_dinas)->toDateString();
        $jamMasuk     = Carbon::parse($tanggalDinas . ' ' . $roster->shift->jam_masuk);
        $jamPulang    = Carbon::parse($tanggalDinas . ' ' . $roster->shift->jam_pulang);

        if ($jamPulang->lessThanOrEqualTo($jamMasuk)) {
            $jamPulang->addDay();
        }

        $maxMenit     = intdiv($now->getTimestamp() - $jamPulang->getTimestamp(), 60);
        $menitTersisa = (int) $now->diffInMinutes($jamPulang, false);

        return response()->json([
            'success' => true,
            'data'    => [
                'tanggal_dinas' => $tanggalDinas,
                'jam_masuk'     => $roster->shift->jam_masuk,
                'jam_pulang'    => $roster->shift->jam_pulang,
                'max_menit'     => max(0, $maxMenit),          // key lama (tetap, utk kompatibilitas Mobile)
                'menit_tersisa' => $menitTersisa,              // key baru: sisa menit s/d jam pulang
                'status_shift'  => $menitTersisa > 0 ? 'sedang_aktif' : 'selesai',
            ],
        ], 200);
    }

    /**
     * HELPER: Mencari roster shift malam KEMARIN yang masih aktif sekarang.
     * Hanya berlaku untuk shift yang melewati tengah malam (jam pulang < jam masuk)
     * dan waktu sekarang masih di dalam jendela kerjanya.
     */
    private function cariRosterShiftMalamAktif(int $userId, Carbon $now): ?JadwalRoster
    {
        $yesterday = Carbon::yesterday()->toDateString();

        $roster = JadwalRoster::with('shift')
            ->where('user_id', $userId)
            ->where('tanggal_dinas', $yesterday)
            ->first();

        if (!$roster || !$roster->shift) {
            return null;
        }

        $jamMasuk  = Carbon::parse($yesterday . ' ' . $roster->shift->jam_masuk);
        $jamPulang = Carbon::parse($yesterday . ' ' . $roster->shift->jam_pulang);

        // Hanya proses shift overnight (lewat tengah malam)
        if ($jamPulang->greaterThan($jamMasuk)) {
            return null;
        }
        $jamPulang->addDay();

        // ✅ Batas masuk cepat yang sama: maksimal 2 jam sebelum jam shift
        if ($now->betweenIncluded($jamMasuk->copy()->subMinutes(self::MASUK_CEPAT_MAKS_MENIT), $jamPulang->copy()->addMinutes(120))) {
            return $roster;
        }

        return null;
    }

    /**
     * Hitung jarak (meter) antara dua titik koordinat (Haversine)
     */
    private function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000; // meter

        $latDelta = deg2rad($lat2 - $lat1);
        $lonDelta = deg2rad($lon2 - $lon1);

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($lonDelta / 2) * sin($lonDelta / 2);

        $c = 2 * atan2(sqrt($a), sqrt($a * -1 + 1)); // atan2(sqrt(a), sqrt(1-a))

        return $earthRadius * $c;
    }
}
