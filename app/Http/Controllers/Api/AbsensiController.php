<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JadwalRoster;
use App\Models\LogAbsensi;
use App\Models\MasterShift;
use App\Models\PengaturanAplikasi;
use App\Models\User;
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
        if (($invalid = $this->validateAttendancePayload($request, 'Absensi')) !== null) {
            return $invalid;
        }

        $user  = $request->user();
        $now   = Carbon::now();
        $today = $now->toDateString();

        // ===== 1) GUARD SADAR-SESI =====
        // Cek apakah ada log aktif (belum pulang) dari sesi yang JENDELANYA MASIH TERBUKA
        $logAktif = LogAbsensi::with('roster.shift')
            ->where('user_id', $user->id)
            ->whereNull('waktu_pulang')
            ->where('waktu_masuk', '>=', $now->copy()->subHours(24))
            ->first();

        if ($logAktif) {
            $masihDalamJendela = false;

            if ($logAktif->roster_id) {
                $rosterLog = $logAktif->roster;
                if ($rosterLog) {
                    [, $winEnd] = self::jendelaSesi($rosterLog);
                    // Sesi masih aktif bila belum melewati jam pulang + 30 menit toleransi
                    if ($now->lte($winEnd->copy()->addMinutes(30))) {
                        $masihDalamJendela = true;
                    }
                }
            } else {
                // Log tanpa roster (Tanpa Jadwal) — anggap masih aktif bila < 16 jam
                if ($logAktif->waktu_masuk->diffInHours($now) < 16) {
                    $masihDalamJendela = true;
                }
            }

            if ($masihDalamJendela) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sesi dinas Anda masih aktif. Lakukan absen pulang terlebih dahulu.',
                ], 422);
            }
            // Kalau sudah lewat jendela → izinkan absen sesi baru (anggap auto-pulang / lupa absen pulang)
        }

        // ===== 2) Ambil semua sesi hari ini =====
        $rostersHariIni = JadwalRoster::with('shift')
            ->where('user_id', $user->id)
            ->whereDate('tanggal_dinas', $today)
            ->orderBy('sesi')
            ->get();

        if ($rostersHariIni->isEmpty()) {
            $malam = $this->cariRosterShiftMalamAktif($user->id, $now);
            if ($malam) $rostersHariIni = collect([$malam]);
        }

        // Pre-query sesi yang sudah diabsen hari ini untuk mencegah query berulang dalam loop (N+1)
        $usedRosterIds = $rostersHariIni->isNotEmpty()
            ? LogAbsensi::whereIn('roster_id', $rostersHariIni->pluck('id'))
            ->whereNotNull('waktu_masuk')
            ->pluck('roster_id')
            ->all()
            : [];

        // ===== 3) Cari sesi TARGET: dalam jendela & belum diabsen =====
        $target = null;
        foreach ($rostersHariIni as $r) {
            if (in_array($r->id, $usedRosterIds)) continue;

            [$winStart, $winEnd] = self::jendelaSesi($r);

            // ✅ FIX: harus dalam jendela (bawah & atas)
            if ($now->gte($winStart) && $now->lte($winEnd)) {
                $target = $r;
                break;
            }
        }

        $isPerubahanJadwal = false;

        // ===== 3.5) Auto-Detect Shift / Perubahan Jadwal Dinas =====
        // Bila tidak cocok dengan jadwal saat ini (misal tukar dinas mendadak atau belum diupdate admin):
        if (!$target) {
            $matchedShift = null;

            // Prioritas 1: Jika request mengirimkan shift_id tertentu (pilihan manual karyawan)
            if ($request->filled('shift_id')) {
                $matchedShift = MasterShift::find($request->input('shift_id'));
            }

            // Prioritas 2: Deteksi otomatis shift aktif di unit kerja/RS yang sesuai jam kedatangan
            if (!$matchedShift) {
                $matchedShift = $this->cariShiftSesuaiWaktu($user, $now);
            }

            if ($matchedShift) {
                // Cari apakah ada sesi jadwal hari ini yang belum diabsen (misal jadwal lama sebelum ditukar)
                $unusedRoster = $rostersHariIni->first(fn($r) => !in_array($r->id, $usedRosterIds));

                if ($unusedRoster) {
                    // Update sesi lama yang belum terpakai ke shift aktual
                    $unusedRoster->update([
                        'shift_id'          => $matchedShift->id,
                        'custom_jam_masuk'  => null,
                        'custom_jam_pulang' => null,
                        'custom_nama_shift' => null,
                    ]);
                    $unusedRoster->load('shift');
                    $target = $unusedRoster;
                } else {
                    // Buat sesi jadwal dinas baru untuk hari ini
                    $nextSesi = ($rostersHariIni->max('sesi') ?? 0) + 1;
                    $target = JadwalRoster::create([
                        'user_id'       => $user->id,
                        'tanggal_dinas' => $today,
                        'sesi'          => $nextSesi > 0 ? $nextSesi : 1,
                        'shift_id'      => $matchedShift->id,
                    ]);
                    $target->load('shift');
                }
                $isPerubahanJadwal = true;
            }
        }

        // ===== 4) Jika masih tidak ada target, cek sesi yang AKAN DATANG / SELESAI =====
        if (!$target) {
            $sesiAkanDatang = null;
            foreach ($rostersHariIni as $r) {
                if (in_array($r->id, $usedRosterIds)) continue;

                [$winStart,] = self::jendelaSesi($r);
                if ($now->lt($winStart)) {
                    $sesiAkanDatang = $r;
                    break;
                }
            }

            $isLuarJadwalReq = $request->boolean('is_luar_jadwal') || $request->input('mode') === 'luar_jadwal';

            if ($sesiAkanDatang && !$isLuarJadwalReq) {
                [$winStart,] = self::jendelaSesi($sesiAkanDatang);
                $jamMulai = $winStart->copy()->addMinutes(self::MASUK_CEPAT_MAKS_MENIT)->format('H:i');
                return response()->json([
                    'success' => false,
                    'message' => "Belum waktunya absen. Sesi {$sesiAkanDatang->sesi} baru bisa diabsen mulai pukul {$jamMulai} (atau gunakan menu Tukar Shift jika ada perubahan jadwal dinas).",
                ], 422);
            }

            // Semua sesi sudah selesai → arahkan ke On-Call atau opsi tukar shift
            $logSelesaiHariIni = LogAbsensi::where('user_id', $user->id)
                ->whereDate('waktu_masuk', $today)
                ->whereNotNull('waktu_pulang')
                ->exists();

            if (($rostersHariIni->isNotEmpty() || $logSelesaiHariIni) && !$isLuarJadwalReq) {
                return response()->json([
                    'success' => false,
                    'message' => 'Absensi jadwal dinas hari ini sudah selesai. Untuk tugas tambahan setelah pulang, gunakan menu On-Call atau pilih Tukar Shift jika dinas pengganti.',
                ], 422);
            }
        }

        // ===== 5) Cek radius =====
        $cekRadius = $this->verifikasiRadius($request, 'Absen masuk');
        if ($cekRadius !== true) return $cekRadius;

        $fotoPath = $request->hasFile('foto')
            ? $request->file('foto')->store('absensi/masuk', 'public')
            : null;

        $jenisAbsen = $isPerubahanJadwal
            ? 'perubahan_jadwal'
            : ($target ? 'dalam_radius' : 'luar_jadwal');

        // ===== 6) Simpan log =====
        $log = LogAbsensi::create([
            'user_id'          => $user->id,
            'roster_id'        => $target?->id,
            'tanggal_dinas'    => $target?->tanggal_dinas ?? $today,
            'waktu_masuk'      => $now,
            'latitude_masuk'   => $request->latitude,
            'longitude_masuk'  => $request->longitude,
            'foto_masuk'       => $fotoPath,
            'ip_address_masuk' => $request->ip(),
            'jenis_absen'      => $jenisAbsen,
            'menit_terlambat'  => 0,
            'status_kehadiran' => 'Tanpa Jadwal',
        ]);

        self::recalculateStatus($log);

        $namaShiftTeks = $target?->shift?->nama_shift ?? $target?->custom_nama_shift ?? ($target ? ('Sesi ' . ($target->sesi ?? 1)) : 'Tanpa Jadwal');

        if ($isPerubahanJadwal) {
            $pesan = "Absen masuk berhasil dicatat ({$namaShiftTeks} - Perubahan Jadwal).";
        } elseif ($target) {
            $pesan = "Absen masuk berhasil dicatat ({$namaShiftTeks}).";
        } else {
            $pesan = 'Absen masuk dicatat. Belum ada jadwal dinas — status akan menyesuaikan setelah jadwal dibuat.';
        }

        return response()->json([
            'success' => true,
            'message' => $pesan,
            'data' => [
                'waktu_masuk'          => $now->format('H:i'),
                'roster_ada'           => $target !== null,
                'sesi'                 => $target?->sesi ?? null,
                'nama_shift'           => $namaShiftTeks,
                'is_perubahan_jadwal'  => $isPerubahanJadwal,
                'status'               => $log->status_kehadiran,
                'menit_terlambat'      => $log->menit_terlambat,
            ],
        ]);
    }

    /**
     * ✅ ABSEN PULANG — juga wajib dalam radius (dinamis dari Pengaturan Sistem).
     */
    public function clockOut(Request $request)
    {
        if (($invalid = $this->validateAttendancePayload($request, 'Absen pulang')) !== null) {
            return $invalid;
        }

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
            'waktu_pulang'      => $now,
            'latitude_pulang'   => $request->latitude,
            'longitude_pulang'  => $request->longitude,
            'foto_pulang'       => $fotoPath,
            'ip_address_pulang' => $request->ip(),
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
            ->whereDate('tanggal_dinas', $today)
            ->orderBy('sesi')
            ->get()
            ->last();

        if (!$roster) {
            $roster = $this->cariRosterShiftMalamAktif($user->id, $now);
        }

        // Daftar Master Shift yang tersedia untuk unit kerja user (untuk opsi Tukar Shift)
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

        if (!$roster || !$roster->shift) {
            return response()->json([
                'success' => true,
                'data'    => [
                    'roster_ada'       => false,
                    'pesan'            => 'Anda belum memiliki jadwal dinas. Absensi tetap bisa dilakukan.',
                    'available_shifts' => $availableShifts,
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'roster_ada'       => true,
                'nama_shift'       => $roster->shift->nama_shift,
                'jam_masuk'        => substr((string) $roster->shift->jam_masuk, 0, 5),
                'jam_pulang'       => substr((string) $roster->shift->jam_pulang, 0, 5),
                'tanggal_dinas'    => $roster->tanggal_dinas,
                'available_shifts' => $availableShifts,
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

        // ✅ OVERNIGHT SHIFT: jika jam pulang lebih kecil dari jam masuk dan absen masuk di dini hari (< 12:00)
        $jamPulang = $roster->custom_jam_pulang ?? ($roster->shift ? (string) $roster->shift->jam_pulang : null);
        if ($jamPulang && $jamMasuk && $jamPulang < $jamMasuk && $log->waktu_masuk->hour < 12) {
            $expected->subDay();
        }

        $selisih   = (int) floor($expected->diffInMinutes($log->waktu_masuk, false));

        // ✅ Cek apakah ada kompensasi jam masuk diperlambat (Time-Off in Lieu) yang telah disetujui
        $kompensasiMasuk = \App\Models\PengambilanLembur::where('user_id', $log->user_id)
            ->whereDate('tanggal_dinas', $tanggalStr)
            ->where('tipe_pengambilan', 'masuk_diperlambat')
            ->where('status_validasi', 'Disetujui')
            ->first();

        $menitKompensasi = $kompensasiMasuk ? (int) $kompensasiMasuk->durasi_menit : 0;
        $totalToleransi  = $toleransi + $menitKompensasi;

        $log->menit_terlambat = ($selisih > $totalToleransi) ? (int) ($selisih - $menitKompensasi) : 0;

        $log->status_kehadiran = $log->menit_terlambat > 0
            ? 'Terlambat'
            : 'Tepat Waktu';

        if ($log->waktu_masuk && $log->waktu_pulang) {
            $log->durasi_kerja = $log->waktu_masuk->diffInMinutes($log->waktu_pulang);
        }

        $log->save();
    }

    /**
     * Validasi payload presensi (koordinat, mock GPS, foto, dan akurasi sinyal).
     * Web Mobile (/app/api/*) diberi batas akurasi 150m untuk akomodasi Wi-Fi indoor iPhone Safari.
     */
    private function validateAttendancePayload(Request $request, string $label = 'Absensi')
    {
        $request->validate([
            'latitude'       => 'required|numeric|between:-90,90',
            'longitude'      => 'required|numeric|between:-180,180',
            'foto'           => 'required|image|mimes:jpeg,png,jpg,webp|max:5120',
            'accuracy'       => 'nullable|numeric|between:0,10000',
            'is_mock'        => 'nullable|string',
            'shift_id'       => 'nullable|integer',
            'is_luar_jadwal' => 'nullable',
            'mode'           => 'nullable|string',
        ]);

        // Cek indikasi manipulasi lokasi palsu (Fake GPS / Mock)
        if ($request->boolean('is_mock') || $request->input('is_mock') === '1') {
            return response()->json([
                'success' => false,
                'message' => $label . ' ditolak: terdeteksi penggunaan lokasi palsu (Mock Location / Fake GPS).',
            ], 403);
        }

        // Koordinat nol (invalid GPS)
        if ((float) $request->latitude == 0.0 && (float) $request->longitude == 0.0) {
            return response()->json([
                'success' => false,
                'message' => $label . ' ditolak: koordinat GPS tidak valid (0,0). Pastikan GPS HP Anda aktif.',
            ], 422);
        }

        // Cek akurasi sinyal GPS (Safari indoor Wi-Fi iPhone bisa 60-140m)
        $maxAccuracy = $request->is('app/api/*') ? 150.0 : 80.0;
        if ($request->filled('accuracy')) {
            $acc = (float) $request->accuracy;
            if ($acc > $maxAccuracy) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akurasi GPS rendah (' . round($acc) . 'm). Silakan pindah ke dekat jendela atau area terbuka lalu tekan Refresh GPS.',
                ], 422);
            }
        }

        return null;
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
            ->whereDate('tanggal_dinas', $kemarin)
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

    /**
     * ✅ Cari MasterShift yang aktif / sesuai dengan jam saat ini ($now).
     * Memprioritaskan shift milik unit kerja user, lalu shift umum.
     * Mengabaikan shift libur/off.
     */
    private function cariShiftSesuaiWaktu($user, Carbon $now): ?MasterShift
    {
        $unitId = $user->unit_kerja_id;

        $shifts = MasterShift::query()
            ->when($unitId, function ($q) use ($unitId) {
                $q->where(function ($sub) use ($unitId) {
                    $sub->where('unit_kerja_id', $unitId)
                        ->orWhereNull('unit_kerja_id');
                });
            })
            ->whereNotNull('jam_masuk')
            ->whereNotNull('jam_pulang')
            ->orderBy('jam_masuk')
            ->get();

        if ($shifts->isEmpty()) {
            $shifts = MasterShift::whereNotNull('jam_masuk')
                ->whereNotNull('jam_pulang')
                ->orderBy('jam_masuk')
                ->get();
        }

        if ($shifts->isEmpty()) {
            return null;
        }

        $today = $now->toDateString();
        $matchedShifts = [];

        foreach ($shifts as $s) {
            $namaShiftLow = strtolower(trim($s->nama_shift ?? ''));
            if ($namaShiftLow === 'off' || str_contains($namaShiftLow, 'libur')) {
                continue;
            }

            $jamMasukStr  = substr((string) $s->jam_masuk, 0, 5);
            $jamPulangStr = substr((string) $s->jam_pulang, 0, 5);

            $isOvernight = ($jamPulangStr < $jamMasukStr);

            if ($isOvernight) {
                // Shift malam lintas hari (contoh 21:00 - 07:00)
                if ($now->hour < 12) {
                    // Pagi dini hari (misal 00:00 - 07:00) -> sesi dinas berawal dari hari kemarin
                    $start = Carbon::parse($now->copy()->subDay()->toDateString() . ' ' . $jamMasukStr)
                        ->subMinutes(self::MASUK_CEPAT_MAKS_MENIT);
                    $end   = Carbon::parse($today . ' ' . $jamPulangStr);
                    $expected = Carbon::parse($now->copy()->subDay()->toDateString() . ' ' . $jamMasukStr);
                } else {
                    // Sore/malam hari (misal 19:00 - 23:59) -> sesi dinas malam hari ini
                    $start = Carbon::parse($today . ' ' . $jamMasukStr)
                        ->subMinutes(self::MASUK_CEPAT_MAKS_MENIT);
                    $end   = Carbon::parse($now->copy()->addDay()->toDateString() . ' ' . $jamPulangStr);
                    $expected = Carbon::parse($today . ' ' . $jamMasukStr);
                }
            } else {
                // Shift reguler dalam 1 hari kalender (contoh 07:00 - 14:00)
                $start = Carbon::parse($today . ' ' . $jamMasukStr)
                    ->subMinutes(self::MASUK_CEPAT_MAKS_MENIT);
                $end   = Carbon::parse($today . ' ' . $jamPulangStr);
                $expected = Carbon::parse($today . ' ' . $jamMasukStr);
            }

            if ($now->gte($start) && $now->lte($end)) {
                $diff = abs($now->diffInMinutes($expected));
                $isUnit = ($unitId && $s->unit_kerja_id == $unitId);
                $matchedShifts[] = [
                    'shift'   => $s,
                    'diff'    => $diff,
                    'is_unit' => $isUnit,
                ];
            }
        }

        if (empty($matchedShifts)) {
            return null;
        }

        // Urutkan: utamakan shift milik unit kerja sendiri, lalu selisih waktu terdekat ke jam masuk
        usort($matchedShifts, function ($a, $b) {
            if ($a['is_unit'] !== $b['is_unit']) {
                return $a['is_unit'] ? -1 : 1;
            }
            return $a['diff'] <=> $b['diff'];
        });

        return $matchedShifts[0]['shift'];
    }
}
