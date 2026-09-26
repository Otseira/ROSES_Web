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
     * ✅ ABSEN MASUK — wajib dalam radius (dinamis dari Pengaturan Sistem).
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

        // 1) Sesi masih aktif (belum absen pulang) → tolak
        $logAktif = LogAbsensi::where('user_id', $user->id)
            ->whereNull('waktu_pulang')
            ->where('waktu_masuk', '>=', $now->copy()->subHours(24))
            ->first();

        if ($logAktif) {
            return response()->json([
                'success' => false,
                'message' => 'Sesi dinas Anda masih aktif. Lakukan absen pulang terlebih dahulu.',
            ], 422);
        }

        // 2) Ambil semua sesi hari ini (1 & 2) + fallback shift malam kemarin
        $rostersHariIni = JadwalRoster::with('shift')
            ->where('user_id', $user->id)
            ->where('tanggal_dinas', $today)
            ->orderBy('sesi')
            ->get();

        if ($rostersHariIni->isEmpty()) {
            $malam = $this->cariRosterShiftMalamAktif($user->id, $now);
            if ($malam) $rostersHariIni = collect([$malam]);
        }

        // 3) Cari sesi TARGET: jendela waktunya memuat sekarang & belum dipakai absen
        $target = null;
        foreach ($rostersHariIni as $r) {
            $sudahDipakai = LogAbsensi::where('roster_id', $r->id)
                ->whereNotNull('waktu_masuk')
                ->exists();
            if ($sudahDipakai) continue;

            [, $winEnd] = self::jendelaSesi($r);

            // Sesi masih valid selama belum melewati jam pulang
            if ($now->lte($winEnd)) {
                $target = $r;
                break;
            }
        }

        // 4) Jika tidak ada target → semua sesi sudah selesai atau tidak ada jadwal
        if (!$target) {

            // b) Semua sesi hari ini sudah selesai → arahkan ke On-Call
            $logSelesaiHariIni = LogAbsensi::where('user_id', $user->id)
                ->whereDate('waktu_masuk', $today)
                ->whereNotNull('waktu_pulang')
                ->exists();

            if ($rostersHariIni->isNotEmpty() || $logSelesaiHariIni) {
                return response()->json([
                    'success' => false,
                    'message' => 'Absensi hari ini sudah selesai. Untuk tugas tambahan setelah pulang, gunakan menu On-Call.',
                ], 422);
            }
            // c) Tidak ada jadwal & belum ada sesi selesai hari ini → jalur "Tanpa Jadwal" (target tetap null)
        }

        // 5) Cek radius (seragam, dinamis)
        $cekRadius = $this->verifikasiRadius($request, 'Absen masuk');
        if ($cekRadius !== true) return $cekRadius;

        $fotoPath = $request->hasFile('foto')
            ? $request->file('foto')->store('absensi/masuk', 'public')
            : null;

        // 6) Simpan log — menempel ke roster SESI yang cocok
        $log = LogAbsensi::create([
            'user_id'          => $user->id,
            'roster_id'        => $target?->id,
            'waktu_masuk'      => $now,
            'latitude_masuk'   => $request->latitude,
            'longitude_masuk'  => $request->longitude,
            'foto_masuk'       => $fotoPath,
            'jenis_absen'      => 'dalam_radius',
            'menit_terlambat'  => 0,
            'status_kehadiran' => 'Tanpa Jadwal',
        ]);

        self::recalculateStatus($log);

        $namaSesi = $target ? ('Sesi ' . ($target->sesi ?? 1)) : 'Tanpa Jadwal';

        return response()->json([
            'success' => true,
            'message' => $target
                ? 'Absen masuk berhasil dicatat (' . $namaSesi . ').'
                : 'Absen masuk dicatat. Belum ada jadwal dinas — status akan menyesuaikan setelah jadwal dibuat.',
            'data' => [
                'waktu_masuk'     => $now->format('H:i'),
                'roster_ada'      => $target !== null,
                'sesi'            => $target?->sesi ?? null,
                'status'          => $log->status_kehadiran,
                'menit_terlambat' => $log->menit_terlambat,
            ],
        ]);
    }

    /**
     * ✅ ABSEN PULANG — juga wajib dalam radius (dinamis dari Pengaturan Sistem).
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

        // ✅ CEK RADIUS (selalu membaca nilai TERBARU dari Pengaturan Sistem)
        $cekRadius = $this->verifikasiRadius($request, 'Absen pulang');
        if ($cekRadius !== true) return $cekRadius;

        $fotoPath = $request->hasFile('foto')
            ? $request->file('foto')->store('absensi/pulang', 'public')
            : null;

        $logAktif->update([
            'waktu_pulang'     => $now,
            'latitude_pulang'  => $request->latitude,
            'longitude_pulang' => $request->longitude,
            'foto_pulang'      => $fotoPath,
        ]);

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
            ->orderBy('sesi')
            ->get()
            ->last();

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
     * ✅ Hitung ulang status berdasarkan JADWAL TERBARU (retroaktif).
     */
    public static function recalculateStatus(LogAbsensi $log): void
    {
        $roster = $log->roster_id
            ? JadwalRoster::with('shift')->find($log->roster_id)
            : null;

        if (!$roster || !$log->waktu_masuk) {
            $log->status_kehadiran = 'Tanpa Jadwal';
            $log->menit_terlambat = 0;

            if ($log->waktu_masuk && $log->waktu_pulang) {
                $log->durasi_kerja = $log->waktu_masuk->diffInMinutes($log->waktu_pulang);
            }
            $log->save();
            return;
        }

        // ✅ Ambil jam dari custom atau shift
        $jamMasuk = $roster->custom_jam_masuk ?? ($roster->shift ? (string) $roster->shift->jam_masuk : null);
        $toleransi = $roster->shift ? (int) ($roster->shift->toleransi_terlambat_menit ?? 5) : 5;

        if (!$jamMasuk) {
            $log->status_kehadiran = 'Tanpa Jadwal';
            $log->menit_terlambat = 0;
            $log->save();
            return;
        }

        // ✅ FIX: tanggal_dinas bisa berupa objek Carbon → format dulu ke Y-m-d
        $tanggalStr = Carbon::parse($roster->tanggal_dinas)->format('Y-m-d');
        $expected   = Carbon::parse($tanggalStr . ' ' . $jamMasuk);

        $selisih   = (int) floor($expected->diffInMinutes($log->waktu_masuk, false));

        $log->menit_terlambat = ($selisih > $toleransi) ? (int) $selisih : 0;

        $log->status_kehadiran = $log->menit_terlambat > 0
            ? 'Terlambat'
            : 'Tepat Waktu';

        if ($log->waktu_masuk && $log->waktu_pulang) {
            $log->durasi_kerja = $log->waktu_masuk->diffInMinutes($log->waktu_pulang);
        }

        $log->save();
    }

    /**
     * ✅ CEK RADIUS DINAMIS — selalu membaca nilai TERBARU dari Pengaturan Sistem.
     * Admin ubah angka radius → absensi berikutnya langsung memakai nilai baru
     * (tanpa deploy, tanpa clear cache).
     *
     * @return true|JsonResponse  true = lolos, JsonResponse = tolak 403
     */
    private function verifikasiRadius(Request $request, string $label = 'Absensi')
    {
        $pengaturan = PengaturanAplikasi::first();

        // Jika titik GPS kantor belum diatur, jangan mengunci karyawan
        if (!$pengaturan || !$pengaturan->latitude || !$pengaturan->longitude) {
            return true;
        }

        $jarak = $this->haversine(
            (float) $pengaturan->latitude,
            (float) $pengaturan->longitude,
            (float) $request->latitude,
            (float) $request->longitude
        );

        // ✅ BACA LIVE dari Pengaturan Sistem (dinamis)
        $maxRadius = $this->getMaxRadius($pengaturan);

        if ($jarak > $maxRadius) {
            return response()->json([
                'success' => false,
                'message' => $label . ' ditolak: posisi Anda ±' . round($jarak)
                    . ' meter dari kantor (maksimal ' . $maxRadius
                    . ' meter). Mendekatlah ke area rumah sakit, lalu coba lagi.',
            ], 403);
        }

        return true;
    }

    private function cariRosterShiftMalamAktif(int $userId, Carbon $now): ?JadwalRoster
    {
        $kemarin = $now->copy()->subDay()->toDateString();

        $roster = JadwalRoster::with('shift')
            ->where('user_id', $userId)
            ->where('tanggal_dinas', $kemarin)
            ->first();

        if (!$roster || !$roster->shift) return null;

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

    /**
     * ✅ Ambil nilai radius dari Pengaturan — tahan terhadap perbedaan nama kolom
     * (radius_absen / radius_meter / radius / apa pun yang mengandung kata "radius").
     */
    private function getMaxRadius($pengaturan): int
    {
        if (!$pengaturan) return 100;

        foreach ($pengaturan->getAttributes() as $key => $val) {
            if (str_contains($key, 'radius') && $val !== null && $val !== '') {
                return (int) $val;
            }
        }

        return 100; // fallback terakhir
    }

    /**
     * ✅ Jendela waktu sebuah sesi dinas: [mulai (− toleransi masuk awal), selesai].
     * Mendukung shift malam lintas tengah malam & shift custom.
     */
    public static function jendelaSesi(JadwalRoster $r): array
    {
        $jamMasuk  = $r->custom_jam_masuk  ?? ($r->shift ? (string) $r->shift->jam_masuk  : null);
        $jamPulang = $r->custom_jam_pulang ?? ($r->shift ? (string) $r->shift->jam_pulang : null);

        $tanggal = Carbon::parse($r->tanggal_dinas)->format('Y-m-d');
        $start   = Carbon::parse($tanggal . ' ' . $jamMasuk)->subMinutes(self::MASUK_CEPAT_MAKS_MENIT);
        $end     = Carbon::parse($tanggal . ' ' . $jamPulang);

        if ($jamPulang < $jamMasuk) {
            $end->addDay(); // shift malam lintas tengah malam
        }

        return [$start, $end];
    }
}
