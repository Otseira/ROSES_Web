<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\JadwalRoster;
use App\Models\MasterShift;
use App\Models\LogLembur;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RosterController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'bulan' => 'required|integer|between:1,12',
            'tahun' => 'required|integer|min:2020',
            'unit_kerja_id' => 'nullable|exists:master_unit_kerjas,id', // Tambahan untuk filter spesifik
        ]);

        $user = $request->user();
        $bulan = $request->bulan;
        $tahun = $request->tahun;

        // === LOGIKA HAK AKSES MULTI-UNIT ===
        $allowedUnitIds = null;
        if (!$user->hasGlobalAccess()) {
            $allowedUnitIds = $user->managesUnits()->pluck('master_unit_kerja_id')->toArray();
            if ($user->unit_kerja_id && !in_array($user->unit_kerja_id, $allowedUnitIds)) {
                $allowedUnitIds[] = $user->unit_kerja_id;
            }

            // Jika user tidak punya unit sama sekali
            if (empty($allowedUnitIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akun Anda tidak terikat dengan unit kerja mana pun.',
                ], 422);
            }
        }

        // Validasi keamanan: Jika frontend minta unit spesifik, pastikan unit itu ada di allowedUnitIds
        if ($request->unit_kerja_id && $allowedUnitIds !== null) {
            if (!in_array($request->unit_kerja_id, $allowedUnitIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki akses untuk melihat roster unit ini.',
                ], 403);
            }
        }

        // === QUERY DATA STAF ===
        $stafQuery = User::query();

        // Terapkan filter hak akses
        if ($allowedUnitIds !== null) {
            $stafQuery->whereIn('unit_kerja_id', $allowedUnitIds);
        }

        // Terapkan filter spesifik dari request (jika ada)
        if ($request->unit_kerja_id) {
            $stafQuery->where('unit_kerja_id', $request->unit_kerja_id);
        }

        // Exclude kepala unit itu sendiri dari daftar staf
        $stafQuery->where('id', '!=', $user->id);

        $staf = $stafQuery->select(['id', 'nik', 'name', 'unit_kerja_id'])
            ->with(['rosters' => function ($query) use ($bulan, $tahun) {
                $query->select(['id', 'user_id', 'shift_id', 'tanggal_dinas', 'sesi', 'custom_nama_shift', 'custom_jam_masuk', 'custom_jam_pulang'])
                    ->whereMonth('tanggal_dinas', $bulan)
                    ->whereYear('tanggal_dinas', $tahun)
                    ->with('shift:id,nama_shift,jam_masuk,jam_pulang');
            }])
            ->get();

        $shifts = MasterShift::select(['id', 'nama_shift', 'jam_masuk', 'jam_pulang', 'unit_kerja_id'])->get();

        return response()->json([
            'success' => true,
            'message' => 'Data roster unit berhasil dimuat.',
            'data' => [
                'bulan' => $bulan,
                'tahun' => $tahun,
                'master_shifts' => $shifts,
                'staf' => $staf->map(function ($employee) {
                    return [
                        'user_id' => $employee->id,
                        'nik' => $employee->nik,
                        'nama' => $employee->name,
                        'unit_kerja_id' => $employee->unit_kerja_id, // Berguna untuk frontend mengelompokkan
                        'jadwal' => $employee->rosters->map(function ($r) {
                            $namaShift = $r->custom_nama_shift ?? $r->shift?->nama_shift ?? '-';
                            $jm = $r->jam_masuk ? substr((string) $r->jam_masuk, 0, 5) : '-';
                            $jp = $r->jam_pulang ? substr((string) $r->jam_pulang, 0, 5) : '-';
                            return [
                                'roster_id'  => $r->id,
                                'tanggal'    => $r->tanggal_dinas ? Carbon::parse($r->tanggal_dinas)->format('Y-m-d') : null,
                                'shift_id'   => $r->shift_id,
                                'nama_shift' => $namaShift,
                                'jam_masuk'  => $jm,
                                'jam_pulang' => $jp,
                                'jam_kerja'  => $jm . ' - ' . $jp,
                            ];
                        })
                    ];
                })
            ]
        ], 200);
    }

    public function bulkStore(Request $request)
    {
        $request->validate([
            'roster_data' => 'required|array',
            'roster_data.*.user_id' => 'required|exists:users,id',
            'roster_data.*.shift_id' => 'required|exists:master_shifts,id',
            'roster_data.*.tanggal' => 'required|date_format:Y-m-d',
        ]);

        $user = $request->user();

        // === LOGIKA HAK AKSES MULTI-UNIT ===
        $allowedUnitIds = null;
        if (!$user->hasGlobalAccess()) {
            $allowedUnitIds = $user->managesUnits()->pluck('master_unit_kerja_id')->toArray();
            if ($user->unit_kerja_id && !in_array($user->unit_kerja_id, $allowedUnitIds)) {
                $allowedUnitIds[] = $user->unit_kerja_id;
            }
        }

        // === VALIDASI KEAMANAN SEKALIGUS (Mencegah N+1 Query dalam loop) ===
        $targetUserIds = array_values(array_unique(array_column($request->roster_data, 'user_id')));
        $validUserIds  = User::whereIn('id', $targetUserIds)
            ->when($allowedUnitIds !== null, function ($query) use ($allowedUnitIds) {
                $query->whereIn('unit_kerja_id', $allowedUnitIds);
            })
            ->pluck('id')
            ->toArray();

        $invalidUsers = array_diff($targetUserIds, $validUserIds);
        if (!empty($invalidUsers)) {
            return response()->json([
                'success' => false,
                'message' => 'Pelanggaran akses. Anda mencoba mengisi jadwal untuk pegawai di luar unit kerja yang Anda kelola.',
            ], 403);
        }

        DB::beginTransaction();

        try {
            foreach ($request->roster_data as $data) {
                $roster = JadwalRoster::updateOrCreate(
                    [
                        'user_id'       => $data['user_id'],
                        'tanggal_dinas' => $data['tanggal'],
                    ],
                    [
                        'shift_id'      => $data['shift_id'],
                    ]
                );

                // Auto-sync absensi jika sebelumnya karyawan sudah absen masuk/pulang sebelum jadwal diinput
                $this->sinkronkanAbsensiLog((int) $data['user_id'], $data['tanggal'], $roster);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Seluruh jadwal roster berhasil disimpan dan dipublikasikan.',
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem saat menyimpan jadwal: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function sinkronkanAbsensiLog(int $userId, string $tanggal, ?JadwalRoster $roster): void
    {
        if ($roster) {
            [$start, $end] = AbsensiController::jendelaSesi($roster);
        } else {
            $start = \Carbon\Carbon::parse($tanggal)->startOfDay();
            $end   = \Carbon\Carbon::parse($tanggal)->endOfDay();
        }

        $logs = LogAbsensi::where('user_id', $userId)
            ->where(function ($q) use ($start, $end, $tanggal) {
                $q->whereBetween('waktu_masuk', [$start, $end])
                  ->orWhere('tanggal_dinas', $tanggal);
            })
            ->get();

        foreach ($logs as $log) {
            $log->roster_id = $roster?->id;
            $log->save();
            AbsensiController::recalculateStatus($log);
        }
    }

    public function jadwalDinas(Request $request)
    {
        $user  = $request->user();
        $bulan = (int) $request->query('bulan', now()->month);
        $tahun = (int) $request->query('tahun', now()->year);

        $start = \Carbon\Carbon::createFromDate($tahun, $bulan, 1)->startOfDay();
        $end   = $start->copy()->endOfMonth();
        $jumlahHari = $start->daysInMonth;

        $rosters = \App\Models\JadwalRoster::with('shift', 'logAbsensi')
            ->where('user_id', $user->id)
            ->whereBetween('tanggal_dinas', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->groupBy(fn($r) => (int) \Carbon\Carbon::parse($r->tanggal_dinas)->day);

        // Ambil seluruh log absensi user di bulan ini
        // agar jika jadwal dinas belum di-input, absen masuk dan pulang tetap muncul!
        $logs = \App\Models\LogAbsensi::where('user_id', $user->id)
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('tanggal_dinas', [$start->toDateString(), $end->toDateString()])
                  ->orWhereBetween('waktu_masuk', [$start, $end]);
            })
            ->orderBy('waktu_masuk')
            ->get()
            ->groupBy(function ($l) {
                if ($l->tanggal_dinas) {
                    return (int) \Carbon\Carbon::parse($l->tanggal_dinas)->day;
                }
                return (int) \Carbon\Carbon::parse($l->waktu_masuk)->day;
            });

        $lemburs = LogLembur::where('user_id', $user->id)
            ->where('status_validasi', 'Disetujui')
            ->whereBetween('waktu_mulai_lembur', [$start, $end])
            ->get();

        $fmtHm = fn($t) => $t ? substr(\Carbon\Carbon::parse($t)->format('H:i:s'), 0, 5) : null;

        $masukByDay = [];
        $keluarByDay = [];
        foreach ($lemburs as $l) {
            if ($l->waktu_mulai_lembur) {
                $m = \Carbon\Carbon::parse($l->waktu_mulai_lembur);
                if ($m->gte($start) && $m->lte($end)) $masukByDay[$m->day] ??= $fmtHm($l->waktu_mulai_lembur);
            }
            if ($l->waktu_selesai_lembur) {
                $s = \Carbon\Carbon::parse($l->waktu_selesai_lembur);
                if ($s->gte($start) && $s->lte($end)) $keluarByDay[$s->day] ??= $fmtHm($l->waktu_selesai_lembur);
            }
        }

        $jam = fn($r, $k) => $r ? substr((string) ($r->{'custom_jam_' . $k} ?? $r->shift?->{'jam_' . $k} ?? ''), 0, 5) : null;

        $hari = [];
        for ($d = 1; $d <= $jumlahHari; $d++) {
            $list = $rosters[$d] ?? collect();
            $r1   = $list->firstWhere('sesi', 1) ?? $list->firstWhere('sesi', null);
            $r2   = $list->firstWhere('sesi', 2);
            $dayLogs = $logs[$d] ?? collect();

            // Sesi 1: Jika ada jadwal ($r1), cari log terkait. Jika belum ada jadwal, ambil log hari itu.
            $log1 = $r1?->logAbsensi ?? ($r1 ? $dayLogs->firstWhere('roster_id', $r1->id) : null) ?? $dayLogs->first();
            $log2 = $r2?->logAbsensi ?? ($r2 ? $dayLogs->firstWhere('roster_id', $r2->id) : null) ?? ($dayLogs->count() > 1 ? $dayLogs->get(1) : null);

            // Jika jadwal sudah ada tapi log belum tertaut ke roster_id, sinkronkan & hitung ulang keterlambatan
            if ($r1 && $log1 && !$log1->roster_id) {
                $log1->roster_id = $r1->id;
                $log1->save();
                \App\Http\Controllers\Api\AbsensiController::recalculateStatus($log1);
            }

            $hasJadwal = ($r1 !== null);
            $hasAbsen = ($log1 !== null && ($log1->waktu_masuk !== null || $log1->waktu_pulang !== null));

            if ($hasJadwal) {
                $shiftNama = $r1->custom_nama_shift ?? $r1->shift?->nama_shift;
                $low = strtolower(trim((string) $shiftNama));
                $libur = str_contains($low, 'libur') || $low === 'off';
                $jamMasuk = $libur ? null : $jam($r1, 'masuk');
                $jamKeluar = $libur ? null : $jam($r1, 'pulang');

                // ✅ Jika jadwal dinas SUDAH di-input: barulah perhitungan keterlambatan bekerja
                $terlambatMenit = (int) ($log1?->menit_terlambat ?? 0);
            } else {
                // ✅ Jadwal dinas BELUM di-input:
                // Jam masuk dan jam pulang aktual tetap dimunculkan jika sudah absen
                $jamMasuk = null;
                $jamKeluar = null;

                if ($hasAbsen) {
                    $libur = false;
                    $shiftNama = 'Belum Ada Jadwal';
                } else {
                    $libur = true;
                    $shiftNama = null;
                }

                // ✅ Belum ada jadwal dinas: TIDAK dihitung keterlambatan (0 menit)
                $terlambatMenit = 0;
            }

            $dayOfWeek = \Carbon\Carbon::createFromDate($tahun, $bulan, $d)->dayOfWeek;
            $namaHariSingkat = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'][$dayOfWeek];

            $hari[] = [
                'tanggal'         => $d,
                'hari_ke'         => $dayOfWeek,
                'hari_nama'       => $namaHariSingkat,
                'is_libur'        => $libur,
                'has_jadwal'      => $hasJadwal,
                'nama_shift'      => $shiftNama,
                'jam_masuk'       => $jamMasuk,
                'jam_keluar'      => $jamKeluar,
                'jam_pulang'      => $jamKeluar,
                'absen_masuk'     => $log1?->waktu_masuk  ? $fmtHm($log1->waktu_masuk)  : null,
                'absen_pulang'    => $log1?->waktu_pulang ? $fmtHm($log1->waktu_pulang) : null,
                'terlambat_menit' => $terlambatMenit,
                'lembur_masuk'    => $masukByDay[$d] ?? null,
                'lembur_keluar'   => $keluarByDay[$d] ?? null,
                // ✅ SESI 2
                'sesi2' => $r2 ? [
                    'nama_shift'      => $r2->custom_nama_shift ?? $r2->shift?->nama_shift,
                    'jam_masuk'       => $jam($r2, 'masuk'),
                    'jam_keluar'      => $jam($r2, 'pulang'),
                    'jam_pulang'      => $jam($r2, 'pulang'),
                    'absen_masuk'     => $log2?->waktu_masuk  ? $fmtHm($log2->waktu_masuk)  : null,
                    'absen_pulang'    => $log2?->waktu_pulang ? $fmtHm($log2->waktu_pulang) : null,
                    'terlambat_menit' => (int) ($log2?->menit_terlambat ?? 0),
                ] : null,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'bulan'            => $bulan,
                'tahun'            => $tahun,
                'jumlah_hari'      => $jumlahHari,
                'hari_awal_minggu' => $start->dayOfWeek,
                'user_nama'        => $user->name,
                'hari'             => $hari,
            ],
        ], 200, [
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }
}
