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
     * ===================================================================
     * LEMBUR EKSTENSI SHIFT
     * - 1x tekan → mengakhiri jam dinas (auto clock-out)
     * - Durasi default otomatis (akhir shift → sekarang)
     * - Bisa diedit manual dalam range 1 – maxMenit
     * ===================================================================
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

        // Cegah duplikasi ekstensi hari ini
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
            return response()->json(['success' => false, 'message' => 'Jadwal dinas tidak ditemukan. Gunakan On-Call untuk hari libur.'], 422);
        }

        // Hitung jam pulang shift (handle shift malam lewat tengah malam)
        $shift = $roster->shift;
        $jamPulangShift = Carbon::parse($today . ' ' . $shift->jam_pulang);
        if (Carbon::parse($shift->jam_pulang)->lessThan(Carbon::parse($shift->jam_masuk))) {
            $jamPulangShift->addDay();
        }

        // Tidak boleh sebelum jam pulang shift
        if ($now->lessThan($jamPulangShift)) {
            return response()->json([
                'success' => false,
                'message' => 'Lembur ekstensi hanya bisa diajukan setelah jam pulang shift (' . $jamPulangShift->format('H:i') . ').',
            ], 422);
        }

        // ✅ RANGE TERKUNCI: 1 s/d (sekarang − akhir shift)
        $maxMenit    = intdiv($now->getTimestamp() - $jamPulangShift->getTimestamp(), 60);
        $durasiMenit = $request->filled('durasi_menit') ? (int) $request->durasi_menit : $maxMenit;

        if ($durasiMenit < 1) {
            return response()->json(['success' => false, 'message' => 'Durasi minimal 1 menit.'], 422);
        }
        if ($durasiMenit > $maxMenit) {
            return response()->json([
                'success' => false,
                'message' => "Durasi melebihi batas. Maksimal {$maxMenit} menit (dari akhir shift {$shift->jam_pulang} s/d sekarang).",
            ], 422);
        }

        // Hitung waktu
        $waktuSelesai = $now;
        $waktuMulai   = $now->copy()->subMinutes($durasiMenit);
        $totalJam     = round($durasiMenit / 60, 2);

        // ✅ AUTO CLOCK-OUT: akhiri jam dinas jika belum absen pulang
        $logAbsen = LogAbsensi::where('roster_id', $roster->id)->first();
        $autoClockOut = false;
        if ($logAbsen && $logAbsen->waktu_pulang === null) {
            $logAbsen->waktu_pulang      = $now;
            $logAbsen->ip_address_pulang = $request->ip();
            $logAbsen->save();
            $autoClockOut = true;
        }

        // Geofencing
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
     * ===================================================================
     * ON-CALL MASUK
     * - Mirip absen masuk: catat jam mulai + foto + GPS
     * - TIDAK menghitung durasi
     * - TIDAK mengakhiri jam dinas
     * ===================================================================
     */
    public function clockInOnCall(Request $request)
    {
        $request->validate([
            'keterangan' => 'required|string|max:500',
            'latitude'   => 'required|numeric',
            'longitude'  => 'required|numeric',
            'foto_masuk' => 'required|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $user = $request->user()->load('unitKerja');
        $now  = Carbon::now();

        // Cegah sesi ganda
        $aktif = LogLembur::where('user_id', $user->id)
            ->where('jenis_lembur', 'On-Call')
            ->whereNull('waktu_selesai_lembur')
            ->exists();
        if ($aktif) {
            return response()->json([
                'success' => false,
                'message' => 'Anda masih memiliki sesi On-Call aktif. Selesaikan dengan On-Call Keluar.',
            ], 422);
        }

        // Geofencing DULUAN (sebelum menyimpan apa pun)
        $cekRadius = $this->verifikasiRadius($request);
        if ($cekRadius !== true) return $cekRadius;

        // Simpan foto
        $file = $request->file('foto_masuk');
        $path = $file->storeAs('oncall_masuk', 'oncall_masuk_' . ($user->nik ?? $user->id) . '_' . time() . '.' . $file->extension(), 'public');

        // ✅ Catat MULAI saja — seperti absen masuk (tanpa durasi, tanpa clock-out)
        LogLembur::create([
            'user_id'              => $user->id,
            'jenis_lembur'         => 'On-Call',
            'waktu_mulai_lembur'   => $now,
            'waktu_selesai_lembur' => null,
            'total_jam_lembur'     => null,
            'status_validasi'      => 'Pending',
            'keterangan'           => $request->keterangan,
            'latitude_masuk'       => $request->latitude,
            'longitude_masuk'      => $request->longitude,
            'foto_masuk'           => $path,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'On-Call masuk berhasil dicatat pada ' . $now->format('H:i') . '. Jangan lupa lakukan On-Call Keluar saat tugas selesai.',
            'data'    => [
                'waktu_mulai' => $now->format('H:i'),
            ],
        ], 200);
    }

    /**
     * ===================================================================
     * ON-CALL KELUAR
     * - Menyerupai lembur: mengakhiri sesi + hitung durasi otomatis
     * - Durasi otomatis = waktu keluar − waktu masuk
     * - Auto clock-out jam dinas jika belum absen pulang
     * ===================================================================
     */
    public function clockOutOnCall(Request $request)
    {
        $request->validate([
            'latitude'    => 'required|numeric',
            'longitude'   => 'required|numeric',
            'foto_keluar' => 'required|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $user = $request->user()->load('unitKerja');
        $now  = Carbon::now();

        // Ambil sesi On-Call aktif
        $lembur = LogLembur::where('user_id', $user->id)
            ->where('jenis_lembur', 'On-Call')
            ->whereNull('waktu_selesai_lembur')
            ->latest()
            ->first();
        if (!$lembur) {
            return response()->json([
                'success' => false,
                'message' => 'Sesi On-Call aktif tidak ditemukan. Lakukan On-Call Masuk terlebih dahulu.',
            ], 422);
        }

        // Geofencing DULUAN
        $cekRadius = $this->verifikasiRadius($request);
        if ($cekRadius !== true) return $cekRadius;

        // ✅ AUTO CLOCK-OUT: akhiri jam dinas jika belum absen pulang
        $today  = $now->toDateString();
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

        // ✅ Durasi OTOMATIS: waktu masuk → waktu keluar
        $waktuMulai  = Carbon::parse($lembur->waktu_mulai_lembur);
        $durasiMenit = intdiv($now->getTimestamp() - $waktuMulai->getTimestamp(), 60);
        $totalJam    = round($durasiMenit / 60, 2);

        if ($durasiMenit < 1) {
            return response()->json([
                'success' => false,
                'message' => 'Durasi on-call belum tercatat (baru saja masuk).',
            ], 422);
        }

        // Simpan foto keluar
        $file = $request->file('foto_keluar');
        $path = $file->storeAs('oncall_keluar', 'oncall_keluar_' . ($user->nik ?? $user->id) . '_' . time() . '.' . $file->extension(), 'public');

        // Update log on-call
        $lembur->update([
            'waktu_selesai_lembur' => $now,
            'total_jam_lembur'     => $totalJam,
            'latitude_keluar'      => $request->latitude,
            'longitude_keluar'     => $request->longitude,
            'foto_keluar'          => $path,
        ]);

        return response()->json([
            'success' => true,
            'message' => ($autoClockOut ? '✓ Absen pulang tercatat otomatis. ' : '') . '✓ On-Call selesai dicatat.',
            'data'    => [
                'jenis_lembur'   => 'On-Call',
                'waktu_mulai'    => $waktuMulai->format('H:i'),
                'waktu_selesai'  => $now->format('H:i'),
                'total_menit'    => $durasiMenit,
                'total_jam'      => $totalJam,
                'auto_clock_out' => $autoClockOut,
                'keterangan'     => $lembur->keterangan,
            ],
        ], 200);
    }

    /**
     * ===================================================================
     * ON-CALL AKTIF
     * - Cek apakah ada sesi on-call yang belum diselesaikan
     * - Untuk tombol dinamis di mobile (masuk / keluar)
     * ===================================================================
     */
    public function onCallAktif(Request $request)
    {
        $lembur = LogLembur::where('user_id', $request->user()->id)
            ->where('jenis_lembur', 'On-Call')
            ->whereNull('waktu_selesai_lembur')
            ->latest()
            ->first();

        return response()->json([
            'success' => true,
            'data'    => $lembur,
        ], 200);
    }

    /**
     * ===================================================================
     * INFO SHIFT HARI INI
     * - Untuk default durasi di form ekstensi shift
     * ===================================================================
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
     * ===================================================================
     * VALIDASI LEMBUR OLEH ATASAN
     * ===================================================================
     */
    public function listValidasi(Request $request)
    {
        $user = $request->user();
        $roleAllowed = ['kepala_unit', 'penanggung_jawab', 'hrd', 'superadmin', 'direktur'];
        if (!in_array($user->role, $roleAllowed)) {
            return response()->json(['success' => false, 'message' => 'Tidak memiliki hak akses.'], 403);
        }

        $query = LogLembur::with('user.unitKerja')->latest('waktu_mulai_lembur');
        if ($request->filled('status')) {
            $query->where('status_validasi', $request->status);
        }

        // Kepala Unit & PJ hanya bisa lihat lembur dari unit yang dikelola
        if (in_array($user->role, ['kepala_unit', 'penanggung_jawab'])) {
            $unitIds = $user->managesUnits()->pluck('master_unit_kerjas.id');
            if ($unitIds->isEmpty()) {
                $unitIds = collect([$user->unit_kerja_id]);
            }
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

        // Kepala Unit & PJ hanya bisa validasi unit yang dikelola
        if (in_array($user->role, ['kepala_unit', 'penanggung_jawab'])) {
            $unitIds = $user->managesUnits()->pluck('master_unit_kerjas.id');
            if ($unitIds->isEmpty()) {
                $unitIds = collect([$user->unit_kerja_id]);
            }
            if (!in_array($lembur->user->unit_kerja_id, $unitIds->all())) {
                return response()->json(['success' => false, 'message' => 'Bukan unit yang Anda kelola.'], 403);
            }
        }

        // Handle 2 versi status: "Pending" atau "Menunggu"
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

    // ===================================================================
    // HELPER METHODS
    // ===================================================================

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
