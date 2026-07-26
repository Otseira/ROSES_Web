<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\JadwalRoster;
use App\Models\MasterShift;
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
        ]);

        $kepalaUnit = $request->user();
        $unitKerjaId = $kepalaUnit->unit_kerja_id;

        if (!$unitKerjaId) {
            return response()->json([
                'success' => false,
                'message' => 'Akun Anda tidak terikat dengan unit kerja mana pun.',
            ], 422);
        }

        $bulan = $request->bulan;
        $tahun = $request->tahun;

        $staf = User::where('unit_kerja_id', $unitKerjaId)
            ->where('id', '!=', $kepalaUnit->id)
            ->with(['rosters' => function ($query) use ($bulan, $tahun) {
                $query->whereMonth('tanggal_dinas', $bulan)
                    ->whereYear('tanggal_dinas', $tahun)
                    ->with('shift');
            }])
            ->get();

        $shifts = MasterShift::all();

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
                        'jadwal' => $employee->rosters->map(function ($r) {
                            return [
                                'roster_id' => $r->id,
                                'tanggal' => $r->tanggal_dinas,
                                'shift_id' => $r->shift_id,
                                'nama_shift' => $r->shift->nama_shift,
                                'jam_kerja' => $r->shift->jam_masuk . ' - ' . $r->shift->jam_pulang,
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

        $kepalaUnit = $request->user();
        $unitKerjaId = $kepalaUnit->unit_kerja_id;

        DB::beginTransaction();

        try {
            foreach ($request->roster_data as $data) {
                $isBawahan = User::where('id', $data['user_id'])
                    ->where('unit_kerja_id', $unitKerjaId)
                    ->exists();

                if (!$isBawahan) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Pelanggaran akses. Anda mencoba mengisi jadwal untuk pegawai di luar unit kerja Anda.',
                    ], 403);
                }

                JadwalRoster::updateOrCreate(
                    [
                        'user_id' => $data['user_id'],
                        'tanggal_dinas' => $data['tanggal'],
                    ],
                    [
                        'shift_id' => $data['shift_id'],
                    ]
                );
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
            ->keyBy(fn($r) => (int) \Carbon\Carbon::parse($r->tanggal_dinas)->day);

        $lembur = \App\Models\LogLembur::where('user_id', $user->id)
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('waktu_mulai_lembur', [$start->toDateTimeString(), $end->toDateTimeString()])
                    ->orWhereBetween('waktu_selesai_lembur', [$start->toDateTimeString(), $end->toDateTimeString()]);
            })
            ->get();

        $fmtHm = fn($t) => $t ? substr(\Carbon\Carbon::parse($t)->format('H:i:s'), 0, 5) : null;

        $masukByDay = [];
        $keluarByDay = [];
        foreach ($lembur as $l) {
            if ($l->waktu_mulai_lembur) {
                $m = \Carbon\Carbon::parse($l->waktu_mulai_lembur);
                if ($m->gte($start) && $m->lte($end)) $masukByDay[$m->day] ??= $fmtHm($l->waktu_mulai_lembur);
            }
            if ($l->waktu_selesai_lembur) {
                $s = \Carbon\Carbon::parse($l->waktu_selesai_lembur);
                if ($s->gte($start) && $s->lte($end)) $keluarByDay[$s->day] ??= $fmtHm($l->waktu_selesai_lembur);
            }
        }

        $hari = [];
        for ($d = 1; $d <= $jumlahHari; $d++) {
            $r   = $rosters[$d] ?? null;
            $log = $r?->logAbsensi;                       
            $shiftNama = $r?->shift?->nama_shift;
            $low = strtolower(trim((string) $shiftNama));
            $libur = ($r === null) || str_contains($low, 'libur') || $low === 'off';

            $hari[] = [
                'tanggal'        => $d,
                'is_libur'       => $libur,
                'nama_shift'     => $shiftNama,
                'jam_masuk'      => $libur ? null : substr((string) ($r->shift->jam_masuk ?? ''), 0, 5),
                'jam_keluar'     => $libur ? null : substr((string) ($r->shift->jam_pulang ?? ''), 0, 5),
                'absen_masuk'    => $log?->waktu_masuk  ? $fmtHm($log->waktu_masuk)  : null,
                'absen_pulang'   => $log?->waktu_pulang ? $fmtHm($log->waktu_pulang) : null,
                'terlambat_menit' => (int) ($log?->menit_terlambat ?? 0),
                'lembur_masuk'   => $masukByDay[$d] ?? null,
                'lembur_keluar'  => $keluarByDay[$d] ?? null,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'bulan'       => $bulan,
                'tahun'       => $tahun,
                'jumlah_hari' => $jumlahHari,
                'user_nama'   => $user->name,
                'hari'        => $hari,
            ],
        ]);
    }
}
