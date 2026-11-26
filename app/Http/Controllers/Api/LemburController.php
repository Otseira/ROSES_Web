<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LogLembur;
use App\Models\PengambilanLembur;
use App\Models\LogAbsensi;
use App\Models\JadwalRoster;
use App\Models\MasterShift;
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
            'foto_masuk'   => 'required|image|mimes:jpeg,png,jpg,webp|max:5120',
            'durasi_menit' => 'nullable|integer|min:15|max:720',
            'accuracy'     => 'nullable|numeric|between:0,10000',
            'is_mock'      => 'nullable|string',
        ]);

        if (($gpsError = $this->checkGpsIntegrity($request, 'Lembur ekstensi')) !== null) {
            return $gpsError;
        }

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

        // 1. Cari log absensi hari ini (aktif / belum pulang, atau tanggal dinas hari ini, atau waktu_masuk hari ini)
        $logDinas = LogAbsensi::where('user_id', $user->id)
            ->where(function ($q) use ($today, $now) {
                $q->where(function ($sub) use ($now) {
                    $sub->whereNull('waktu_pulang')
                        ->where('waktu_masuk', '>=', $now->copy()->subHours(24));
                })
                ->orWhereDate('tanggal_dinas', $today)
                ->orWhereDate('waktu_masuk', $today);
            })
            ->latest('waktu_masuk')
            ->first();

        // 2. Cari Jadwal Roster hari ini
        $roster = JadwalRoster::with('shift')
            ->where('user_id', $user->id)
            ->whereDate('tanggal_dinas', $today)
            ->first();

        if (!$roster) {
            $kemarin = $now->copy()->subDay()->toDateString();
            $rosterKemarin = JadwalRoster::with('shift')
                ->where('user_id', $user->id)
                ->whereDate('tanggal_dinas', $kemarin)
                ->first();
            if ($rosterKemarin && $rosterKemarin->shift && $rosterKemarin->shift->jam_pulang < $rosterKemarin->shift->jam_masuk) {
                $roster = $rosterKemarin;
            }
        }

        $shiftJamMasuk  = $roster?->custom_jam_masuk ?? ($roster?->shift ? (string) $roster->shift->jam_masuk : null);
        $shiftJamPulang = $roster?->custom_jam_pulang ?? ($roster?->shift ? (string) $roster->shift->jam_pulang : null);

        if (!$logDinas && (!$roster || empty($shiftJamMasuk))) {
            return response()->json([
                'success' => false,
                'message' => 'Anda belum melakukan absensi masuk kerja dan belum memiliki jadwal dinas hari ini.',
            ], 422);
        }

        // Tentukan jam masuk patokan: utamakan log absensi aktual, bila belum absen gunakan jam masuk dari jadwal dinas
        if ($logDinas && $logDinas->waktu_masuk) {
            $waktuMasuk = Carbon::parse($logDinas->waktu_masuk);
        } else {
            $tglRoster = $roster->tanggal_dinas ? Carbon::parse($roster->tanggal_dinas)->toDateString() : $today;
            $waktuMasuk = Carbon::parse($tglRoster . ' ' . $shiftJamMasuk);
        }

        // ✅ ATURAN SELURUH UNIT: Jam dinas karyawan sudah melebihi 6 jam 40 menit (400 menit)
        $ambangLembur  = $waktuMasuk->copy()->addMinutes(400); // 6 jam 40 menit
        $menitBerjalan = $waktuMasuk->diffInMinutes($now);

        if ($now->lessThan($ambangLembur)) {
            $jamBisa = $ambangLembur->format('H:i');
            $berjalanJam = intdiv($menitBerjalan, 60);
            $berjalanSisa = $menitBerjalan % 60;
            return response()->json([
                'success' => false,
                'message' => "Lembur hanya dapat diajukan jika jam dinas sudah melebihi 6 jam 40 menit (dapat diajukan mulai pukul {$jamBisa} WIB). Jam dinas Anda baru berjalan {$berjalanJam} jam {$berjalanSisa} menit.",
            ], 422);
        }

        // Durasi otomatis: dari setelah 6 jam 40 menit dinas s/d sekarang
        $durasiOtomatis = max(0, intdiv($now->getTimestamp() - $ambangLembur->getTimestamp(), 60));
        $maxMenit       = max(15, $durasiOtomatis);

        $durasiMenit = $request->filled('durasi_menit') && (int) $request->durasi_menit > 0
            ? (int) $request->durasi_menit
            : $durasiOtomatis;

        if ($durasiMenit < 15) {
            return response()->json(['success' => false, 'message' => 'Durasi lembur minimal 15 menit.'], 422);
        }
        if ($durasiMenit > $maxMenit) {
            return response()->json([
                'success' => false,
                'message' => "Durasi melebihi batas jam kerja lembur Anda. Maksimal {$maxMenit} menit (dihitung setelah dinas 6 jam 40 menit s/d sekarang).",
            ], 422);
        }

        // ✅ CEK RADIUS (seragam, dinamis dari Pengaturan Sistem)
        $cekRadius = $this->verifikasiRadius($request, 'Lembur ekstensi');
        if ($cekRadius !== true) return $cekRadius;

        // Simpan foto lembur
        $file = $request->file('foto_masuk');
        $path = $file->storeAs('lembur_masuk', 'lembur_' . ($user->nik ?? $user->id) . '_' . time() . '.' . $file->extension(), 'public');

        $waktuSelesai = $now;
        // Jika durasi sama persis dengan durasi otomatis: mulai tepat dari setelah dinas 6 jam 40 menit
        if ($durasiMenit === $durasiOtomatis) {
            $waktuMulai = $ambangLembur;
        } else {
            $waktuMulai = $now->copy()->subMinutes($durasiMenit);
        }
        $totalJam = round($durasiMenit / 60, 2);

        // ✅ AUTO CLOCK-OUT: tutup sesi dinas reguler jika masih aktif belum absen pulang
        $autoClockOut = false;
        if ($logDinas && is_null($logDinas->waktu_pulang)) {
            $logDinas->waktu_pulang      = $now;
            $logDinas->foto_pulang       = $path;
            $logDinas->latitude_pulang   = $request->latitude;
            $logDinas->longitude_pulang  = $request->longitude;
            $logDinas->save();
            AbsensiController::recalculateStatus($logDinas);
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
            'accuracy_masuk'       => $request->accuracy,
            'is_mock_masuk'        => $request->filled('is_mock') ? (int) $request->is_mock : 0,
        ]);

        $responseData = $this->normalizeLembur($lembur->load('user.unitKerja'));
        $responseData['auto_clock_out'] = $autoClockOut;

        return response()->json([
            'success' => true,
            'message' => ($autoClockOut ? '✓ Absen pulang tercatat otomatis. ' : '') . '✓ Lembur ekstensi berhasil disimpan.',
            'data'    => $responseData,
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
            'foto_masuk' => 'required|image|mimes:jpeg,png,jpg,webp|max:5120',
            'accuracy'   => 'nullable|numeric|between:0,10000',
            'is_mock'    => 'nullable|string',
        ]);

        if (($gpsError = $this->checkGpsIntegrity($request, 'On-Call masuk')) !== null) {
            return $gpsError;
        }

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
            'foto_keluar' => 'required|image|mimes:jpeg,png,jpg,webp|max:5120',
            'accuracy'    => 'nullable|numeric|between:0,10000',
            'is_mock'     => 'nullable|string',
        ]);

        if (($gpsError = $this->checkGpsIntegrity($request, 'On-Call keluar')) !== null) {
            return $gpsError;
        }

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

        // ✅ AUTO CLOCK-OUT sesi dinas aktif (jika belum absen pulang)
        $logAbsen = LogAbsensi::where('user_id', $user->id)
            ->whereNull('waktu_pulang')
            ->where('waktu_masuk', '>=', $now->copy()->subHours(36))
            ->latest('waktu_masuk')
            ->first();

        $autoClockOut = false;
        if ($logAbsen) {
            $logAbsen->waktu_pulang      = $now;
            $logAbsen->foto_pulang       = $path;
            $logAbsen->latitude_pulang   = $request->latitude;
            $logAbsen->longitude_pulang  = $request->longitude;
            $logAbsen->save();
            AbsensiController::recalculateStatus($logAbsen);
            $autoClockOut = true;
        }

        $lembur->update([
            'waktu_selesai_lembur' => $now,
            'total_jam_lembur'     => $totalJam,
            'latitude_keluar'      => $request->latitude,
            'longitude_keluar'     => $request->longitude,
            'foto_keluar'          => $path,
        ]);

        $responseData = $this->normalizeLembur($lembur->load('user.unitKerja'));
        $responseData['auto_clock_out'] = $autoClockOut;

        return response()->json([
            'success' => true,
            'message' => ($autoClockOut ? '✓ Absen pulang tercatat otomatis. ' : '') . '✓ On-Call selesai dicatat.',
            'data'    => $responseData,
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

    /** Info sesi dinas & ambang lembur 6 jam 40 menit (berlaku untuk seluruh unit). */
    public function infoShiftHariIni(Request $request)
    {
        $user  = $request->user();
        $now   = Carbon::now();
        $today = $now->toDateString();

        // 1. Cari log absensi hari ini (aktif / belum pulang, atau tanggal dinas hari ini, atau waktu_masuk hari ini)
        $logDinas = LogAbsensi::where('user_id', $user->id)
            ->where(function ($q) use ($today, $now) {
                $q->where(function ($sub) use ($now) {
                    $sub->whereNull('waktu_pulang')
                        ->where('waktu_masuk', '>=', $now->copy()->subHours(24));
                })
                ->orWhereDate('tanggal_dinas', $today)
                ->orWhereDate('waktu_masuk', $today);
            })
            ->latest('waktu_masuk')
            ->first();

        // 2. Cari Jadwal Roster hari ini
        $roster = JadwalRoster::with('shift')
            ->where('user_id', $user->id)
            ->whereDate('tanggal_dinas', $today)
            ->first();

        if (!$roster) {
            $kemarin = $now->copy()->subDay()->toDateString();
            $rosterKemarin = JadwalRoster::with('shift')
                ->where('user_id', $user->id)
                ->whereDate('tanggal_dinas', $kemarin)
                ->first();
            if ($rosterKemarin && $rosterKemarin->shift && $rosterKemarin->shift->jam_pulang < $rosterKemarin->shift->jam_masuk) {
                $roster = $rosterKemarin;
            }
        }

        $shiftJamMasuk  = $roster?->custom_jam_masuk ?? ($roster?->shift ? (string) $roster->shift->jam_masuk : null);
        $shiftJamPulang = $roster?->custom_jam_pulang ?? ($roster?->shift ? (string) $roster->shift->jam_pulang : null);
        $shiftNama      = $roster?->custom_nama_shift ?? $roster?->shift?->nama_shift ?? 'Dinas Shift';

        // Daftar Master Shift yang tersedia untuk unit kerja user (kompatibilitas antarmuka)
        $unitId = $user->unit_kerja_id;
        $availableShifts = MasterShift::query()
            ->when($unitId, function ($q) use ($unitId) {
                $q->where(function ($sub) use ($unitId) {
                    $sub->where('unit_kerja_id', $unitId)
                        ->orWhereNull('unit_kerja_id');
                });
            })
            ->whereNotNull('jam_masuk')
            ->whereNotNull('jam_pulang')
            ->orderBy('jam_masuk')
            ->get(['id', 'nama_shift', 'jam_masuk', 'jam_pulang'])
            ->filter(function ($s) {
                $low = strtolower(trim($s->nama_shift ?? ''));
                return $low !== 'off' && !str_contains($low, 'libur');
            })
            ->values()
            ->map(function ($s) {
                return [
                    'id'         => $s->id,
                    'nama_shift' => $s->nama_shift,
                    'jam_masuk'  => substr((string) $s->jam_masuk, 0, 5),
                    'jam_pulang' => substr((string) $s->jam_pulang, 0, 5),
                ];
            });

        if (!$logDinas && (!$roster || empty($shiftJamMasuk))) {
            return response()->json([
                'success' => true,
                'data'    => [
                    'roster_ada'       => (bool) $roster,
                    'available_shifts' => $availableShifts,
                    'bisa_ekstensi'    => false,
                    'pesan'            => $roster ? 'Jadwal dinas Anda hari ini adalah Libur / Off.' : 'Anda belum melakukan absensi masuk dinas dan belum memiliki jadwal dinas hari ini.',
                ],
            ], 200);
        }

        // Tentukan jam masuk patokan: utamakan log absensi aktual, bila belum absen gunakan jam masuk dari jadwal dinas
        if ($logDinas && $logDinas->waktu_masuk) {
            $waktuMasuk = Carbon::parse($logDinas->waktu_masuk);
            $sumberMasuk = 'aktual';
        } else {
            $tglRoster = $roster->tanggal_dinas ? Carbon::parse($roster->tanggal_dinas)->toDateString() : $today;
            $waktuMasuk = Carbon::parse($tglRoster . ' ' . $shiftJamMasuk);
            $sumberMasuk = 'jadwal';
        }

        $ambangLembur  = $waktuMasuk->copy()->addMinutes(400); // 6 jam 40 menit dinas
        $menitBerjalan = $waktuMasuk->diffInMinutes($now);
        $bisaEkstensi  = ($now->greaterThanOrEqualTo($ambangLembur));
        $durasiOtomatis = max(0, intdiv($now->getTimestamp() - $ambangLembur->getTimestamp(), 60));

        $jamFormat = intdiv($durasiOtomatis, 60);
        $sisaMenit = $durasiOtomatis % 60;
        $teksJam   = ($jamFormat > 0 ? "{$jamFormat} Jam " : '') . ($sisaMenit > 0 ? "{$sisaMenit} Menit" : ($jamFormat > 0 ? '' : '0 Menit'));

        return response()->json([
            'success' => true,
            'data'    => [
                'roster_ada'            => (bool) $roster,
                'available_shifts'      => $availableShifts,
                'nama_shift'            => $shiftNama,
                'jam_masuk'             => $waktuMasuk->format('H:i'),
                'jam_pulang'            => $ambangLembur->format('H:i'),
                'jam_ambang_lembur'     => $ambangLembur->format('H:i'),
                'menit_berjalan'        => $menitBerjalan,
                'max_menit'             => max(15, $durasiOtomatis),
                'durasi_otomatis_menit' => $durasiOtomatis,
                'durasi_otomatis_teks'  => trim($teksJam),
                'min_menit'             => 15,
                'bisa_ekstensi'         => $bisaEkstensi,
                'sumber_masuk'          => $sumberMasuk,
                'tanggal'               => $waktuMasuk->format('Y-m-d'),
                'tanggal_dinas'         => $roster?->tanggal_dinas ? Carbon::parse($roster->tanggal_dinas)->toDateString() : $today,
                'pesan'                 => $bisaEkstensi
                    ? 'Syarat 6 jam 40 menit dinas terpenuhi. Anda dapat mengajukan lembur.'
                    : 'Lembur baru dapat diajukan setelah dinas minimal 6 jam 40 menit (mulai ' . $ambangLembur->format('H:i') . ' WIB).',
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

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('waktu_mulai_lembur', [
                Carbon::parse($request->start_date)->startOfDay(),
                Carbon::parse($request->end_date)->endOfDay(),
            ]);
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
        $roleAllowed = ['kepala_unit', 'penanggung_jawab', 'manajer', 'manager', 'hrd', 'superadmin', 'direktur'];
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

        if (in_array($user->role, ['kepala_unit', 'penanggung_jawab', 'manajer', 'manager'])) {
            $unitIds = $user->managesUnits()->pluck('master_unit_kerjas.id');
            if ($user->unit_kerja_id && !$unitIds->contains($user->unit_kerja_id)) {
                $unitIds->push($user->unit_kerja_id);
            }
            if ($unitIds->isEmpty()) {
                $unitIds = collect([$user->unit_kerja_id]);
            }
            $query->whereHas('user', fn($q) => $q->whereIn('unit_kerja_id', $unitIds));
        }

        if ($request->filled('unit_id') && $request->unit_id !== 'all' && $request->unit_id !== '') {
            $query->whereHas('user', fn($q) => $q->where('unit_kerja_id', $request->unit_id));
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
        $roleAllowed = ['kepala_unit', 'penanggung_jawab', 'manajer', 'manager', 'hrd', 'superadmin', 'direktur'];
        if (!in_array($user->role, $roleAllowed)) {
            return response()->json(['success' => false, 'message' => 'Tidak memiliki hak akses.'], 403);
        }

        $raw  = $request->input('status')
            ?? $request->input('status_validasi')
            ?? $request->input('action')
            ?? $request->input('aksi')
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

        if (in_array($user->role, ['kepala_unit', 'penanggung_jawab', 'manajer', 'manager'])) {
            $unitIds = $user->managesUnits()->pluck('master_unit_kerjas.id');
            if ($user->unit_kerja_id && !$unitIds->contains($user->unit_kerja_id)) {
                $unitIds->push($user->unit_kerja_id);
            }
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

        $fmtDurasi = function (int $mnt): string {
            $j = intdiv($mnt, 60);
            $m = $mnt % 60;
            if ($j > 0 && $m > 0) return "{$j} Jam {$m} Menit";
            if ($j > 0) return "{$j} Jam";
            return "{$m} Menit";
        };
        $teksSaldoJam     = $fmtDurasi($saldoTersediaMenit);
        $teksDiperoleh    = $fmtDurasi($earnedMinutes);
        $teksDigunakan    = $fmtDurasi($usedMinutes);

        return response()->json([
            'success' => true,
            'data'    => [
                'is_ganti_jam'               => ($user->unitKerja?->jenis_lembur ?? 'dibayar') === 'ganti_jam',
                'kebijakan_lembur'           => $user->unitKerja?->jenis_lembur ?? 'dibayar',
                'unit_nama'                  => $user->unitKerja?->nama_unit ?? '-',
                'saldo_awal_menit'           => $saldoAwalMenit,
                'menit_lembur_aplikasi'      => $menitLemburAplikasi,
                'keterangan_saldo'           => $user->keterangan_saldo_lembur,
                'updated_saldo_by'           => $user->updatedSaldoBy?->name,
                'updated_saldo_at'           => optional($user->updated_saldo_lembur_at)->format('d/m/Y H:i'),
                'total_menit_diperoleh'      => $earnedMinutes,
                'total_menit_digunakan'      => $usedMinutes,
                'total_menit_pending'        => $pendingMinutes,
                'saldo_menit_bersih'         => $saldoBersihMenit,
                'saldo_menit_tersedia'       => $saldoTersediaMenit,
                'saldo_bersih_teks'          => trim($teksSaldoBersih) ?: '0 menit',
                'saldo_tersedia_teks'        => trim($teksSaldoTersedia) ?: '0 menit',
                // Alias kompatibilitas untuk Web Mobile & Flutter
                'saldo_jam_teks'             => $teksSaldoJam,
                'total_lembur_disetujui_teks'=> $teksDiperoleh,
                'total_pengambilan_teks'     => $teksDigunakan,
            ],
        ], 200);
    }

    /**
     * Pegawai mengajukan pengambilan waktu lembur (masuk diperlambat / pulang dipercepat).
     */
    public function storePengambilanJam(Request $request)
    {
        // Normalisasi alias field dari Web Mobile maupun Flutter sirom
        $request->merge([
            'tanggal_dinas'    => $request->input('tanggal_dinas') ?? $request->input('tanggal_rencana'),
            'keterangan'       => $request->input('keterangan') ?? $request->input('alasan'),
            'tipe_pengambilan' => $request->input('tipe_pengambilan') ?: 'pulang_dipercepat',
        ]);

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

        // Hitung saldo tersedia (konsisten dengan getBankJam: Saldo Awal + Lembur Aplikasi - Terpakai - Pending)
        $saldoAwalMenit = (int) ($user->saldo_awal_lembur_menit ?? 0);
        $menitLemburAplikasi = (int) round(
            LogLembur::where('user_id', $user->id)
                ->where('status_validasi', 'Disetujui')
                ->sum('total_jam_lembur') * 60
        );
        $earnedMinutes = $saldoAwalMenit + $menitLemburAplikasi;
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

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('tanggal_dinas', [
                Carbon::parse($request->start_date)->format('Y-m-d'),
                Carbon::parse($request->end_date)->format('Y-m-d'),
            ]);
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
        $roleAllowed = ['kepala_unit', 'penanggung_jawab', 'manajer', 'manager', 'hrd', 'superadmin', 'direktur'];
        if (!in_array($user->role, $roleAllowed)) {
            return response()->json(['success' => false, 'message' => 'Tidak memiliki hak akses.'], 403);
        }

        $query = PengambilanLembur::with(['user.unitKerja', 'validator'])->latest('created_at');

        if ($request->filled('status')) {
            $query->where('status_validasi', $request->status);
        }

        if (in_array($user->role, ['kepala_unit', 'penanggung_jawab', 'manajer', 'manager'])) {
            $unitIds = $user->managesUnits()->pluck('master_unit_kerjas.id');
            if ($user->unit_kerja_id && !$unitIds->contains($user->unit_kerja_id)) {
                $unitIds->push($user->unit_kerja_id);
            }
            if ($unitIds->isEmpty()) {
                $unitIds = collect([$user->unit_kerja_id]);
            }
            $query->whereHas('user', fn($q) => $q->whereIn('unit_kerja_id', $unitIds));
        }

        if ($request->filled('unit_id') && $request->unit_id !== 'all' && $request->unit_id !== '') {
            $query->whereHas('user', fn($q) => $q->where('unit_kerja_id', $request->unit_id));
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
        $roleAllowed = ['kepala_unit', 'penanggung_jawab', 'manajer', 'manager', 'hrd', 'superadmin', 'direktur'];
        if (!in_array($user->role, $roleAllowed)) {
            return response()->json(['success' => false, 'message' => 'Tidak memiliki hak akses.'], 403);
        }

        $raw  = $request->input('status')
            ?? $request->input('status_validasi')
            ?? $request->input('action')
            ?? $request->input('aksi')
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

        if (in_array($user->role, ['kepala_unit', 'penanggung_jawab', 'manajer', 'manager'])) {
            $unitIds = $user->managesUnits()->pluck('master_unit_kerjas.id');
            if ($user->unit_kerja_id && !$unitIds->contains($user->unit_kerja_id)) {
                $unitIds->push($user->unit_kerja_id);
            }
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

        // Format durasi detail: "120 Menit (2 Jam)" atau "100 Menit (1 Jam 40 Menit)"
        $durasiMenit = (int) round(((float) $l->total_jam_lembur) * 60);
        $jamFormat   = intdiv($durasiMenit, 60);
        $sisaMenit   = $durasiMenit % 60;
        if ($jamFormat > 0 && $sisaMenit > 0) {
            $jamKurung = "{$jamFormat} Jam {$sisaMenit} Menit";
        } elseif ($jamFormat > 0) {
            $jamKurung = "{$jamFormat} Jam";
        } else {
            $jamKurung = "{$sisaMenit} Menit";
        }
        $durasiDetail = "{$durasiMenit} Menit ({$jamKurung})";

        // Hitung jarak ke RS
        $pengaturan = PengaturanAplikasi::first();
        $latLembur  = $l->latitude_masuk ?? $l->latitude_keluar;
        $lngLembur  = $l->longitude_masuk ?? $l->longitude_keluar;
        $jarakMeter = null;
        $jarakTeks  = null;
        if ($pengaturan && $pengaturan->latitude && $pengaturan->longitude && $latLembur !== null && $lngLembur !== null) {
            $jarakMeter = round($this->calculateDistance(
                (float) $pengaturan->latitude,
                (float) $pengaturan->longitude,
                (float) $latLembur,
                (float) $lngLembur
            ));
            $maxRadius = $this->getMaxRadius($pengaturan);
            $jarakTeks = "{$jarakMeter} meter dari RS" . ($jarakMeter <= $maxRadius ? " (Dalam Radius)" : " (Di Luar Radius)");
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

            'total_jam'     => $l->total_jam_lembur,
            'durasi_jam'    => $l->total_jam_lembur,
            'durasi_menit'  => $durasiMenit,
            'total_menit'   => $durasiMenit,
            'durasi_teks'   => $durasiDetail,
            'durasi_detail' => $durasiDetail,
            'status'        => $l->status_validasi,

            'alasan'        => $l->keterangan ?? '-',
            'alasan_lembur' => $l->keterangan ?? '-',
            'jarak_meter'   => $jarakMeter,
            'jarak_teks'    => $jarakTeks,
            'map_url'       => ($latLembur !== null && $lngLembur !== null) ? "https://www.google.com/maps?q={$latLembur},{$lngLembur}" : null,

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

        $durasiMenit = (int) $p->durasi_menit;
        $jamFormat   = intdiv($durasiMenit, 60);
        $sisaMenit   = $durasiMenit % 60;
        if ($jamFormat > 0 && $sisaMenit > 0) {
            $jamKurung = "{$jamFormat} Jam {$sisaMenit} Menit";
        } elseif ($jamFormat > 0) {
            $jamKurung = "{$jamFormat} Jam";
        } else {
            $jamKurung = "{$sisaMenit} Menit";
        }
        $durasiDetail = "{$durasiMenit} Menit ({$jamKurung})";

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
            'tanggal_rencana'     => $p->tanggal_dinas ? Carbon::parse($p->tanggal_dinas)->format('d/m/Y') : '-',
            'tanggal_iso'         => $p->tanggal_dinas ? Carbon::parse($p->tanggal_dinas)->format('Y-m-d') : null,
            'alasan'              => $p->keterangan ?? '-',
            'durasi_teks'         => $durasiDetail,
            'durasi_detail'       => $durasiDetail,
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

    /**
     * Validasi integritas koordinat GPS, mock location, dan batas akurasi.
     */
    private function checkGpsIntegrity(Request $request, string $label): ?\Illuminate\Http\JsonResponse
    {
        if ($request->boolean('is_mock') || $request->input('is_mock') === '1') {
            return response()->json([
                'success' => false,
                'message' => $label . ' ditolak: terdeteksi penggunaan lokasi palsu (Mock Location / Fake GPS).',
            ], 403);
        }

        if ((float) $request->latitude == 0.0 && (float) $request->longitude == 0.0) {
            return response()->json([
                'success' => false,
                'message' => $label . ' ditolak: koordinat GPS tidak valid (0,0).',
            ], 422);
        }

        $maxAccuracy = $request->is('app/api/*') ? 150.0 : 80.0;
        if ($request->filled('accuracy') && (float) $request->accuracy > $maxAccuracy) {
            return response()->json([
                'success' => false,
                'message' => 'Akurasi GPS rendah (' . round((float) $request->accuracy) . 'm). Silakan pindah ke dekat jendela atau area terbuka agar posisi akurat.',
            ], 422);
        }

        return null;
    }
}
