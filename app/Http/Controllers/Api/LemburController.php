<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LogLembur;
use App\Models\PengambilanLembur;
use App\Models\LogAbsensi;
use App\Models\JadwalRoster;
use App\Models\PengaturanAplikasi;
use Carbon\Carbon;
use Illuminate\Http\Request;

class LemburController extends Controller
{
    /**
     * LEMBUR EKSTENSI SHIFT — 1x tekan: akhiri jam dinas + catat lembur.
     * ✅ Mendukung shift malam lintas hari & shift custom via rosterReferensiEkstensi.
     */
    public function storeEkstensi(Request $request)
    {
        $request->validate([
            'keterangan'   => 'required|string|max:500',
            'latitude'     => 'required|numeric|between:-90,90',
            'longitude'    => 'required|numeric|between:-180,180',
            'foto_masuk'   => 'required|image|mimes:jpeg,png,jpg,webp|max:2048',
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

        // ✅ Sesi aktif = belum absen pulang
        $logAktif = LogAbsensi::where('user_id', $user->id)
            ->whereNull('waktu_pulang')
            ->where('waktu_masuk', '>=', $now->copy()->subHours(24))
            ->first();

        // ✅ Jika TIDAK ada sesi aktif dan hari ini sudah absen pulang → arahkan ke On-Call
        if (!$logAktif) {
            $sudahPulang = LogAbsensi::where('user_id', $user->id)
                ->whereDate('waktu_masuk', $today)
                ->whereNotNull('waktu_pulang')
                ->exists();

            if ($sudahPulang) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda sudah absen pulang hari ini. Untuk tugas tambahan setelah pulang, gunakan menu On-Call.',
                ], 422);
            }
        }

        // ✅ SESI REFERENSI: cari lewat log absen (mendukung shift malam & custom)
        $roster = $this->rosterReferensiEkstensi($user->id, $now, $logAktif);
        if (!$roster) {
            return response()->json([
                'success' => false,
                'message' => 'Jadwal dinas tidak ditemukan. Gunakan On-Call untuk hari libur.',
            ], 422);
        }

        // ✅ Jam pulang = akhir jendela sesi (akurat untuk shift lintas tengah malam)
        [, $winEnd] = \App\Http\Controllers\Api\AbsensiController::jendelaSesi($roster);

        if ($now->lessThan($winEnd)) {
            return response()->json([
                'success' => false,
                'message' => 'Lembur ekstensi hanya bisa diajukan setelah jam pulang jadwal (' . $winEnd->format('H:i') . ').',
            ], 422);
        }

        // Range terkunci: 1 s/d (sekarang − akhir sesi referensi)
        $maxMenit    = intdiv($now->getTimestamp() - $winEnd->getTimestamp(), 60);
        $durasiMenit = $request->filled('durasi_menit') ? (int) $request->durasi_menit : $maxMenit;

        if ($durasiMenit < 1) {
            return response()->json(['success' => false, 'message' => 'Durasi minimal 1 menit.'], 422);
        }
        if ($durasiMenit > $maxMenit) {
            return response()->json([
                'success' => false,
                'message' => "Durasi melebihi batas. Maksimal {$maxMenit} menit (dari akhir sesi s/d sekarang).",
            ], 422);
        }

        // ✅ CEK RADIUS (seragam, dinamis dari Pengaturan Sistem)
        $cekRadius = $this->verifikasiRadius($request, 'Lembur ekstensi');
        if ($cekRadius !== true) return $cekRadius;

        // Simpan foto lembur
        $file = $request->file('foto_masuk');
        $path = $file->storeAs('lembur_masuk', 'lembur_' . ($user->nik ?? $user->id) . '_' . time() . '.' . $file->extension(), 'public');

        $waktuSelesai = $now;
        $waktuMulai   = $now->copy()->subMinutes($durasiMenit);
        $totalJam     = round($durasiMenit / 60, 2);

        // ✅ AUTO CLOCK-OUT: tutup sesi yang masih aktif (apa pun roster-nya)
        $autoClockOut = false;
        if ($logAktif) {
            $logAktif->waktu_pulang      = $now;
            $logAktif->foto_pulang       = $path;
            $logAktif->latitude_pulang   = $request->latitude;
            $logAktif->longitude_pulang  = $request->longitude;
            $logAktif->save();
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
     * ON-CALL MASUK — hanya boleh setelah SEMUA sesi selesai.
     * ✅ Mendukung shift malam lintas hari & shift custom via log absen.
     */
    public function clockInOnCall(Request $request)
    {
        $request->validate([
            'keterangan' => 'required|string|max:500',
            'latitude'   => 'required|numeric|between:-90,90',
            'longitude'  => 'required|numeric|between:-180,180',
            'foto_masuk' => 'required|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        $user = $request->user()->load('unitKerja');
        $now  = Carbon::now();

        // Cegah sesi ganda on-call
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

        // ✅ Cek sesi dinas AKTIF (belum absen pulang) — via log absen saja
        //    (lebih andal daripada cek roster tanggal hari ini)
        $logAktif = LogAbsensi::where('user_id', $user->id)
            ->whereNull('waktu_pulang')
            ->where('waktu_masuk', '>=', $now->copy()->subHours(36))
            ->first();

        if ($logAktif) {
            return response()->json([
                'success' => false,
                'message' => 'Masih ada sesi dinas yang aktif. Gunakan "Ekstensi Shift" untuk menambah waktu kerja, atau lakukan Absen Pulang terlebih dahulu.',
            ], 422);
        }

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
     * ON-CALL KELUAR — akhiri sesi + durasi otomatis + auto clock-out sesi terakhir (jika perlu).
     */
    public function clockOutOnCall(Request $request)
    {
        $request->validate([
            'latitude'    => 'required|numeric|between:-90,90',
            'longitude'   => 'required|numeric|between:-180,180',
            'foto_keluar' => 'required|image|mimes:jpeg,png,jpg,webp|max:2048',
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

        // ✅ AUTO CLOCK-OUT sesi terakhir (jika belum ada)
        $today  = $now->toDateString();
        $rosterTerakhir = JadwalRoster::where('user_id', $user->id)
            ->where('tanggal_dinas', $today)
            ->orderBy('sesi', 'desc')
            ->first();

        $autoClockOut = false;
        if ($rosterTerakhir) {
            $logAbsen = LogAbsensi::where('roster_id', $rosterTerakhir->id)->whereNull('waktu_pulang')->first();
            if ($logAbsen) {
                $logAbsen->waktu_pulang      = $now;
                $logAbsen->foto_pulang       = $path;
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

    /** Info sesi referensi (untuk default durasi ekstensi) — akurat untuk shift malam & custom. */
    public function infoShiftHariIni(Request $request)
    {
        $user = $request->user();
        $now  = Carbon::now();

        $logAktif = LogAbsensi::where('user_id', $user->id)
            ->whereNull('waktu_pulang')
            ->where('waktu_masuk', '>=', $now->copy()->subHours(24))
            ->first();

        $roster = $this->rosterReferensiEkstensi($user->id, $now, $logAktif);

        if (!$roster) {
            return response()->json(['success' => true, 'data' => null], 200);
        }

        [, $winEnd] = \App\Http\Controllers\Api\AbsensiController::jendelaSesi($roster);
        $maxMenit   = intdiv($now->getTimestamp() - $winEnd->getTimestamp(), 60);

        $jamMasuk = $roster->custom_jam_masuk ?? ($roster->shift ? (string) $roster->shift->jam_masuk : null);

        return response()->json([
            'success' => true,
            'data'    => [
                'jam_masuk'  => $jamMasuk ? substr((string) $jamMasuk, 0, 5) : null,
                'jam_pulang' => $winEnd->format('H:i'),   // ✅ jam pulang SESI yang benar (mis. 07:40)
                'max_menit'  => max(0, $maxMenit),
                'sesi'       => $roster->sesi,
                'tanggal'    => Carbon::parse($roster->tanggal_dinas)->format('Y-m-d'),
            ],
        ], 200);
    }

    /**
     * Riwayat pengajuan lembur & on-call milik karyawan yang sedang login.
     */
    public function riwayatSaya(Request $request)
    {
        $user = $request->user();

        $query = LogLembur::with('user.unitKerja')
            ->where('user_id', $user->id)
            ->latest('waktu_mulai_lembur');

        if ($request->filled('status') && $request->status !== 'Semua') {
            $query->where('status_validasi', $request->status);
        }

        return response()->json([
            'success' => true,
            'data'    => $query->get()->map(fn($l) => $this->normalizeLembur($l)),
        ], 200);
    }

    /** List validasi untuk atasan — payload lengkap untuk aplikasi. */
    public function listValidasi(Request $request)
    {
        $user = $request->user();
        $roleAllowed = ['kepala_unit', 'penanggung_jawab', 'manajer', 'hrd', 'superadmin', 'direktur'];
        if (!in_array($user->role, $roleAllowed)) {
            return response()->json(['success' => false, 'message' => 'Tidak memiliki hak akses.'], 403);
        }

        $query = LogLembur::with('user.unitKerja')->latest('waktu_mulai_lembur');

        if ($request->filled('status')) {
            $query->where('status_validasi', $request->status);
        }

        if ($request->filled('jenis_lembur') && $request->jenis_lembur !== 'Semua') {
            $query->where('jenis_lembur', $request->jenis_lembur);
        }

        if (in_array($user->role, ['kepala_unit', 'penanggung_jawab', 'manajer'])) {
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
        $roleAllowed = ['kepala_unit', 'penanggung_jawab', 'manajer', 'hrd', 'superadmin', 'direktur'];
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

        if (in_array($user->role, ['kepala_unit', 'penanggung_jawab', 'manajer'])) {
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
    // BANK JAM LEMBUR & PENGAMBILAN WAKTU (KOMPENSASI JAM KERJA)
    // ===================================================================

    /**
     * Cek Saldo Bank Jam Lembur pegawai (tersedia & terpakai).
     */
    public function getBankJam(Request $request)
    {
        $user = $request->user()->load(['unitKerja', 'updatedSaldoBy']);

        $saldoAwalMenit = (int) ($user->saldo_awal_lembur_menit ?? 0);

        // Total menit lembur aplikasi yang telah disetujui (Ekstensi & On-Call) sepanjang masa
        $menitLemburAplikasi = (int) round(
            LogLembur::where('user_id', $user->id)
                ->where('status_validasi', 'Disetujui')
                ->sum('total_jam_lembur') * 60
        );

        // Total akumulasi perolehan = Saldo Awal/Manual + Lembur Disetujui
        $earnedMinutes = $saldoAwalMenit + $menitLemburAplikasi;

        // Total menit yang sudah diambil & disetujui sepanjang masa
        $usedMinutes = (int) PengambilanLembur::where('user_id', $user->id)
            ->where('status_validasi', 'Disetujui')
            ->sum('durasi_menit');

        // Total menit yang sedang diajukan (Pending)
        $pendingMinutes = (int) PengambilanLembur::where('user_id', $user->id)
            ->where('status_validasi', 'Pending')
            ->sum('durasi_menit');

        $saldoBersihMenit   = max(0, $earnedMinutes - $usedMinutes);
        $saldoTersediaMenit = max(0, $saldoBersihMenit - $pendingMinutes);

        $jamBersih       = intdiv($saldoBersihMenit, 60);
        $sisaMenitBersih = $saldoBersihMenit % 60;
        $teksSaldoBersih = ($jamBersih > 0 ? "{$jamBersih} jam " : '') . "{$sisaMenitBersih} menit";

        $jamTersedia       = intdiv($saldoTersediaMenit, 60);
        $sisaMenitTersedia = $saldoTersediaMenit % 60;
        $teksSaldoTersedia = ($jamTersedia > 0 ? "{$jamTersedia} jam " : '') . "{$sisaMenitTersedia} menit";

        return response()->json([
            'success' => true,
            'data'    => [
                'is_ganti_jam'           => ($user->unitKerja?->jenis_lembur ?? 'dibayar') === 'ganti_jam',
                'kebijakan_lembur'       => $user->unitKerja?->jenis_lembur ?? 'dibayar',
                'unit_nama'              => $user->unitKerja?->nama_unit ?? '-',
                'saldo_awal_menit'       => $saldoAwalMenit,
                'menit_lembur_aplikasi'  => $menitLemburAplikasi,
                'keterangan_saldo'       => $user->keterangan_saldo_lembur,
                'updated_saldo_by'       => $user->updatedSaldoBy?->name,
                'updated_saldo_at'       => optional($user->updated_saldo_lembur_at)->format('d/m/Y H:i'),
                'total_menit_diperoleh'  => $earnedMinutes,
                'total_menit_digunakan'  => $usedMinutes,
                'total_menit_pending'    => $pendingMinutes,
                'saldo_menit_bersih'     => $saldoBersihMenit,
                'saldo_menit_tersedia'   => $saldoTersediaMenit,
                'saldo_bersih_teks'      => trim($teksSaldoBersih) ?: '0 menit',
                'saldo_tersedia_teks'    => trim($teksSaldoTersedia) ?: '0 menit',
            ],
        ], 200);
    }

    /**
     * Pegawai mengajukan pengambilan waktu lembur (masuk diperlambat / pulang dipercepat).
     */
    public function storePengambilanJam(Request $request)
    {
        $request->validate([
            'tanggal_dinas'    => 'required|date|after_or_equal:today',
            'tipe_pengambilan' => 'required|in:masuk_diperlambat,pulang_dipercepat',
            'durasi_menit'     => 'required|integer|min:15|max:480',
            'keterangan'       => 'required|string|max:500',
        ]);

        $user = $request->user()->load('unitKerja');

        if (($user->unitKerja?->jenis_lembur ?? 'dibayar') !== 'ganti_jam') {
            return response()->json([
                'success' => false,
                'message' => 'Unit kerja Anda menerapkan sistem lembur dibayar, tidak dapat mengajukan pengambilan jam.',
            ], 422);
        }

        // Hitung saldo tersedia
        $earnedMinutes = (int) round(
            LogLembur::where('user_id', $user->id)
                ->where('status_validasi', 'Disetujui')
                ->sum('total_jam_lembur') * 60
        );
        $usedMinutes = (int) PengambilanLembur::where('user_id', $user->id)
            ->where('status_validasi', 'Disetujui')
            ->sum('durasi_menit');
        $pendingMinutes = (int) PengambilanLembur::where('user_id', $user->id)
            ->where('status_validasi', 'Pending')
            ->sum('durasi_menit');

        $saldoTersedia = max(0, $earnedMinutes - $usedMinutes - $pendingMinutes);

        if ($request->durasi_menit > $saldoTersedia) {
            $jam   = intdiv($saldoTersedia, 60);
            $menit = $saldoTersedia % 60;
            $teks  = ($jam > 0 ? "{$jam} jam " : '') . "{$menit} menit";
            return response()->json([
                'success' => false,
                'message' => "Saldo jam lembur tidak mencukupi. Sisa saldo yang dapat diajukan: " . (trim($teks) ?: '0 menit') . ".",
            ], 422);
        }

        // Cegah duplikasi di tanggal dan tipe yang sama jika masih Pending / Disetujui
        $cekAda = PengambilanLembur::where('user_id', $user->id)
            ->whereDate('tanggal_dinas', $request->tanggal_dinas)
            ->where('tipe_pengambilan', $request->tipe_pengambilan)
            ->whereIn('status_validasi', ['Pending', 'Disetujui'])
            ->exists();

        if ($cekAda) {
            return response()->json([
                'success' => false,
                'message' => 'Anda sudah memiliki pengajuan pengambilan jam aktif untuk tanggal dinas tersebut.',
            ], 422);
        }

        $pengambilan = PengambilanLembur::create([
            'user_id'          => $user->id,
            'tanggal_dinas'    => $request->tanggal_dinas,
            'tipe_pengambilan' => $request->tipe_pengambilan,
            'durasi_menit'     => $request->durasi_menit,
            'keterangan'       => $request->keterangan,
            'status_validasi'  => 'Pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pengajuan pengambilan waktu lembur berhasil dikirim dan menunggu persetujuan atasan.',
            'data'    => $this->normalizePengambilanJam($pengambilan->load('user.unitKerja')),
        ], 200);
    }

    /**
     * Riwayat pengajuan pengambilan waktu lembur milik pegawai sendiri.
     */
    public function riwayatPengambilanJam(Request $request)
    {
        $user = $request->user();

        $query = PengambilanLembur::with(['user.unitKerja', 'validator'])
            ->where('user_id', $user->id)
            ->latest('tanggal_dinas');

        if ($request->filled('status') && $request->status !== 'Semua') {
            $query->where('status_validasi', $request->status);
        }

        return response()->json([
            'success' => true,
            'data'    => $query->get()->map(fn($p) => $this->normalizePengambilanJam($p)),
        ], 200);
    }

    /**
     * Daftar validasi pengambilan waktu lembur untuk atasan.
     */
    public function listValidasiPengambilanJam(Request $request)
    {
        $user = $request->user();
        $roleAllowed = ['kepala_unit', 'penanggung_jawab', 'manajer', 'hrd', 'superadmin', 'direktur'];
        if (!in_array($user->role, $roleAllowed)) {
            return response()->json(['success' => false, 'message' => 'Tidak memiliki hak akses.'], 403);
        }

        $query = PengambilanLembur::with(['user.unitKerja', 'validator'])->latest('created_at');

        if ($request->filled('status')) {
            $query->where('status_validasi', $request->status);
        }

        if (in_array($user->role, ['kepala_unit', 'penanggung_jawab', 'manajer'])) {
            $unitIds = $user->managesUnits()->pluck('master_unit_kerjas.id');
            if ($unitIds->isEmpty()) {
                $unitIds = collect([$user->unit_kerja_id]);
            }
            $query->whereHas('user', fn($q) => $q->whereIn('unit_kerja_id', $unitIds));
        }

        return response()->json([
            'success' => true,
            'data'    => $query->get()->map(fn($p) => $this->normalizePengambilanJam($p)),
        ], 200);
    }

    /**
     * Proses validasi pengajuan pengambilan waktu lembur oleh atasan.
     */
    public function prosesValidasiPengambilanJam(Request $request, $id)
    {
        $user = $request->user();
        $roleAllowed = ['kepala_unit', 'penanggung_jawab', 'manajer', 'hrd', 'superadmin', 'direktur'];
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
            'setujui'   => 'Disetujui',
            'approve'   => 'Disetujui',
            'approved'  => 'Disetujui',
            'terima'    => 'Disetujui',
            'ditolak'   => 'Ditolak',
            'tolak'     => 'Ditolak',
            'reject'    => 'Ditolak',
            'rejected'  => 'Ditolak',
        ];

        if (!isset($map[$norm])) {
            return response()->json(['success' => false, 'message' => 'Status validasi tidak dikenali. Kirim status: Disetujui / Ditolak.'], 422);
        }

        $pengambilan = PengambilanLembur::with('user')->findOrFail($id);

        if (in_array($user->role, ['kepala_unit', 'penanggung_jawab', 'manajer'])) {
            $unitIds = $user->managesUnits()->pluck('master_unit_kerjas.id');
            if ($unitIds->isEmpty()) {
                $unitIds = collect([$user->unit_kerja_id]);
            }
            if (!in_array($pengambilan->user->unit_kerja_id, $unitIds->all())) {
                return response()->json(['success' => false, 'message' => 'Bukan unit yang Anda kelola.'], 403);
            }
        }

        if (!in_array($pengambilan->status_validasi, ['Menunggu', 'Pending'])) {
            return response()->json(['success' => false, 'message' => 'Pengajuan sudah divalidasi sebelumnya.'], 422);
        }

        $pengambilan->status_validasi  = $map[$norm];
        $pengambilan->catatan_validasi = $request->input('catatan_validasi') ?? $request->input('catatan');
        $pengambilan->divalidasi_oleh  = $user->id;
        $pengambilan->divalidasi_pada  = now();
        $pengambilan->save();

        return response()->json([
            'success' => true,
            'message' => 'Pengambilan waktu lembur berhasil divalidasi: ' . $map[$norm] . '.',
            'data'    => $this->normalizePengambilanJam($pengambilan->load(['user.unitKerja', 'validator'])),
        ], 200);
    }

    // ===================================================================
    // HELPER
    // ===================================================================

    private function verifikasiRadius(Request $request, string $label = 'Absensi')
    {
        $pengaturan = PengaturanAplikasi::first();

        if (!$pengaturan || !$pengaturan->latitude || !$pengaturan->longitude) {
            return true;
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
            'pegawai_nama' => $nama,
            'pegawai_nik'  => $nik,
            'unit_nama'    => $unitNama,
            'lat_masuk'    => $l->latitude_masuk   !== null ? (float) $l->latitude_masuk   : null,
            'lng_masuk'    => $l->longitude_masuk  !== null ? (float) $l->longitude_masuk  : null,
            'lat_keluar'   => $l->latitude_keluar  !== null ? (float) $l->latitude_keluar  : null,
            'lng_keluar'   => $l->longitude_keluar !== null ? (float) $l->longitude_keluar : null,

            'nama'          => $nama,
            'nama_karyawan' => $nama,
            'nama_pegawai'  => $nama,
            'unit'          => $unitNama,
            'unit_kerja'    => $unitNama,
            'nama_unit'     => $unitNama,

            'waktu_mulai'   => $mulai?->format('Y-m-d H:i'),
            'waktu_selesai' => $selesai?->format('Y-m-d H:i'),
            'jam_mulai'     => $mulai?->format('H:i'),
            'jam_selesai'   => $selesai?->format('H:i'),
            'tanggal'       => $mulai?->format('d/m/Y'),

            'total_jam'  => $l->total_jam_lembur,
            'durasi_jam' => $l->total_jam_lembur,
            'status'     => $l->status_validasi,

            'foto'            => $l->foto_masuk ? url('storage/' . $l->foto_masuk) : null,
            'foto_masuk_url'  => $l->foto_masuk ? url('storage/' . $l->foto_masuk) : null,
            'foto_keluar_url' => $l->foto_keluar ? url('storage/' . $l->foto_keluar) : null,
            'foto_profil'     => $fotoProfil,

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

    private function normalizePengambilanJam(PengambilanLembur $p): array
    {
        $user      = $p->user;
        $unit      = $user?->unitKerja;
        $validator = $p->validator;

        $nama     = $user?->name ?? '-';
        $nik      = $user?->nik ?? '-';
        $unitNama = $unit?->nama_unit ?? '-';

        $fotoProfil = null;
        if ($user) {
            $pathFoto = $user->foto_profil ?? $user->foto ?? null;
            $fotoProfil = $pathFoto ? url('storage/' . $pathFoto) : null;
        }

        $jamFormat  = intdiv($p->durasi_menit, 60);
        $sisaMenit  = $p->durasi_menit % 60;
        $durasiTeks = ($jamFormat > 0 ? "{$jamFormat} jam " : '') . ($sisaMenit > 0 ? "{$sisaMenit} menit" : '');

        return array_merge($p->toArray(), [
            'pegawai_nama'        => $nama,
            'pegawai_nik'         => $nik,
            'unit_nama'           => $unitNama,
            'nama'                => $nama,
            'nama_karyawan'       => $nama,
            'nama_pegawai'        => $nama,
            'unit'                => $unitNama,
            'unit_kerja'          => $unitNama,
            'nama_unit'           => $unitNama,
            'tanggal'             => $p->tanggal_dinas ? Carbon::parse($p->tanggal_dinas)->format('d/m/Y') : '-',
            'tanggal_iso'         => $p->tanggal_dinas ? Carbon::parse($p->tanggal_dinas)->format('Y-m-d') : null,
            'durasi_teks'         => trim($durasiTeks) ?: "{$p->durasi_menit} menit",
            'tipe_label'          => $p->tipe_pengambilan === 'masuk_diperlambat' ? 'Jam Masuk Diperlambat' : 'Jam Pulang Dipercepat',
            'status'              => $p->status_validasi,
            'foto_profil'         => $fotoProfil,
            'validator_nama'      => $validator?->name,
            'divalidasi_pada_fmt' => $p->divalidasi_pada ? $p->divalidasi_pada->format('d/m/Y H:i') : null,
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

    private function cariRosterShiftMalamAktif(int $userId, Carbon $now): ?JadwalRoster
    {
        $kemarin = $now->copy()->subDay()->toDateString();

        $roster = JadwalRoster::with('shift')
            ->where('user_id', $userId)
            ->where('tanggal_dinas', $kemarin)
            ->orderBy('sesi', 'desc')
            ->first();

        if (!$roster || !$roster->shift) return null;

        return ($roster->shift->jam_pulang < $roster->shift->jam_masuk) ? $roster : null;
    }

    /**
     * ✅ SESI REFERENSI untuk ekstensi — mendukung shift malam lintas hari & shift custom.
     * Urutan prioritas:
     *  1) Roster dari sesi yang masih AKTIF (belum absen pulang)
     *  2) Roster dari sesi terakhir yang SUDAH dipulangkan (≤ 36 jam)
     *  3) Roster kemarin/hari ini yang jendela pulangnya sudah lewat
     */
    private function rosterReferensiEkstensi(int $userId, Carbon $now, ?LogAbsensi $logAktif): ?JadwalRoster
    {
        if ($logAktif && $logAktif->roster_id) {
            $r = JadwalRoster::with('shift')->find($logAktif->roster_id);
            if ($r) return $r;
        }

        $logSelesai = LogAbsensi::where('user_id', $userId)
            ->whereNotNull('waktu_pulang')
            ->where('waktu_pulang', '>=', $now->copy()->subHours(36))
            ->orderByDesc('waktu_pulang')
            ->first();

        if ($logSelesai && $logSelesai->roster_id) {
            $r = JadwalRoster::with('shift')->find($logSelesai->roster_id);
            if ($r) return $r;
        }

        $candidates = JadwalRoster::with('shift')
            ->where('user_id', $userId)
            ->whereBetween('tanggal_dinas', [
                $now->copy()->subDay()->toDateString(),
                $now->toDateString(),
            ])
            ->orderByDesc('tanggal_dinas')
            ->orderByDesc('sesi')
            ->get();

        foreach ($candidates as $r) {
            [, $winEnd] = \App\Http\Controllers\Api\AbsensiController::jendelaSesi($r);
            if ($now->gte($winEnd)) return $r;
        }

        return null;
    }
}
