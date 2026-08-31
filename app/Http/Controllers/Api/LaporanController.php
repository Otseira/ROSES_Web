<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\LogAbsensi;
use App\Models\LogLembur;
use App\Models\PengaturanPayroll;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LaporanController extends Controller
{
    /**
     * Mengambil Rekapitulasi Laporan Bulanan Otomatis (Akses: HRD / SDM / Keuangan / Kepala Unit)
     */
    public function rekapBulanan(Request $request)
    {
        // 1. Validasi Parameter Bulan dan Tahun
        $request->validate([
            'bulan' => 'required|integer|between:1,12',
            'tahun' => 'required|integer|min:2020',
            'unit_kerja_id' => 'nullable|exists:master_unit_kerjas,id'
        ]);

        $bulan = $request->bulan;
        $tahun = $request->tahun;
        $user = $request->user();

        // === LOGIKA HAK AKSES MULTI-UNIT (SCOPING) ===
        $allowedUnitIds = null;

        if (!$user->hasGlobalAccess()) {
            // Kepala Unit / Manajemen hanya boleh melihat unit yang dikelola
            $allowedUnitIds = $user->managesUnits()->pluck('master_unit_kerja_id')->toArray();
            if ($user->unit_kerja_id && !in_array($user->unit_kerja_id, $allowedUnitIds)) {
                $allowedUnitIds[] = $user->unit_kerja_id;
            }

            // Proteksi Keamanan: Jika frontend mengirim request unit_kerja_id spesifik, 
            // pastikan unit tersebut ada di dalam daftar allowedUnitIds miliknya
            if ($request->unit_kerja_id) {
                if (!in_array($request->unit_kerja_id, $allowedUnitIds)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Akses ditolak. Anda tidak memiliki izin untuk melihat laporan unit kerja tersebut.',
                    ], 403);
                }
            }
        }

        // 2. Ambil Parameter Finansial & Aturan Tanggal
        $pengaturan = PengaturanPayroll::first();
        $tglMulaiRule = $pengaturan ? $pengaturan->tanggal_cut_off_mulai : 24;
        $tglSelesaiRule = $pengaturan ? $pengaturan->tanggal_cut_off_selesai : 23;
        $ratePotongan = $pengaturan ? $pengaturan->potongan_terlambat_per_menit : 0;
        $rateLembur = $pengaturan ? $pengaturan->uang_lembur_per_jam : 0;

        // 3. Generasikan Rentang Tanggal Cut-Off
        $startDate = Carbon::createFromDate($tahun, $bulan, $tglMulaiRule)->subMonth()->startOfDay()->toDateTimeString();
        $endDate = Carbon::createFromDate($tahun, $bulan, $tglSelesaiRule)->endOfDay()->toDateTimeString();

        // 4. Query Data Pegawai dengan Eager Loading Aggregates (Mencegah N+1 Query)
        $users = User::with('unitKerja')

            // FILTER 1: Batasi berdasarkan hak akses role (Kepala Unit vs HRD)
            ->when($allowedUnitIds !== null, function ($query) use ($allowedUnitIds) {
                $query->whereIn('unit_kerja_id', $allowedUnitIds);
            })

            // FILTER 2: Batasi berdasarkan request spesifik dari frontend (jika ada)
            ->when($request->unit_kerja_id, function ($query) use ($request) {
                $query->where('unit_kerja_id', $request->unit_kerja_id);
            })

            ->withCount([
                // Menghitung jumlah hari hadir langsung di level database query
                'logAbsensis as total_hadir' => function ($query) use ($startDate, $endDate) {
                    $query->whereHas('roster', function ($q) use ($startDate, $endDate) {
                        $q->whereBetween('tanggal_dinas', [$startDate, $endDate]);
                    })->whereNotNull('waktu_masuk');
                },
                // Menghitung total akumulasi menit terlambat langsung di database
                'logAbsensis as total_menit_terlambat' => function ($query) use ($startDate, $endDate) {
                    $query->whereHas('roster', function ($q) use ($startDate, $endDate) {
                        $q->whereBetween('tanggal_dinas', [$startDate, $endDate]);
                    })->select(DB::raw('SUM(menit_terlambat)'));
                }
            ])
            // Menghitung total jam lembur yang disetujui langsung di database
            ->withSum([
                'logLemburs as total_jam_lembur' => function ($query) use ($startDate, $endDate) {
                    $query->where('status_validasi', 'Disetujui')
                        ->whereBetween('waktu_mulai_lembur', [$startDate, $endDate]);
                }
            ], 'total_jam_lembur')
            ->get();

        // 5. Mapping Data Finansial (Hanya kalkulasi matematika murni tanpa hit query lagi)
        $dataRekap = $users->map(function ($user) use ($ratePotongan, $rateLembur) {

            // Mengambil hasil aggregate database (jika null otomatis diubah ke 0)
            $totalHadir = $user->total_hadir;
            $totalMenitTerlambat = $user->total_menit_terlambat ?? 0;
            $totalJamLembur = $user->total_jam_lembur ?? 0;

            // Hitung finansial
            $totalPotonganTerlambat = $totalMenitTerlambat * $ratePotongan;
            $totalUangLembur = $totalJamLembur * $rateLembur;
            $totalPenyesuaianNet = $totalUangLembur - $totalPotonganTerlambat;

            return [
                'user_id' => $user->id,
                'nik' => $user->nik,
                'nama' => $user->name,
                'unit_kerja' => $user->unitKerja ? $user->unitKerja->nama_unit : '-',
                'metrik_kehadiran' => [
                    'total_hari_hadir' => (int) $totalHadir,
                    'total_menit_terlambat' => (int) $totalMenitTerlambat,
                    'total_jam_lembur' => round((float) $totalJamLembur, 2),
                ],
                'rincian_finansial' => [
                    'potongan_keterlambatan' => round($totalPotonganTerlambat, 2),
                    'insentif_uang_lembur' => round($totalUangLembur, 2),
                    'total_penyesuaian_bersih' => round($totalPenyesuaianNet, 2),
                ]
            ];
        });

        // 6. Kembalikan Response Data Laporan
        return response()->json([
            'success' => true,
            'message' => 'Kalkulasi laporan otomatis periode ' . Carbon::parse($startDate)->toDateString() . ' s/d ' . Carbon::parse($endDate)->toDateString() . ' berhasil dimuat.',
            'periode_laporan' => [
                'tanggal_mulai' => Carbon::parse($startDate)->toDateString(),
                'tanggal_selesai' => Carbon::parse($endDate)->toDateString(),
                'rate_potongan_per_menit' => $ratePotongan,
                'rate_lembur_per_jam' => $rateLembur,
            ],
            'data' => $dataRekap
        ], 200);
    }
}
