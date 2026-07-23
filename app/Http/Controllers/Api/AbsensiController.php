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
     * Logika Absen Masuk (Clock-In) Pegawai
     */
    public function clockIn(Request $request)
    {
        $request->validate([
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'foto' => 'required|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $user = $request->user();

        if ($user->role === 'superadmin') {
            return response()->json([
                'success' => false,
                'message' => 'Akun Administrator tidak terikat jadwal dinas dan tidak diizinkan melakukan absensi.'
            ], 403);
        }

        $today = Carbon::today()->toDateString();
        $now = Carbon::now();

        // Menggunakan eager loading untuk shift
        $roster = JadwalRoster::with('shift')
            ->where('user_id', $user->id)
            ->where('tanggal_dinas', $today)
            ->first();

        if (!$roster) {
            return response()->json([
                'success' => false,
                'message' => 'Absen gagal. Anda tidak memiliki jadwal dinas yang terdaftar untuk hari ini.',
            ], 422);
        }

        $existingLog = LogAbsensi::where('roster_id', $roster->id)->first();
        if ($existingLog && $existingLog->waktu_masuk !== null) {
            return response()->json([
                'success' => false,
                'message' => 'Anda sudah melakukan absen masuk untuk shift ini.',
            ], 422);
        }

        // Ambil pengaturan GPS dari database
        $pengaturan = PengaturanAplikasi::first();
        $hospitalLat = $pengaturan ? (float) $pengaturan->latitude : -0.9471;
        $hospitalLng = $pengaturan ? (float) $pengaturan->longitude : 100.3511;
        $maxRadius = $pengaturan ? (int) $pengaturan->radius_meter : 50;

        $distance = $this->calculateDistance(
            (float) $request->latitude,
            (float) $request->longitude,
            $hospitalLat,
            $hospitalLng
        );

        if ($distance > $maxRadius) {
            return response()->json([
                'success' => false,
                'message' => 'Absen ditolak. Anda berada di luar radius rumah sakit (Jarak Anda: ' . round($distance) . ' meter).',
            ], 403);
        }

        $shift = $roster->shift;
        $jamMasukShift = Carbon::parse($today . ' ' . $shift->jam_masuk);
        $batasToleransi = $jamMasukShift->copy()->addMinutes($shift->toleransi_terlambat_menit);

        $menitTerlambat = 0;
        if ($now->greaterThan($batasToleransi)) {
            // ✅ FIX #7: Urutan diffInMinutes yang benar (dari jam shift ke waktu sekarang)
            // Carbon 3.x: $start->diffInMinutes($end) = positif jika end > start
            $menitTerlambat = $jamMasukShift->diffInMinutes($now);
        }

        // ✅ FIX #8: Handle jika NIK null (fallback ke ID)
        $nik = $user->nik ?? $user->id;
        $file = $request->file('foto');
        $filename = 'in_' . $nik . '_' . time() . '.' . $file->extension();
        $path = $file->storeAs('absensi_masuk', $filename, 'public');

        LogAbsensi::updateOrCreate(
            ['roster_id' => $roster->id],
            [
                'waktu_masuk' => $now,
                'menit_terlambat' => $menitTerlambat,
                'foto_masuk' => $path,
                'latitude_masuk' => $request->latitude,
                'longitude_masuk' => $request->longitude,
                'ip_address_masuk' => $request->ip(),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Absen masuk berhasil dicatat pada ' . $now->format('H:i:s'),
            'data' => [
                'waktu_masuk' => $now->toDateTimeString(),
                'terlambat_menit' => $menitTerlambat,
                'status_kehadiran' => $menitTerlambat > 0 ? 'Terlambat' : 'Tepat Waktu',
            ]
        ], 200);
    }

    /**
     * Logika Absen Pulang (Clock-Out) Karyawan
     */
    public function clockOut(Request $request)
    {
        $request->validate([
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'foto' => 'required|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $user = $request->user();

        if ($user->role === 'superadmin') {
            return response()->json([
                'success' => false,
                'message' => 'Akun Administrator tidak diizinkan menggunakan aplikasi absensi.',
            ], 403);
        }

        $now = Carbon::now();

        // OPTIMASI: Eager loading relasi roster dan shift langsung di awal kueri
        $logAbsen = LogAbsensi::whereHas('roster', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })
            ->with(['roster.shift'])
            ->whereNotNull('waktu_masuk')
            ->whereNull('waktu_pulang')
            ->latest()
            ->first();

        if (!$logAbsen) {
            return response()->json([
                'success' => false,
                'message' => 'Absen pulang gagal. Anda belum melakukan absen masuk atau sudah melakukan absen pulang sebelumnya.',
            ], 422);
        }

        // Ambil data roster dan shift langsung dari relasi yang sudah di-load
        $roster = $logAbsen->roster;
        $shift = $roster->shift;

        $pengaturan = PengaturanAplikasi::first();
        $hospitalLat = $pengaturan ? (float) $pengaturan->latitude : -0.9471;
        $hospitalLng = $pengaturan ? (float) $pengaturan->longitude : 100.3511;
        $maxRadius = $pengaturan ? (int) $pengaturan->radius_meter : 50;

        $distance = $this->calculateDistance(
            (float) $request->latitude,
            (float) $request->longitude,
            $hospitalLat,
            $hospitalLng
        );

        if ($distance > $maxRadius) {
            return response()->json([
                'success' => false,
                'message' => 'Absen ditolak. Anda berada di luar radius rumah sakit (Jarak Anda: ' . round($distance) . ' meter).',
            ], 403);
        }

        $jamPulangShift = Carbon::parse($roster->tanggal_dinas . ' ' . $shift->jam_pulang);
        if (Carbon::parse($shift->jam_pulang)->lessThan(Carbon::parse($shift->jam_masuk))) {
            $jamPulangShift->addDay();
        }

        $bisaLembur = false;
        $kelebihanWaktuMenit = 0;

        if ($now->greaterThan($jamPulangShift)) {
            $bisaLembur = true;
            // ✅ FIX #7: Urutan diffInMinutes yang benar
            $kelebihanWaktuMenit = $jamPulangShift->diffInMinutes($now);
        }

        // ✅ FIX #8: Handle jika NIK null
        $nik = $user->nik ?? $user->id;
        $file = $request->file('foto');
        $filename = 'out_' . $nik . '_' . time() . '.' . $file->extension();
        $path = $file->storeAs('absensi_pulang', $filename, 'public');

        $logAbsen->update([
            'waktu_pulang' => $now,
            'foto_pulang' => $path,
            'latitude_pulang' => $request->latitude,
            'longitude_pulang' => $request->longitude,
            'ip_address_pulang' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Absen pulang berhasil dicatat pada ' . $now->format('H:i:s'),
            'data' => [
                'waktu_pulang' => $now->toDateTimeString(),
                'bisa_lembur' => $bisaLembur,
                'kelebihan_waktu_menit' => $kelebihanWaktuMenit,
            ]
        ], 200);
    }

    /**
     * Fungsi Pembantu: Menghitung Jarak (Haversine Formula)
     */
    private function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000; // meter

        $latDelta = deg2rad($lat2 - $lat1);
        $lonDelta = deg2rad($lon2 - $lon1);

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($lonDelta / 2) * sin($lonDelta / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
