<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Api\AbsensiController;
use App\Models\JadwalRoster;
use App\Models\LogAbsensi;
use App\Models\MasterShift;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class WebRosterController extends Controller
{
    private function allowedUnitIds(User $user): ?array
    {
        if ($user->hasGlobalAccess()) {
            return null;
        }
        return $user->managesUnits()->pluck('master_unit_kerja_id')->toArray();
    }

    public function index(Request $request)
    {
        $bulan = (int) $request->input('bulan', date('m'));
        $tahun = (int) $request->input('tahun', date('Y'));

        $jumlahHari = Carbon::createFromDate($tahun, $bulan, 1)->daysInMonth;
        $userLogin  = $request->user();

        $queryStaf = User::query()->where('role', '!=', 'superadmin');

        $allowed = $this->allowedUnitIds($userLogin);
        if ($allowed !== null) {
            if (empty($allowed)) {
                $queryStaf->whereRaw('1 = 0');
            } else {
                $queryStaf->whereIn('unit_kerja_id', $allowed);
            }
        }

        $staf = $queryStaf
            ->with(['unitKerja', 'rosters' => function ($q) use ($tahun, $bulan) {
                $q->whereYear('tanggal_dinas', $tahun)->whereMonth('tanggal_dinas', $bulan);
            }])
            ->orderBy('name')
            ->get();

        $stafGrouped = $staf->groupBy(function ($u) {
            return $u->unitKerja ? $u->unitKerja->nama_unit : 'Tanpa Unit';
        })->sortKeys();

        $shiftsQuery = MasterShift::query()->orderBy('jam_masuk');
        if ($allowed !== null) {
            $shiftsQuery->whereIn('unit_kerja_id', $allowed);
        }
        $shifts = $shiftsQuery->get();

        $liburMap = \App\Models\LiburNasional::whereYear('tanggal', $tahun)
            ->get()
            ->mapWithKeys(fn($l) => [
                $l->tanggal->format('Y-m-d') => ['nama' => $l->nama, 'jenis' => $l->jenis],
            ]);

        return view('roster', compact('stafGrouped', 'shifts', 'bulan', 'tahun', 'jumlahHari', 'liburMap'));
    }

    /**
     * ✅ SIMPAN ROSTER + validasi batas tgl 7 + AUTO-SYNC absensi.
     */
    public function bulkStore(Request $request)
    {
        try {
            $rosterData1 = $request->input('roster', []);
            $rosterData2 = $request->input('roster2', []);

            if (!$rosterData1 && !$rosterData2) {
                return response()->json(['success' => false, 'message' => 'Tidak ada data jadwal yang dikirim.']);
            }

            $userLogin = $request->user();
            $allowed   = $this->allowedUnitIds($userLogin);

            // Kumpulkan entri: [userId][tanggal][sesi] = payload
            $entries = [];
            foreach ([1 => $rosterData1, 2 => $rosterData2] as $sesi => $data) {
                foreach ($data as $userId => $dates) {
                    foreach ($dates as $tanggal => $payload) {
                        $shiftId      = is_array($payload) ? ($payload['shift_id'] ?? null) : $payload;
                        $customMasuk  = is_array($payload) ? ($payload['custom_jam_masuk'] ?? null) : null;
                        $customPulang = is_array($payload) ? ($payload['custom_jam_pulang'] ?? null) : null;
                        $customNama   = is_array($payload) ? ($payload['custom_nama_shift'] ?? null) : null;

                        $entries[$userId][$tanggal][$sesi] = compact('shiftId', 'customMasuk', 'customPulang', 'customNama');
                    }
                }
            }

            $validUserIds = User::whereIn('id', array_keys($entries))
                ->when($allowed !== null, fn($q) => $q->whereIn('unit_kerja_id', $allowed))
                ->pluck('id')
                ->toArray();

            // ✅ VALIDASI ANTI-TUMPUK antar sesi
            foreach ($entries as $userId => $dates) {
                if (!in_array($userId, $validUserIds)) continue;

                foreach ($dates as $tanggal => $sesiMap) {
                    if (!isset($sesiMap[1], $sesiMap[2])) continue;

                    $t1 = $this->waktuSesiDariInput($sesiMap[1]);
                    $t2 = $this->waktuSesiDariInput($sesiMap[2]);

                    if (!$t1 || !$t2) continue;

                    // Sesi 1 lintas tengah malam → sesi 2 di tanggal sama tidak masuk akal
                    if ($t1[1] <= $t1[0]) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Sesi 1 pada ' . $tanggal . ' lintas tengah malam, sehingga tidak bisa ada sesi 2 di tanggal yang sama.',
                        ], 422);
                    }

                    if ($t2[0] < $t1[1]) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Jadwal tumpang tindih pada ' . $tanggal . ': sesi 2 mulai sebelum sesi 1 selesai.',
                        ], 422);
                    }
                }
            }

            // ✅ SIMPAN per sesi
            $countShift = 0;
            $countAbsen = 0;

            foreach ($entries as $userId => $dates) {
                if (!in_array($userId, $validUserIds)) continue;

                foreach ($dates as $tanggal => $sesiMap) {
                    foreach ([1, 2] as $sesi) {
                        $d = $sesiMap[$sesi] ?? null;
                        if ($d === null) continue;

                        $kosong = empty($d['shiftId']) && empty($d['customMasuk']);

                        if ($kosong) {
                            JadwalRoster::where('user_id', $userId)
                                ->where('tanggal_dinas', $tanggal)
                                ->where('sesi', $sesi)
                                ->delete();

                            $countAbsen += $this->sinkronkanAbsensi((int) $userId, $tanggal, null);
                            continue;
                        }

                        $shiftId = empty($d['customMasuk']) ? $d['shiftId'] : null;

                        $roster = JadwalRoster::updateOrCreate(
                            ['user_id' => $userId, 'tanggal_dinas' => $tanggal, 'sesi' => $sesi],
                            [
                                'shift_id'          => $shiftId,
                                'custom_jam_masuk'  => $d['customMasuk'],
                                'custom_jam_pulang' => $d['customPulang'],
                                'custom_nama_shift' => $d['customNama'],
                            ]
                        );
                        $countShift++;

                        $countAbsen += $this->sinkronkanAbsensi((int) $userId, $tanggal, $roster);
                    }
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Jadwal disimpan ({$countShift} sesi). {$countAbsen} absensi otomatis disesuaikan dengan jadwal terbaru.",
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem: ' . $e->getMessage(),
            ]);
        }
    }

    /** Ambil [jamMasuk, jamPulang] dari payload input (custom atau shift). */
    private function waktuSesiDariInput(array $d): ?array
    {
        if (!empty($d['customMasuk']) && !empty($d['customPulang'])) {
            return [$d['customMasuk'], $d['customPulang']];
        }
        if (!empty($d['shiftId'])) {
            $s = \App\Models\MasterShift::find($d['shiftId']);
            if ($s) return [(string) $s->jam_masuk, (string) $s->jam_pulang];
        }
        return null;
    }

    /**
     * ✅ Salin bulan lalu — juga melakukan auto-sync absensi.
     */
    public function copyPrevious(Request $request)
    {
        $request->validate([
            'bulan' => 'required|integer|between:1,12',
            'tahun' => 'required|integer|min:2020',
        ]);

        $bulan = (int) $request->bulan;
        $tahun = (int) $request->tahun;

        $userLogin = $request->user();
        $allowed   = $this->allowedUnitIds($userLogin);

        $prev       = Carbon::createFromDate($tahun, $bulan, 1)->subMonthNoOverflow();
        $targetDays = Carbon::createFromDate($tahun, $bulan, 1)->daysInMonth;

        $prevRosters = JadwalRoster::with('user')
            ->whereYear('tanggal_dinas', $prev->year)
            ->whereMonth('tanggal_dinas', $prev->month)
            ->whereHas('user', function ($q) use ($allowed) {
                $q->where('role', '!=', 'superadmin');
                if ($allowed !== null) $q->whereIn('unit_kerja_id', $allowed);
            })
            ->get();

        $count = 0;
        foreach ($prevRosters as $r) {
            $day = (int) Carbon::parse($r->tanggal_dinas)->day;
            if ($day > $targetDays) continue;

            $tanggalBaru = sprintf('%04d-%02d-%02d', $tahun, $bulan, $day);

            $roster = JadwalRoster::updateOrCreate(
                ['user_id' => $r->user_id, 'tanggal_dinas' => $tanggalBaru, 'sesi' => $r->sesi],
                [
                    'shift_id'          => $r->shift_id,
                    'custom_jam_masuk'  => $r->custom_jam_masuk,
                    'custom_jam_pulang' => $r->custom_jam_pulang,
                    'custom_nama_shift' => $r->custom_nama_shift,
                ]
            );

            $this->sinkronkanAbsensi((int) $r->user_id, $tanggalBaru, $roster);
            $count++;

            $this->sinkronkanAbsensi((int) $r->user_id, $tanggalBaru, $roster);
            $count++;
        }

        return response()->json([
            'success' => true,
            'message' => "Berhasil menyalin {$count} jadwal dari periode sebelumnya.",
        ]);
    }

    /**
     * ✅ AUTO-SYNC: hubungkan semua absensi pada tanggal tsb ke roster (atau lepaskan),
     * lalu hitung ulang statusnya dengan jadwal TERBARU.
     */
    private function sinkronkanAbsensi(int $userId, string $tanggal, ?JadwalRoster $roster): int
    {
        if ($roster) {
            [$start, $end] = \App\Http\Controllers\Api\AbsensiController::jendelaSesi($roster);
        } else {
            $start = Carbon::parse($tanggal)->startOfDay();
            $end   = Carbon::parse($tanggal)->endOfDay();
        }

        $logs = LogAbsensi::where('user_id', $userId)
            ->whereBetween('waktu_masuk', [$start, $end])
            ->get();

        // Sesi lain yang masih ada di tanggal itu (untuk melindungi log-nya)
        $sesiLain = JadwalRoster::with('shift')
            ->where('user_id', $userId)
            ->where('tanggal_dinas', $tanggal)
            ->when($roster, fn($q) => $q->where('id', '!=', $roster->id))
            ->get();

        foreach ($logs as $log) {
            // Jangan lepaskan log yang jatuh di jendela sesi lain
            if (!$roster) {
                $diSesiLain = false;
                foreach ($sesiLain as $o) {
                    [$ws, $we] = \App\Http\Controllers\Api\AbsensiController::jendelaSesi($o);
                    if ($log->waktu_masuk->gte($ws) && $log->waktu_masuk->lte($we)) {
                        $diSesiLain = true;
                        break;
                    }
                }
                if ($diSesiLain) continue;
            }

            $log->roster_id = $roster?->id;
            $log->save();
            \App\Http\Controllers\Api\AbsensiController::recalculateStatus($log);
        }

        return $logs->count();
    }
}
