<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JadwalRoster;
use App\Models\LogAbsensi;
use App\Models\PengaturanAplikasi;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AbsensiController extends Controller
{
    public const MASUK_CEPAT_MAKS_MENIT = 120;

    /**
     * ✅ ABSEN MASUK — selalu bisa, dengan atau tanpa jadwal dinas.
     */
    public function clockIn(Request $request)
    {
        $request->validate([
            'latitude'  => 'required|numeric',
            'longitude' => 'required|numeric',
            'foto'      => 'nullable|image|max:3072',
        ]);

        $user  = $request->user();
        $now   = Carbon::now();
        $today = $now->toDateString();

        // Cegah double absen (sudah masuk, belum pulang)
        $logAktif = LogAbsensi::where('user_id', $user->id)
            ->whereNull('waktu_pulang')
            ->where('waktu_masuk', '>=', $now->copy()->subHours(24))
            ->first();

        if ($logAktif) {
            return response()->json([
                'success' => false,
                'message' => 'Anda sudah absen masuk dan belum absen pulang.',
            ], 422);
        }

        // Cari roster hari ini (opsional — boleh tidak ada)
        $roster = JadwalRoster::with('shift')
            ->where('user_id', $user->id)
            ->where('tanggal_dinas', $today)
            ->first();

        // Fallback: shift malam kemarin yang masih berjalan
        if (!$roster) {
            $roster = $this->cariRosterShiftMalamAktif($user->id, $now);
        }

        // Geofencing
        $pengaturan  = PengaturanAplikasi::first();
        $dalamRadius = false;
        if ($pengaturan && $pengaturan->latitude && $pengaturan->longitude) {
            $jarak = $this->haversine(
                (float) $pengaturan->latitude,
                (float) $pengaturan->longitude,
                (float) $request->latitude,
                (float) $request->longitude
            );
            $dalamRadius = $jarak <= ($pengaturan->radius_absen ?? 100);
        }

        $fotoPath = $request->hasFile('foto')
            ? $request->file('foto')->store('absensi/masuk', 'public')
            : null;

        // ✅ Simpan log — roster_id boleh null
        $log = LogAbsensi::create([
            'user_id'          => $user->id,
            'roster_id'        => $roster?->id,
            'waktu_masuk'      => $now,
            'latitude_masuk'   => $request->latitude,
            'longitude_masuk'  => $request->longitude,
            'foto_masuk'       => $fotoPath,
            'jenis_absen'      => $dalamRadius ? 'dalam_radius' : 'luar_jadwal',
            'menit_terlambat'  => 0,
            'status_kehadiran' => 'Tanpa Jadwal',
        ]);

        // ✅ Status otomatis: ikut jadwal jika ada, "Tanpa Jadwal" jika tidak
        self::recalculateStatus($log);

        return response()->json([
            'success' => true,
            'message' => $roster
                ? 'Absen masuk berhasil dicatat.'
                : 'Absen masuk dicatat. Belum ada jadwal dinas — status akan menyesuaikan setelah jadwal dibuat.',
            'data' => [
                'waktu_masuk'     => $now->format('H:i'),
                'roster_ada'      => $roster !== null,
                'status'          => $log->status_kehadiran,
                'menit_terlambat' => $log->menit_terlambat,
            ],
        ]);
    }

    /**
     * ABSEN PULANG — cari log aktif (mendukung shift malam lewat tengah malam).
     */
    public function clockOut(Request $request)
    {
        $request->validate([
            'latitude'  => 'required|numeric',
            'longitude' => 'required|numeric',
            'foto'      => 'nullable|image|max:3072',
        ]);

        $user = $request->user();
        $now  = Carbon::now();

        $logAktif = LogAbsensi::where('user_id', $user->id)
            ->whereNull('waktu_pulang')
            ->where('waktu_masuk', '>=', $now->copy()->subHours(24))
            ->first();

        if (!$logAktif) {
            return response()->json([
                'success' => false,
                'message' => 'Anda belum melakukan absen masuk.',
            ], 422);
        }

        $fotoPath = $request->hasFile('foto')
            ? $request->file('foto')->store('absensi/pulang', 'public')
            : null;

        $logAktif->update([
            'waktu_pulang'     => $now,
            'latitude_pulang'  => $request->latitude,
            'longitude_pulang' => $request->longitude,
            'foto_pulang'      => $fotoPath,
        ]);

        // Hitung ulang durasi + status (jaga-jaga roster berubah saat shift berjalan)
        self::recalculateStatus($logAktif);

        return response()->json([
            'success' => true,
            'message' => 'Absen pulang berhasil dicatat.',
            'data'    => ['waktu_pulang' => $now->format('H:i')],
        ]);
    }

    public function infoShiftHariIni(Request $request)
    {
        $user  = $request->user();
        $now   = Carbon::now();
        $today = $now->toDateString();

        $roster = JadwalRoster::with('shift')
            ->where('user_id', $user->id)
            ->where('tanggal_dinas', $today)
            ->first();

        if (!$roster) {
            $roster = $this->cariRosterShiftMalamAktif($user->id, $now);
        }

        if (!$roster || !$roster->shift) {
            return response()->json([
                'success' => true,
                'data'    => [
                    'roster_ada' => false,
                    'pesan'      => 'Anda belum memiliki jadwal dinas. Absensi tetap bisa dilakukan.',
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'roster_ada'    => true,
                'nama_shift'    => $roster->shift->nama_shift,
                'jam_masuk'     => substr((string) $roster->shift->jam_masuk, 0, 5),
                'jam_pulang'    => substr((string) $roster->shift->jam_pulang, 0, 5),
                'tanggal_dinas' => $roster->tanggal_dinas,
            ],
        ]);
    }

    /**
     * ✅ INTI LOGIKA BARU: hitung ulang status berdasarkan JADWAL TERBARU.
     * Dipanggil saat absen, saat pulang, dan saat roster dibuat/diubah/dihapus.
     */
    public static function recalculateStatus(LogAbsensi $log): void
    {
        $roster = $log->roster_id
            ? JadwalRoster::with('shift')->find($log->roster_id)
            : null;

        // Tanpa roster → Tanpa Jadwal (atau Luar Jadwal jika di luar radius)
        if (!$roster || !$roster->shift || !$log->waktu_masuk) {
            $log->status_kehadiran = ($log->jenis_absen === 'luar_jadwal')
                ? 'Luar Jadwal'
                : 'Tanpa Jadwal';
            $log->menit_terlambat = 0;

            if ($log->waktu_masuk && $log->waktu_pulang) {
                $log->durasi_kerja = $log->waktu_masuk->diffInMinutes($log->waktu_pulang);
            }
            $log->save();
            return;
        }

        // Ada roster → bandingkan dengan jam masuk shift (jadwal TERBARU)
        $shift    = $roster->shift;
        $expected = Carbon::parse($roster->tanggal_dinas . ' ' . $shift->jam_masuk);

        $selisih    = $expected->diffInMinutes($log->waktu_masuk, false); // positif = terlambat
        $toleransi  = (int) ($shift->toleransi_terlambat_menit ?? 5);

        $log->menit_terlambat = ($selisih > $toleransi) ? (int) $selisih : 0;

        $log->status_kehadiran = ($log->jenis_absen === 'luar_jadwal')
            ? 'Luar Jadwal'
            : ($log->menit_terlambat > 0 ? 'Terlambat' : 'Tepat Waktu');

        if ($log->waktu_masuk && $log->waktu_pulang) {
            $log->durasi_kerja = $log->waktu_masuk->diffInMinutes($log->waktu_pulang);
        }

        $log->save();
    }

    /** Shift malam kemarin yang masih berjalan (untuk absen lewat tengah malam). */
    private function cariRosterShiftMalamAktif(int $userId, Carbon $now): ?JadwalRoster
    {
        $kemarin = $now->copy()->subDay()->toDateString();

        $roster = JadwalRoster::with('shift')
            ->where('user_id', $userId)
            ->where('tanggal_dinas', $kemarin)
            ->first();

        if (!$roster || !$roster->shift) return null;

        // Overnight: jam_pulang < jam_masuk (mis. 20:00 → 07:00)
        return ($roster->shift->jam_pulang < $roster->shift->jam_masuk) ? $roster : null;
    }

    private function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $R    = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a    = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
