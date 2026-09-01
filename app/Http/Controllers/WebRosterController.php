<?php

namespace App\Http\Controllers;

use App\Models\JadwalRoster;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\MasterShift;
use Carbon\Carbon;

class WebRosterController extends Controller
{
    /**
     * Helper: daftar ID unit yang boleh dikelola oleh user login.
     * null = akses global (lihat semua unit).
     */
    private function allowedUnitIds(User $user): ?array
    {
        if ($user->hasGlobalAccess()) {
            return null;
        }

        // ✅ BARU: HANYA unit yang DICENTANG pada "Unit yang Dikelola".
        // Unit utama (unit_kerja_id) TIDAK otomatis dimasukkan,
        // sehingga kepala instalasi tidak muncul sebagai karyawan yang dikelola.
        return $user->managesUnits()->pluck('master_unit_kerja_id')->toArray();

        return $ids;
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
                $q->whereYear('tanggal_dinas', $tahun)
                    ->whereMonth('tanggal_dinas', $bulan);
            }])
            ->orderBy('name')
            ->get();

        // KELOMPOKKAN STAF BERDASARKAN UNIT KERJA
        $stafGrouped = $staf->groupBy(function ($u) {
            return $u->unitKerja ? $u->unitKerja->nama_unit : 'Tanpa Unit';
        })->sortKeys();

        $shifts = MasterShift::orderBy('jam_masuk')->get();

        return view('roster', compact('stafGrouped', 'shifts', 'bulan', 'tahun', 'jumlahHari'));
    }

    public function bulkStore(Request $request)
    {
        try {
            $rosterData = $request->input('roster');

            if (!$rosterData) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada data jadwal yang dikirim.'
                ]);
            }

            $userLogin = $request->user();
            $allowed   = $this->allowedUnitIds($userLogin);

            // VALIDASI KEAMANAN: hanya staf di unit yang diizinkan yang boleh disimpan
            $validUserIds = User::whereIn('id', array_keys($rosterData))
                ->when($allowed !== null, function ($q) use ($allowed) {
                    $q->whereIn('unit_kerja_id', $allowed);
                })
                ->pluck('id')
                ->toArray();

            foreach ($rosterData as $userId => $dates) {
                if (!in_array($userId, $validUserIds)) {
                    continue; // abaikan / bisa juga dijadikan error 403
                }

                foreach ($dates as $tanggal => $shiftId) {
                    if ($shiftId) {
                        JadwalRoster::updateOrCreate(
                            ['user_id' => $userId, 'tanggal_dinas' => $tanggal],
                            ['shift_id' => $shiftId]
                        );
                    } else {
                        JadwalRoster::where('user_id', $userId)
                            ->where('tanggal_dinas', $tanggal)
                            ->delete();
                    }
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Jadwal Roster berhasil disimpan dan dipublikasikan.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * BARU: Menyalin seluruh jadwal bulan sebelumnya ke bulan aktif (1 klik).
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
                if ($allowed !== null) {
                    $q->whereIn('unit_kerja_id', $allowed);
                }
            })
            ->get();

        $count = 0;
        foreach ($prevRosters as $r) {
            $day = (int) Carbon::parse($r->tanggal_dinas)->day;
            if ($day > $targetDays) continue; // contoh: tgl 31 tidak ada di bulan 30 hari

            $tanggalBaru = sprintf('%04d-%02d-%02d', $tahun, $bulan, $day);

            JadwalRoster::updateOrCreate(
                ['user_id' => $r->user_id, 'tanggal_dinas' => $tanggalBaru],
                ['shift_id' => $r->shift_id]
            );
            $count++;
        }

        return response()->json([
            'success' => true,
            'message' => "Berhasil menyalin {$count} jadwal dari periode sebelumnya."
        ]);
    }
}
