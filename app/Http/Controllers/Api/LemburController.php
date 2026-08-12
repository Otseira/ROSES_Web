<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LogLembur;
use App\Models\LogAbsensi;
use App\Models\JadwalRoster;
use App\Models\PengaturanAplikasi;
use Carbon\Carbon;
use Illuminate\Http\Request;

class LemburController extends Controller
{
    /**
     * LEMBUR EKSTENSI SHIFT
     * - Auto clock-out jika belum absen pulang
     * - Durasi default otomatis (akhir shift → sekarang)
     * - Bisa diedit manual dalam range 1 – maxMenit
     */
    public function storeEkstensi(Request $request)
    {
        $request->validate([
            'keterangan'   => 'required|string|max:500',
            'latitude'     => 'required|numeric',
            'longitude'    => 'required|numeric',
            'foto_masuk'   => 'required|image|mimes:jpeg,png,jpg|max:2048',
            'durasi_menit' => 'nullable|integer|min:1|max:720',
        ]);

        $user  = $request->user()->load('unitKerja');
        $now   = Carbon::now();
        $today = $now->toDateString();

        // Cegah duplikasi
        $cekEkstensi = LogLembur::where('user_id', $user->id)
            ->where('jenis_lembur', 'Ekstensi Shift')
            ->whereDate('waktu_mulai_lembur', $today)
            ->exists();
        if ($cekEkstensi) {
            return response()->json(['success' => false, 'message' => 'Anda sudah mengajukan lembur ekstensi untuk hari ini.'], 422);
        }

        // Ambil roster
        $roster = JadwalRoster::with('shift')
            ->where('user_id', $user->id)
            ->where('tanggal_dinas', $today)
            ->first();
        if (!$roster) {
            return response()->json(['success' => false, 'message' => 'Jadwal dinas tidak ditemukan.'], 422);
        }

        // Hitung jam pulang shift (handle shift malam)
        $shift = $roster->shift;
        $jamPulangShift = Carbon::parse($today . ' ' . $shift->jam_pulang);
        if (Carbon::parse($shift->jam_pulang)->lessThan(Carbon::parse($shift->jam_masuk))) {
            $jamPulangShift->addDay();
        }

        if ($now->lessThan($jamPulangShift)) {
            return response()->json(['success' => false, 'message' => 'Lembur ekstensi hanya bisa diajukan setelah jam pulang shift (' . $jamPulangShift->format('H:i') . ').'], 422);
        }

        // ✅ RANGE TERKUNCI
        $maxMenit    = intdiv($now->getTimestamp() - $jamPulangShift->getTimestamp(), 60);
        $durasiMenit = $request->filled('durasi_menit') ? (int) $request->durasi_menit : $maxMenit;

        if ($durasiMenit < 1) {
            return response()->json(['success' => false, 'message' => 'Durasi minimal 1 menit.'], 422);
        }
        if ($durasiMenit > $maxMenit) {
            return response()->json(['success' => false, 'message' => "Durasi melebihi batas. Maksimal {$maxMenit} menit."], 422);
        }

        // Hitung waktu
        $waktuSelesai = $now;
        $waktuMulai   = $now->copy()->subMinutes($durasiMenit);
        $totalJam     = round($durasiMenit / 60, 2);

        // ✅ AUTO CLOCK-OUT
        $logAbsen = LogAbsensi::where('roster_id', $roster->id)->first();
        $autoClockOut = false;
        if ($logAbsen && $logAbsen->waktu_pulang === null) {
            $logAbsen->waktu_pulang      = $now;
            $logAbsen->ip_address_pulang = $request->ip();
            $logAbsen->save();
            $autoClockOut = true;
        }

        // Verifikasi radius
        $cekRadius = $this->verifikasiRadius($request);
        if ($cekRadius !== true) return $cekRadius;

        // Simpan foto
        $file = $request->file('foto_masuk');
        $path = $file->storeAs('lembur_masuk', 'lembur_' . ($user->nik ?? $user->id) . '_' . time() . '.' . $file->extension(), 'public');

        // Simpan lembur LANGSUNG SELESAI
        LogLembur::create([
            'user_id'              => $user->id,
            'jenis_lembur'         => 'Ekstensi Shift',
            'waktu_mulai_lembur'   => $waktuMulai,
            'waktu_selesai_lembur' => $waktuSelesai,
            'total_jam_lembur'     => $totalJam,
            'status_validasi'      => 'Pending',
            'keterangan'           => $request->keterangan,
            'latitude_masuk'       => $request->latitude,
            'longitude_masuk'      => $request->longitude,
            'foto_masuk'           => $path,
        ]);

        return response()->json([
            'success' => true,
            'message' => ($autoClockOut ? '✓ Absen pulang tercatat otomatis. ' : '') . '✓ Lembur ekstensi berhasil disimpan.',
            'data'    => [
                'jenis_lembur'   => 'Ekstensi Shift',
                'waktu_mulai'    => $waktuMulai->format('H:i'),
                'waktu_selesai'  => $waktuSelesai->format('H:i'),
                'total_menit'    => $durasiMenit,
                'total_jam'      => $totalJam,
                'auto_clock_out' => $autoClockOut,
            ],
        ], 200);
    }

    /**
     * ON-CALL
     * - Auto clock-out jika ada log absensi terbuka
     * - Durasi WAJIB diisi manual (tidak ada shift acuan)
     * - Langsung selesai (tidak perlu clock-out terpisah)
     */
    public function clockInOnCall(Request $request)
    {
        $request->validate([
            'keterangan'   => 'required|string|max:500',
            'latitude'     => 'required|numeric',
            'longitude'    => 'required|numeric',
            'foto_masuk'   => 'required|image|mimes:jpeg,png,jpg|max:2048',
            'durasi_menit' => 'required|integer|min:15|max:720',
        ]);

        $user  = $request->user()->load('unitKerja');
        $now   = Carbon::now();
        $today = $now->toDateString();

        $cekOnCall = LogLembur::where('user_id', $user->id)
            ->where('jenis_lembur', 'On-Call')
            ->whereNull('waktu_selesai_lembur')
            ->first();
        if ($cekOnCall) {
            return response()->json(['success' => false, 'message' => 'Masih ada sesi On-Call aktif.'], 422);
        }

        // ✅ AUTO CLOCK-OUT
        $roster = JadwalRoster::where('user_id', $user->id)->where('tanggal_dinas', $today)->first();
        $autoClockOut = false;
        if ($roster) {
            $logAbsen = LogAbsensi::where('roster_id', $roster->id)->whereNull('waktu_pulang')->first();
            if ($logAbsen) {
                $logAbsen->waktu_pulang      = $now;
                $logAbsen->ip_address_pulang = $request->ip();
                $logAbsen->save();
                $autoClockOut = true;
            }
        }

        // Verifikasi radius
        $cekRadius = $this->verifikasiRadius($request);
        if ($cekRadius !== true) return $cekRadius;

        // Hitung waktu: Selesai = sekarang, Mulai = mundur
        $durasiMenit = (int) $request->durasi_menit;
        $waktuSelesai = $now;
        $waktuMulai   = $now->copy()->subMinutes($durasiMenit);
        $totalJam     = round($durasiMenit / 60, 2);

        // Simpan foto
        $file = $request->file('foto_masuk');
        $path = $file->storeAs('oncall_masuk', 'oncall_masuk_' . ($user->nik ?? $user->id) . '_' . time() . '.' . $file->extension(), 'public');

        // Simpan LANGSUNG SELESAI
        LogLembur::create([
            'user_id'              => $user->id,
            'jenis_lembur'         => 'On-Call',
            'waktu_mulai_lembur'   => $waktuMulai,
            'waktu_selesai_lembur' => $waktuSelesai,
            'total_jam_lembur'     => $totalJam,
            'status_validasi'      => 'Pending',
            'keterangan'           => $request->keterangan,
            'latitude_masuk'       => $request->latitude,
            'longitude_masuk'      => $request->longitude,
            'foto_masuk'           => $path,
        ]);

        return response()->json([
            'success' => true,
            'message' => ($autoClockOut ? '✓ Absen pulang tercatat otomatis. ' : '') . '✓ On-Call berhasil diselesaikan.',
            'data'    => [
                'jenis_lembur'   => 'On-Call',
                'waktu_mulai'    => $waktuMulai->format('H:i'),
                'waktu_selesai'  => $waktuSelesai->format('H:i'),
                'total_menit'    => $durasiMenit,
                'total_jam'      => $totalJam,
                'auto_clock_out' => $autoClockOut,
            ],
        ], 200);
    }

    /**
     * ✅ BARU: Info shift hari ini (untuk default durasi di mobile)
     */
    public function infoShiftHariIni(Request $request)
    {
        $user  = $request->user();
        $today = now()->toDateString();

        $roster = JadwalRoster::with('shift')
            ->where('user_id', $user->id)
            ->where('tanggal_dinas', $today)
            ->first();

        if (!$roster) {
            return response()->json(['success' => true, 'data' => null], 200);
        }

        $jamPulang = Carbon::parse($today . ' ' . $roster->shift->jam_pulang);
        if (Carbon::parse($roster->shift->jam_pulang)->lessThan(Carbon::parse($roster->shift->jam_masuk))) {
            $jamPulang->addDay();
        }

        $maxMenit = intdiv(now()->getTimestamp() - $jamPulang->getTimestamp(), 60);

        return response()->json([
            'success' => true,
            'data'    => [
                'jam_masuk'  => $roster->shift->jam_masuk,
                'jam_pulang' => $roster->shift->jam_pulang,
                'max_menit'  => max(0, $maxMenit),
            ],
        ], 200);
    }

    /**
     * Validasi oleh atasan — handle 2 versi status: "Pending" atau "Menunggu"
     */
    public function listValidasi(Request $request)
    {
        $user = $request->user();
        $roleAllowed = ['kepala_unit', 'penanggung_jawab', 'hrd', 'superadmin', 'direktur'];
        if (!in_array($user->role, $roleAllowed)) {
            return response()->json(['success' => false, 'message' => 'Tidak memiliki hak akses.'], 403);
        }

        $query = LogLembur::with('user.unitKerja')->latest('waktu_mulai_lembur');
        if ($request->filled('status')) $query->where('status_validasi', $request->status);

        if (in_array($user->role, ['kepala_unit', 'penanggung_jawab'])) {
            $unitIds = $user->managesUnits()->pluck('master_unit_kerjas.id');
            if ($unitIds->isEmpty()) $unitIds = collect([$user->unit_kerja_id]);
            $query->whereHas('user', fn($q) => $q->whereIn('unit_kerja_id', $unitIds));
        }

        return response()->json(['success' => true, 'data' => $query->get()], 200);
    }

    public function prosesValidasi(Request $request, $id)
    {
        $user = $request->user();
        $roleAllowed = ['kepala_unit', 'penanggung_jawab', 'hrd', 'superadmin', 'direktur'];
        if (!in_array($user->role, $roleAllowed)) {
            return response()->json(['success' => false, 'message' => 'Tidak memiliki hak akses.'], 403);
        }

        $request->validate([
            'status'           => 'required|in:Disetujui,Ditolak',
            'catatan_validasi' => 'nullable|string|max:500',
        ]);

        $lembur = LogLembur::with('user')->findOrFail($id);

        if (in_array($user->role, ['kepala_unit', 'penanggung_jawab'])) {
            $unitIds = $user->managesUnits()->pluck('master_unit_kerjas.id');
            if ($unitIds->isEmpty()) $unitIds = collect([$user->unit_kerja_id]);
            if (!in_array($lembur->user->unit_kerja_id, $unitIds->all())) {
                return response()->json(['success' => false, 'message' => 'Bukan unit yang Anda kelola.'], 403);
            }
        }

        if (!in_array($lembur->status_validasi, ['Menunggu', 'Pending'])) {
            return response()->json(['success' => false, 'message' => 'Sudah divalidasi sebelumnya.'], 422);
        }

        $lembur->status_validasi  = $request->status;
        $lembur->catatan_validasi = $request->catatan_validasi;
        $lembur->divalidasi_oleh  = $user->id;
        $lembur->divalidasi_pada  = now();
        $lembur->save();

        return response()->json(['success' => true, 'message' => 'Lembur berhasil divalidasi.'], 200);
    }

    // ===== Helpers =====
    private function verifikasiRadius(Request $request)
    {
        $pengaturan   = PengaturanAplikasi::first();
        $hospitalLat  = $pengaturan ? (float) $pengaturan->latitude  : -0.9471;
        $hospitalLng  = $pengaturan ? (float) $pengaturan->longitude : 100.3511;
        $maxRadius    = $pengaturan ? (int) $pengaturan->radius_meter : 50;

        $distance = $this->calculateDistance($request->latitude, $request->longitude, $hospitalLat, $hospitalLng);
        if ($distance > $maxRadius) {
            return response()->json([
                'success' => false,
                'message' => 'Ditolak. Anda di luar radius rumah sakit (' . round($distance) . ' meter).',
            ], 403);
        }
        return true;
    }

    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $R = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
