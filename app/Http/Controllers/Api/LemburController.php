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
     * LEMBUR EKSTENSI SHIFT (tidak berubah)
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

        $cekEkstensi = LogLembur::where('user_id', $user->id)
            ->where('jenis_lembur', 'Ekstensi Shift')
            ->whereDate('waktu_mulai_lembur', $today)
            ->exists();
        if ($cekEkstensi) {
            return response()->json(['success' => false, 'message' => 'Anda sudah mengajukan lembur ekstensi untuk hari ini.'], 422);
        }

        $roster = JadwalRoster::with('shift')
            ->where('user_id', $user->id)
            ->where('tanggal_dinas', $today)
            ->first();
        if (!$roster) {
            return response()->json(['success' => false, 'message' => 'Jadwal dinas tidak ditemukan. Gunakan On-Call untuk hari libur.'], 422);
        }

        $shift = $roster->shift;
        $jamPulangShift = Carbon::parse($today . ' ' . $shift->jam_pulang);
        if (Carbon::parse($shift->jam_pulang)->lessThan(Carbon::parse($shift->jam_masuk))) {
            $jamPulangShift->addDay();
        }

        if ($now->lessThan($jamPulangShift)) {
            return response()->json([
                'success' => false,
                'message' => 'Lembur ekstensi hanya bisa diajukan setelah jam pulang shift (' . $jamPulangShift->format('H:i') . ').',
            ], 422);
        }

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

        $waktuSelesai = $now;
        $waktuMulai   = $now->copy()->subMinutes($durasiMenit);
        $totalJam     = round($durasiMenit / 60, 2);

        $logAbsen = LogAbsensi::where('roster_id', $roster->id)->first();
        $autoClockOut = false;
        if ($logAbsen && $logAbsen->waktu_pulang === null) {
            $logAbsen->waktu_pulang = $now;
            $logAbsen->save();
            $autoClockOut = true;
        }

        $cekRadius = $this->verifikasiRadius($request);
        if ($cekRadius !== true) return $cekRadius;

        $file = $request->file('foto_masuk');
        $path = $file->storeAs('lembur_masuk', 'lembur_' . ($user->nik ?? $user->id) . '_' . time() . '.' . $file->extension(), 'public');

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
            'data'    => $this->normalizeLembur(LogLembur::with('user.unitKerja')->latest()->first()),
        ], 200);
    }

    /**
     * ON-CALL MASUK — ✅ kini aman untuk kolom nullable + error terbaca jelas
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

        $cekRadius = $this->verifikasiRadius($request);
        if ($cekRadius !== true) return $cekRadius;

        $file = $request->file('foto_masuk');
        $path = $file->storeAs('oncall_masuk', 'oncall_masuk_' . ($user->nik ?? $user->id) . '_' . time() . '.' . $file->extension(), 'public');

        try {
            $lembur = LogLembur::create([
                'user_id'              => $user->id,
                'jenis_lembur'         => 'On-Call',
                'waktu_mulai_lembur'   => $now,
                'waktu_selesai_lembur' => null,   // ✅ kini kolom nullable
                'total_jam_lembur'     => null,   // ✅ kini kolom nullable
                'status_validasi'      => 'Pending',
                'keterangan'           => $request->keterangan,
                'latitude_masuk'       => $request->latitude,
                'longitude_masuk'      => $request->longitude,
                'foto_masuk'           => $path,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan On-Call: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'On-Call masuk berhasil dicatat pada ' . $now->format('H:i') . '. Jangan lupa lakukan On-Call Keluar saat tugas selesai.',
            'data'    => $this->normalizeLembur($lembur->load('user.unitKerja')),
        ], 200);
    }

    /**
     * ON-CALL KELUAR (tidak berubah, response dinormalisasi)
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

        $cekRadius = $this->verifikasiRadius($request);
        if ($cekRadius !== true) return $cekRadius;

        $today  = $now->toDateString();
        $roster = JadwalRoster::where('user_id', $user->id)->where('tanggal_dinas', $today)->first();
        $autoClockOut = false;
        if ($roster) {
            $logAbsen = LogAbsensi::where('roster_id', $roster->id)->whereNull('waktu_pulang')->first();
            if ($logAbsen) {
                $logAbsen->waktu_pulang = $now;
                $logAbsen->save();
                $autoClockOut = true;
            }
        }

        $waktuMulai  = Carbon::parse($lembur->waktu_mulai_lembur);
        $durasiMenit = intdiv($now->getTimestamp() - $waktuMulai->getTimestamp(), 60);
        $totalJam    = round($durasiMenit / 60, 2);

        if ($durasiMenit < 1) {
            return response()->json(['success' => false, 'message' => 'Durasi on-call belum tercatat (baru saja masuk).'], 422);
        }

        $file = $request->file('foto_keluar');
        $path = $file->storeAs('oncall_keluar', 'oncall_keluar_' . ($user->nik ?? $user->id) . '_' . time() . '.' . $file->extension(), 'public');

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
            'data'    => $this->normalizeLembur($lembur->load('user.unitKerja')),
        ], 200);
    }

    public function onCallAktif(Request $request)
    {
        $lembur = LogLembur::with('user.unitKerja')
            ->where('user_id', $request->user()->id)
            ->where('jenis_lembur', 'On-Call')
            ->whereNull('waktu_selesai_lembur')
            ->latest()
            ->first();

        return response()->json([
            'success' => true,
            'data'    => $lembur ? $this->normalizeLembur($lembur) : null,
        ], 200);
    }

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
     * ✅ LIST VALIDASI — payload dinormalisasi + alias field agar Flutter pasti terbaca
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

        if (in_array($user->role, ['kepala_unit', 'penanggung_jawab'])) {
            $unitIds = $user->managesUnits()->pluck('master_unit_kerjas.id');
            if ($unitIds->isEmpty()) {
                $unitIds = collect([$user->unit_kerja_id]);
            }
            $query->whereHas('user', fn($q) => $q->whereIn('unit_kerja_id', $unitIds));
        }

        $data = $query->get()->map(fn($l) => $this->normalizeLembur($l));

        return response()->json(['success' => true, 'data' => $data], 200);
    }

    /**
     * ✅ PROSES VALIDASI — menerima berbagai variasi key & nilai status dari aplikasi
     */
    public function prosesValidasi(Request $request, $id)
    {
        $user = $request->user();
        $roleAllowed = ['kepala_unit', 'penanggung_jawab', 'hrd', 'superadmin', 'direktur'];
        if (!in_array($user->role, $roleAllowed)) {
            return response()->json(['success' => false, 'message' => 'Tidak memiliki hak akses.'], 403);
        }

        // ✅ Toleran: terima status dari beberapa kemungkinan key
        $raw  = $request->input('status')
            ?? $request->input('status_validasi')
            ?? $request->input('action')
            ?? $request->input('nilai');
        $norm = strtolower(trim((string) $raw));

        $map = [
            'disetujui' => 'Disetujui',
            'setujui' => 'Disetujui',
            'approve' => 'Disetujui',
            'approved'  => 'Disetujui',
            'terima' => 'Disetujui',
            'ditolak'   => 'Ditolak',
            'tolak' => 'Ditolak',
            'reject' => 'Ditolak',
            'rejected' => 'Ditolak',
        ];

        if (!isset($map[$norm])) {
            return response()->json([
                'success' => false,
                'message' => 'Status validasi tidak dikenali. Kirim status: Disetujui / Ditolak.',
            ], 422);
        }

        $lembur = LogLembur::with('user')->findOrFail($id);

        if (in_array($user->role, ['kepala_unit', 'penanggung_jawab'])) {
            $unitIds = $user->managesUnits()->pluck('master_unit_kerjas.id');
            if ($unitIds->isEmpty()) {
                $unitIds = collect([$user->unit_kerja_id]);
            }
            if (!in_array($lembur->user->unit_kerja_id, $unitIds->all())) {
                return response()->json(['success' => false, 'message' => 'Bukan unit yang Anda kelola.'], 403);
            }
        }

        if (!in_array($lembur->status_validasi, ['Menunggu', 'Pending'])) {
            return response()->json(['success' => false, 'message' => 'Sudah divalidasi sebelumnya.'], 422);
        }

        $lembur->status_validasi  = $map[$norm];
        $lembur->catatan_validasi = $request->input('catatan_validasi') ?? $request->input('catatan');
        $lembur->divalidasi_oleh  = $user->id;
        $lembur->divalidasi_pada  = now();
        $lembur->save();

        return response()->json([
            'success' => true,
            'message' => 'Lembur berhasil divalidasi: ' . $map[$norm] . '.',
            'data'    => $this->normalizeLembur($lembur->load('user.unitKerja')),
        ], 200);
    }

    // ===================================================================
    // ✅ HELPER: normalisasi payload lembur (field mentah + alias untuk Flutter)
    // ===================================================================
    private function normalizeLembur($l): array
    {
        $user = $l->user;
        $unit = $user?->unitKerja;

        $mulai   = $l->waktu_mulai_lembur ? Carbon::parse($l->waktu_mulai_lembur) : null;
        $selesai = $l->waktu_selesai_lembur ? Carbon::parse($l->waktu_selesai_lembur) : null;

        $fotoProfil = null;
        if ($user) {
            $pathFoto = $user->foto_profil ?? $user->foto ?? null;
            $fotoProfil = $pathFoto ? url('storage/' . $pathFoto) : null;
        }

        return array_merge($l->toArray(), [
            // Alias nama & unit
            'nama'          => $user?->name ?? '-',
            'nama_karyawan' => $user?->name ?? '-',
            'unit'          => $unit?->nama_unit ?? '-',
            'unit_kerja'    => $unit?->nama_unit ?? '-',

            // Alias waktu (format lengkap & jam saja)
            'waktu_mulai'   => $mulai?->format('Y-m-d H:i'),
            'waktu_selesai' => $selesai?->format('Y-m-d H:i'),
            'jam_mulai'     => $mulai?->format('H:i'),
            'jam_selesai'   => $selesai?->format('H:i'),
            'tanggal'       => $mulai?->format('d/m/Y'),

            // Alias durasi
            'total_jam'  => $l->total_jam_lembur,
            'durasi_jam' => $l->total_jam_lembur,

            // Alias status
            'status' => $l->status_validasi,

            // URL foto (siap tampil di Flutter)
            'foto'            => $l->foto_masuk ? url('storage/' . $l->foto_masuk) : null,
            'foto_masuk_url'  => $l->foto_masuk ? url('storage/' . $l->foto_masuk) : null,
            'foto_keluar_url' => $l->foto_keluar ? url('storage/' . $l->foto_keluar) : null,

            // Objek user lengkap dengan alias
            'user' => $user ? [
                'id'         => $user->id,
                'name'       => $user->name,
                'nama'       => $user->name,
                'unit'       => $unit?->nama_unit,
                'nama_unit'  => $unit?->nama_unit,
                'unit_kerja' => $unit?->nama_unit,
                'foto'       => $fotoProfil,
                'foto_profil' => $fotoProfil,
            ] : null,
        ]);
    }

    // ===================================================================
    // HELPER RADIUS (dibuat toleran terhadap nama kolom radius)
    // ===================================================================
    private function verifikasiRadius(Request $request)
    {
        $pengaturan  = PengaturanAplikasi::first();
        $hospitalLat = $pengaturan ? (float) $pengaturan->latitude  : -0.9471;
        $hospitalLng = $pengaturan ? (float) $pengaturan->longitude : 100.3511;
        $maxRadius   = $pengaturan ? (int) ($pengaturan->radius_meter ?? $pengaturan->radius_absen ?? 50) : 50;

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
