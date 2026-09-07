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
     * LEMBUR EKSTENSI SHIFT — 1x tekan: akhiri jam dinas + catat lembur.
     * ✅ Wajib dalam radius • foto disalin sebagai foto pulang.
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

        // Ambil roster hari ini
        $roster = JadwalRoster::with('shift')
            ->where('user_id', $user->id)
            ->where('tanggal_dinas', $today)
            ->first();
        if (!$roster) {
            return response()->json(['success' => false, 'message' => 'Jadwal dinas tidak ditemukan. Gunakan On-Call untuk hari libur.'], 422);
        }

        // Hitung jam pulang shift (handle shift malam)
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

        // Range terkunci: 1 s/d (sekarang − akhir shift)
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

        // ✅ CEK RADIUS (seragam, dinamis dari Pengaturan Sistem)
        $cekRadius = $this->verifikasiRadius($request, 'Lembur ekstensi');
        if ($cekRadius !== true) return $cekRadius;

        // Simpan foto lembur
        $file = $request->file('foto_masuk');
        $path = $file->storeAs('lembur_masuk', 'lembur_' . ($user->nik ?? $user->id) . '_' . time() . '.' . $file->extension(), 'public');

        // ✅ AUTO CLOCK-OUT + foto lembur menjadi foto pulang
        $waktuSelesai = $now;
        $waktuMulai   = $now->copy()->subMinutes($durasiMenit);
        $totalJam     = round($durasiMenit / 60, 2);

        $logAbsen = LogAbsensi::where('roster_id', $roster->id)->first();
        $autoClockOut = false;
        if ($logAbsen && $logAbsen->waktu_pulang === null) {
            $logAbsen->waktu_pulang      = $now;
            $logAbsen->foto_pulang       = $path; // ✅ foto lembur = foto pulang
            $logAbsen->latitude_pulang   = $request->latitude;
            $logAbsen->longitude_pulang  = $request->longitude;
            $logAbsen->save();
            $autoClockOut = true;
        }

        $lembur = LogLembur::create([
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
            'data'    => $this->normalizeLembur($lembur->load('user.unitKerja')),
        ], 200);
    }

    /**
     * ON-CALL MASUK — seperti absen masuk (foto & GPS tersendiri).
     * ✅ Wajib dalam radius (kebijakan seragam).
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

        // ✅ LOGIKA BARU: On-Call hanya boleh jika:
        //    a) Belum ada absen masuk hari ini (hari libur / tanpa jadwal), ATAU
        //    b) Sudah absen masuk DAN sudah absen pulang (setelah jam dinas selesai)
        $logAbsenHariIni = LogAbsensi::where('user_id', $user->id)
            ->whereDate('waktu_masuk', $now->toDateString())
            ->latest('waktu_masuk')
            ->first();

        if ($logAbsenHariIni) {
            // Sudah ada absen masuk — cek apakah sudah absen pulang
            if ($logAbsenHariIni->waktu_pulang === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'On-Call tidak bisa dimulai saat jam dinas masih aktif. Gunakan "Ekstensi Shift" untuk menambah waktu kerja, atau lakukan Absen Pulang terlebih dahulu.',
                ], 422);
            }
        }
        // Jika $logAbsenHariIni null → boleh on-call (hari libur / tanpa jadwal)
        // Jika $logAbsenHariIni tidak null DAN sudah absen pulang → boleh on-call (setelah jam dinas)

        // ✅ CEK RADIUS (seragam, dinamis dari Pengaturan Sistem)
        $cekRadius = $this->verifikasiRadius($request, 'On-Call masuk');
        if ($cekRadius !== true) return $cekRadius;

        // Simpan foto on-call masuk (terpisah dari lembur)
        $file = $request->file('foto_masuk');
        $path = $file->storeAs('oncall_masuk', 'oncall_masuk_' . ($user->nik ?? $user->id) . '_' . time() . '.' . $file->extension(), 'public');

        $lembur = LogLembur::create([
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
            'data'    => $this->normalizeLembur($lembur->load('user.unitKerja')),
        ], 200);
    }

    /**
     * ON-CALL KELUAR — akhiri sesi + durasi otomatis + auto clock-out.
     * ✅ Wajib dalam radius (kebijakan seragam).
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

        // ✅ CEK RADIUS (seragam, dinamis dari Pengaturan Sistem)
        $cekRadius = $this->verifikasiRadius($request, 'On-Call keluar');
        if ($cekRadius !== true) return $cekRadius;

        // Durasi otomatis: masuk → keluar
        $waktuMulai  = Carbon::parse($lembur->waktu_mulai_lembur);
        $durasiMenit = intdiv($now->getTimestamp() - $waktuMulai->getTimestamp(), 60);
        $totalJam    = round($durasiMenit / 60, 2);

        if ($durasiMenit < 1) {
            return response()->json(['success' => false, 'message' => 'Durasi on-call belum tercatat (baru saja masuk).'], 422);
        }

        // Simpan foto on-call keluar (terpisah)
        $file = $request->file('foto_keluar');
        $path = $file->storeAs('oncall_keluar', 'oncall_keluar_' . ($user->nik ?? $user->id) . '_' . time() . '.' . $file->extension(), 'public');

        // ✅ AUTO CLOCK-OUT + foto on-call keluar menjadi foto pulang (jika belum ada)
        $today  = $now->toDateString();
        $roster = JadwalRoster::where('user_id', $user->id)->where('tanggal_dinas', $today)->first();
        $autoClockOut = false;
        if ($roster) {
            $logAbsen = LogAbsensi::where('roster_id', $roster->id)->whereNull('waktu_pulang')->first();
            if ($logAbsen) {
                $logAbsen->waktu_pulang      = $now;
                $logAbsen->foto_pulang       = $path; // ✅ foto on-call keluar = foto pulang
                $logAbsen->latitude_pulang   = $request->latitude;
                $logAbsen->longitude_pulang  = $request->longitude;
                $logAbsen->save();
                $autoClockOut = true;
            }
        }

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

    /** Cek sesi on-call aktif (untuk tombol dinamis di mobile). */
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

    /** Info shift hari ini (untuk default durasi ekstensi). */
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

    /** List validasi untuk atasan — payload lengkap untuk aplikasi. */
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

        return response()->json([
            'success' => true,
            'data'    => $query->get()->map(fn($l) => $this->normalizeLembur($l)),
        ], 200);
    }

    /** Proses validasi — toleran terhadap variasi key/nilai status dari aplikasi. */
    public function prosesValidasi(Request $request, $id)
    {
        $user = $request->user();
        $roleAllowed = ['kepala_unit', 'penanggung_jawab', 'hrd', 'superadmin', 'direktur'];
        if (!in_array($user->role, $roleAllowed)) {
            return response()->json(['success' => false, 'message' => 'Tidak memiliki hak akses.'], 403);
        }

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
            return response()->json(['success' => false, 'message' => 'Status validasi tidak dikenali. Kirim status: Disetujui / Ditolak.'], 422);
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
    // HELPER
    // ===================================================================

    /**
     * ✅ CEK RADIUS DINAMIS — nilai selalu dibaca live dari Pengaturan Sistem.
     * Berlaku seragam: ekstensi shift, on-call masuk, on-call keluar.
     */
    private function verifikasiRadius(Request $request, string $label = 'Absensi')
    {
        $pengaturan = PengaturanAplikasi::first();

        if (!$pengaturan || !$pengaturan->latitude || !$pengaturan->longitude) {
            return true; // GPS kantor belum diatur → jangan mengunci
        }

        $jarak = $this->calculateDistance(
            (float) $pengaturan->latitude,
            (float) $pengaturan->longitude,
            (float) $request->latitude,
            (float) $request->longitude
        );

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

    /** Baca radius — tahan terhadap perbedaan nama kolom di tabel pengaturan. */
    private function getMaxRadius($pengaturan): int
    {
        if (!$pengaturan) return 100;

        foreach ($pengaturan->getAttributes() as $key => $val) {
            if (str_contains($key, 'radius') && $val !== null && $val !== '') {
                return (int) $val;
            }
        }

        return 100;
    }

    /** Payload lembur dinormalisasi — semua key yang dibaca aplikasi Flutter. */
    private function normalizeLembur($l): array
    {
        $user = $l->user;
        $unit = $user?->unitKerja;

        $mulai   = $l->waktu_mulai_lembur ? Carbon::parse($l->waktu_mulai_lembur) : null;
        $selesai = $l->waktu_selesai_lembur ? Carbon::parse($l->waktu_selesai_lembur) : null;

        $nama     = $user?->name ?? '-';
        $nik      = $user?->nik ?? '-';
        $unitNama = $unit?->nama_unit ?? '-';

        $fotoProfil = null;
        if ($user) {
            $pathFoto = $user->foto_profil ?? $user->foto ?? null;
            $fotoProfil = $pathFoto ? url('storage/' . $pathFoto) : null;
        }

        return array_merge($l->toArray(), [
            // Key persis yang dibaca validasi_lembur_screen.dart
            'pegawai_nama' => $nama,
            'pegawai_nik'  => $nik,
            'unit_nama'    => $unitNama,
            'lat_masuk'    => $l->latitude_masuk   !== null ? (float) $l->latitude_masuk   : null,
            'lng_masuk'    => $l->longitude_masuk  !== null ? (float) $l->longitude_masuk  : null,
            'lat_keluar'   => $l->latitude_keluar  !== null ? (float) $l->latitude_keluar  : null,
            'lng_keluar'   => $l->longitude_keluar !== null ? (float) $l->longitude_keluar : null,

            // Alias cadangan
            'nama'          => $nama,
            'nama_karyawan' => $nama,
            'nama_pegawai'  => $nama,
            'unit'          => $unitNama,
            'unit_kerja'    => $unitNama,
            'nama_unit'     => $unitNama,

            // Waktu
            'waktu_mulai'   => $mulai?->format('Y-m-d H:i'),
            'waktu_selesai' => $selesai?->format('Y-m-d H:i'),
            'jam_mulai'     => $mulai?->format('H:i'),
            'jam_selesai'   => $selesai?->format('H:i'),
            'tanggal'       => $mulai?->format('d/m/Y'),

            // Durasi & status
            'total_jam'  => $l->total_jam_lembur,
            'durasi_jam' => $l->total_jam_lembur,
            'status'     => $l->status_validasi,

            // URL foto
            'foto'            => $l->foto_masuk ? url('storage/' . $l->foto_masuk) : null,
            'foto_masuk_url'  => $l->foto_masuk ? url('storage/' . $l->foto_masuk) : null,
            'foto_keluar_url' => $l->foto_keluar ? url('storage/' . $l->foto_keluar) : null,
            'foto_profil'     => $fotoProfil,

            // Objek user lengkap
            'user' => $user ? [
                'id'          => $user->id,
                'name'        => $user->name,
                'nama'        => $user->name,
                'nik'         => $user->nik,
                'unit'        => $unitNama,
                'nama_unit'   => $unitNama,
                'unit_kerja'  => $unitNama,
                'foto'        => $fotoProfil,
                'foto_profil' => $fotoProfil,
            ] : null,
        ]);
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
