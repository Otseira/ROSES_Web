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
            $rosterData = $request->input('roster');

            if (!$rosterData) {
                return response()->json(['success' => false, 'message' => 'Tidak ada data jadwal yang dikirim.']);
            }

            // ✅ BATAS WAKTU: roster bulan berjalan hanya s/d tanggal 7
            $now = Carbon::now();
            foreach ($rosterData as $dates) {
                foreach ($dates as $tanggal => $shiftId) {
                    $d = Carbon::parse($tanggal);
                    if ($d->year === $now->year && $d->month === $now->month && $now->day > 7) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Batas waktu penyusunan roster bulan ini sudah lewat (maksimal tanggal 7). Hubungi HRD untuk penyesuaian.',
                        ], 422);
                    }
                    break 2;
                }
            }

            $userLogin = $request->user();
            $allowed   = $this->allowedUnitIds($userLogin);

            $validUserIds = User::whereIn('id', array_keys($rosterData))
                ->when($allowed !== null, fn($q) => $q->whereIn('unit_kerja_id', $allowed))
                ->pluck('id')
                ->toArray();

            $countShift  = 0;
            $countAbsen  = 0;

            foreach ($rosterData as $userId => $dates) {
                if (!in_array($userId, $validUserIds)) continue;

                foreach ($dates as $tanggal => $shiftId) {
                    if ($shiftId) {
                        $roster = JadwalRoster::updateOrCreate(
                            ['user_id' => $userId, 'tanggal_dinas' => $tanggal],
                            ['shift_id' => $shiftId]
                        );
                        $countShift++;

                        // ✅ JADWAL DIBUAT / DIGANTI → absensi otomatis ikut jadwal TERBARU
                        $countAbsen += $this->sinkronkanAbsensi((int) $userId, $tanggal, $roster);
                    } else {
                        JadwalRoster::where('user_id', $userId)
                            ->where('tanggal_dinas', $tanggal)
                            ->delete();

                        // ✅ JADWAL DIHAPUS → absensi kembali "Tanpa Jadwal"
                        $countAbsen += $this->sinkronkanAbsensi((int) $userId, $tanggal, null);
                    }
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Jadwal disimpan ({$countShift} shift). {$countAbsen} absensi otomatis disesuaikan dengan jadwal terbaru.",
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem: ' . $e->getMessage(),
            ]);
        }
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
                ['user_id' => $r->user_id, 'tanggal_dinas' => $tanggalBaru],
                ['shift_id' => $r->shift_id]
            );

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
        $start = Carbon::parse($tanggal)->startOfDay();
        $end   = Carbon::parse($tanggal)->endOfDay();

        // Shift malam (overnight): jendela absen masuk s/d jam pulang besoknya
        if ($roster && $roster->shift && $roster->shift->jam_pulang < $roster->shift->jam_masuk) {
            $start = Carbon::parse($tanggal . ' ' . $roster->shift->jam_masuk)
                ->subMinutes(AbsensiController::MASUK_CEPAT_MAKS_MENIT);
            $end = Carbon::parse($tanggal)->addDay()->setTimeFromTimeString($roster->shift->jam_pulang);
        }

        $logs = LogAbsensi::where('user_id', $userId)
            ->whereBetween('waktu_masuk', [$start, $end])
            ->get();

        foreach ($logs as $log) {
            $log->roster_id = $roster?->id;
            $log->save();
            AbsensiController::recalculateStatus($log);
        }

        return $logs->count();
    }
}
