<?php

namespace App\Http\Controllers;

use App\Models\JadwalRoster;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\MasterShift;
use Carbon\Carbon;

class WebRosterController extends Controller
{
    public function index(Request $request)
    {
        $bulan = $request->input('bulan', date('m'));
        $tahun = $request->input('tahun', date('Y'));
        
        $jumlahHari = \Carbon\Carbon::createFromDate($tahun, $bulan)->daysInMonth;
        $userLogin = $request->user();
        
        // Mulai query pencarian staf
        $queryStaf = User::query();

        // KUNCI UTAMA: Sembunyikan semua akun ber-role 'superadmin' dari matriks roster harian
        $queryStaf->where('role', '!=', 'superadmin');

        // JIKA BUKAN SUPERADMIN: Filter ketat staf berdasarkan unit kerja masing-masing
        if ($userLogin->role !== 'superadmin') {
            $queryStaf->where('unit_kerja_id', $userLogin->unit_kerja_id);
        }

        // Ambil data staf beserta relasi jadwal rosternya
        $staf = $queryStaf->with(['rosters' => function ($q) use ($tahun, $bulan) {
                $q->whereYear('tanggal_dinas', $tahun)
                  ->whereMonth('tanggal_dinas', $bulan);
            }])
            ->orderBy('name') 
            ->get();
        
        $shifts = \App\Models\MasterShift::all();

        return view('roster', compact('staf', 'shifts', 'bulan', 'tahun', 'jumlahHari'));
    }
    public function bulkStore(Request $request)
    {
        try {
            // Mengambil array data input: roster[user_id][tanggal]
            $rosterData = $request->input('roster');

            if (!$rosterData) {
                return response()->json([
                    'success' => false, 
                    'message' => 'Tidak ada data jadwal yang dikirim.'
                ]);
            }

            foreach ($rosterData as $userId => $dates) {
                foreach ($dates as $tanggal => $shiftId) {
                    if ($shiftId) {
                        // Jika ada shift yang dipilih (bukan Libur), update atau buat baru
                        JadwalRoster::updateOrCreate(
                            [
                                'user_id' => $userId,
                                'tanggal_dinas' => $tanggal,
                            ],
                            [
                                'shift_id' => $shiftId,
                            ]
                        );
                    } else {
                        // Jika admin memilih "Libur" (value kosong), hapus jadwal di tanggal tersebut jika ada
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
}
