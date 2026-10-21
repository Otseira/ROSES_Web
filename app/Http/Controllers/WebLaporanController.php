<?php

namespace App\Http\Controllers;

use App\Models\LogAbsensi;
use App\Models\LogLembur;
use App\Models\PengambilanLembur;
use App\Models\MasterUnitKerja;
use App\Models\PengaturanAplikasi;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\RekapAbsensiPerKaryawanExport;
use App\Exports\RekapLemburExport;

class WebLaporanController extends Controller
{
    public function index(Request $request)
    {
        $unit       = $request->input('unit');
        $tglMulai   = $request->input('tanggal_mulai');
        $tglSelesai = $request->input('tanggal_selesai');

        // === LOGIKA HAK AKSES MULTI-UNIT ===
        $userLogin      = $request->user();
        $allowedUnitIds = $this->getAllowedUnitIds($userLogin);

        if ($allowedUnitIds !== null && $unit && !in_array($unit, $allowedUnitIds)) {
            abort(403, 'Anda tidak memiliki akses untuk melihat laporan unit tersebut.');
        }

        // === RENTANG TANGGAL (custom atau default) ===
        [$startDate, $endDate] = $this->resolveDateRange($tglMulai, $tglSelesai);

        [$logs, $lemburs] = $this->buildData($startDate, $endDate, $unit, $allowedUnitIds);

        // Statistik ringkasan
        $stats = [
            'total'      => $logs->count(),
            'tepat'      => $logs->where('status_kehadiran', 'Tepat Waktu')->count(),
            'terlambat'  => $logs->where('status_kehadiran', 'Terlambat')->count(),
            'luar'       => $logs->where('status_kehadiran', 'Luar Jadwal')->count(),
            'menit_late' => (int) $logs->sum('menit_terlambat'),
            'jam_lembur' => round($lemburs->flatten()->sum('total_jam_lembur'), 1),
        ];

        // Dropdown unit
        if ($allowedUnitIds !== null) {
            $units = MasterUnitKerja::whereIn('id', $allowedUnitIds)
                ->orderBy('nama_unit', 'asc')->get();
        } else {
            $units = MasterUnitKerja::orderBy('nama_unit', 'asc')->get();
        }

        // Kelompokkan per unit
        $logsGrouped = $logs->groupBy(function ($log) {
            $user = $log->user ?? $log->roster?->user;
            return $user?->unitKerja?->nama_unit ?? 'Tanpa Unit';
        })->sortKeys();

        // Untuk kompatibilitas view PDF (label periode)
        $bulan = Carbon::parse($startDate)->month;
        $tahun = Carbon::parse($startDate)->year;

        return view('laporan.laporan', compact(
            'unit',
            'logs',
            'lemburs',
            'stats',
            'units',
            'logsGrouped',
            'tglMulai',
            'tglSelesai',
            'startDate',
            'endDate',
            'bulan',
            'tahun'
        ));
    }

    /**
     * ✅ EXPORT EXCEL — Multi-sheet per karyawan dengan:
     *    - Total lembur & on-call
     *    - Breakdown keterlambatan per kategori potongan
     *    - Total potongan otomatis
     */
    public function exportExcel(Request $request)
    {
        $unit       = $request->input('unit');
        $tglMulai   = $request->input('tanggal_mulai');
        $tglSelesai = $request->input('tanggal_selesai');

        $userLogin      = $request->user();
        $allowedUnitIds = $this->getAllowedUnitIds($userLogin);

        if ($allowedUnitIds !== null && $unit && !in_array($unit, $allowedUnitIds)) {
            abort(403, 'Anda tidak memiliki akses untuk mengekspor laporan unit tersebut.');
        }

        [$startDate, $endDate] = $this->resolveDateRange($tglMulai, $tglSelesai);
        [$calendarData, $periodDates] = $this->buildCalendarData($startDate, $endDate, $unit, $allowedUnitIds);

        $periodLabel = Carbon::parse($startDate)->translatedFormat('d M Y')
            . ' s/d ' . Carbon::parse($endDate)->translatedFormat('d M Y');

        $sheetsData = [];
        foreach ($calendarData as $empData) {
            $rows = [];
            foreach ($empData['days'] as $day) {
                $rows[] = [
                    $day['tanggal'],
                    $day['jam_masuk'],
                    $day['jam_keluar'],
                    $day['durasi'],
                    $day['menit_telat'],
                    $day['jarak'],
                    $day['lembur_menit'] > 0 ? $day['lembur_menit'] : 0,
                    $day['oncall_menit'] > 0 ? $day['oncall_menit'] : 0,
                    $day['masuk_dinas'],
                ];
            }

            $sheetsData[] = [
                'nama'    => $empData['nama'],
                'unit'    => $empData['unit'],
                'rows'    => $rows,
                'summary' => $empData['summary'],
            ];
        }

        $filename = 'rekap-absensi-'
            . Carbon::parse($startDate)->format('Y-m-d') . '-'
            . Carbon::parse($endDate)->format('Y-m-d') . '.xls';

        // Format XLS (BIFF8) — tidak butuh extension zip
        return Excel::download(
            new \App\Exports\RekapAbsensiPerKaryawanExport($sheetsData, $periodLabel),
            $filename,
            \Maatwebsite\Excel\Excel::XLS
        );
    }

    public function exportPdf(Request $request)
    {
        $unit       = $request->input('unit');
        $tglMulai   = $request->input('tanggal_mulai');
        $tglSelesai = $request->input('tanggal_selesai');

        $userLogin      = $request->user();
        $allowedUnitIds = $this->getAllowedUnitIds($userLogin);

        if ($allowedUnitIds !== null && $unit && !in_array($unit, $allowedUnitIds)) {
            abort(403, 'Anda tidak memiliki akses untuk melihat PDF laporan unit tersebut.');
        }

        [$startDate, $endDate] = $this->resolveDateRange($tglMulai, $tglSelesai);
        [$calendarData, $periodDates] = $this->buildCalendarData($startDate, $endDate, $unit, $allowedUnitIds);

        $bulan = Carbon::parse($startDate)->month;
        $tahun = Carbon::parse($startDate)->year;
        $periodLabel = Carbon::parse($startDate)->translatedFormat('d M Y')
            . ' s/d ' . Carbon::parse($endDate)->translatedFormat('d M Y');

        $unitNama = null;
        if ($unit) {
            $unitModel = MasterUnitKerja::find($unit);
            $unitNama  = $unitModel ? $unitModel->nama_unit : null;
        }

        $grandTotalMasukDinas = array_sum(array_column($calendarData, 'total_masuk_dinas'));
        $grandTotalTerlambat  = array_sum(array_column(array_column($calendarData, 'summary'), 'total_terlambat_menit'));
        $grandTotalLembur     = array_sum(array_column($calendarData, 'total_lembur_menit'));
        $grandTotalOncall     = array_sum(array_column($calendarData, 'total_oncall_menit'));

        return view('laporan.pdf', compact(
            'bulan',
            'tahun',
            'calendarData',
            'periodDates',
            'periodLabel',
            'tglMulai',
            'tglSelesai',
            'startDate',
            'endDate',
            'unitNama',
            'grandTotalMasukDinas',
            'grandTotalTerlambat',
            'grandTotalLembur',
            'grandTotalOncall'
        ));
    }

    /**
     * ✅ Rentang tanggal: pakai input user; jika kosong → 1 bulan penuh s/d akhir bulan.
     */
    private function resolveDateRange(?string $tglMulai, ?string $tglSelesai): array
    {
        if ($tglMulai && $tglSelesai) {
            return [
                Carbon::parse($tglMulai)->startOfDay()->toDateTimeString(),
                Carbon::parse($tglSelesai)->endOfDay()->toDateTimeString(),
            ];
        }

        return [
            now()->startOfMonth()->startOfDay()->toDateTimeString(),
            now()->endOfMonth()->endOfDay()->toDateTimeString(),
        ];
    }

    /**
     * Membangun data matriks kalender penuh harian per karyawan (untuk Cetak PDF & Excel).
     * Seluruh tanggal dalam sebulan / rentang dipilih tetap ditampilkan.
     * Hari tanpa absen / jadwal dibuat strip (-).
     * Disertai kolom / penghitungan total masuk dinas.
     */
    private function buildCalendarData(string $startDate, string $endDate, $unit = null, ?array $allowedUnitIds = null): array
    {
        [$logs, $lemburs] = $this->buildData($startDate, $endDate, $unit, $allowedUnitIds);

        // Ambil daftar karyawan dalam scope
        $userQuery = \App\Models\User::select(['id', 'name', 'nik', 'unit_kerja_id'])
            ->with('unitKerja:id,nama_unit')
            ->where('role', '!=', 'superadmin');

        if ($unit) {
            $userQuery->where('unit_kerja_id', $unit);
        } elseif ($allowedUnitIds !== null) {
            $userQuery->whereIn('unit_kerja_id', $allowedUnitIds);
        }

        $scopedUsers = $userQuery->orderBy('name', 'asc')->get();
        $userMap = $scopedUsers->keyBy('id');

        // Pastikan karyawan yang memiliki log absensi juga terdaftar
        foreach ($logs as $log) {
            $u = $log->user ?? $log->roster?->user;
            if ($u && !$userMap->has($u->id)) {
                $userMap->put($u->id, $u);
            }
        }

        $allEmployees = $userMap->values()->sortBy('name');

        // Buat daftar tanggal lengkap dari startDate s/d endDate
        $startCarbon = Carbon::parse($startDate)->startOfDay();
        $endCarbon   = Carbon::parse($endDate)->startOfDay();

        $periodDates = [];
        $curr = $startCarbon->copy();
        while ($curr->lte($endCarbon)) {
            $periodDates[] = $curr->copy();
            $curr->addDay();
        }

        // Petakan log absensi per user_id dan tanggal (Y-m-d)
        $logsByUserAndDate = [];
        foreach ($logs as $log) {
            $uId = $log->user_id ?? $log->roster?->user_id;
            if (!$uId) continue;
            $d = $log->tanggal_dinas
                ? Carbon::parse($log->tanggal_dinas)->format('Y-m-d')
                : optional($log->waktu_masuk)->format('Y-m-d');
            if ($d) {
                $logsByUserAndDate[$uId][$d][] = $log;
            }
        }

        $calendarData = [];

        foreach ($allEmployees as $emp) {
            $totalMasukDinas     = 0;
            $totalLemburMenit    = 0;
            $totalOncallMenit    = 0;
            $totalTerlambatMenit = 0;

            $terlambat6_10   = 0;
            $jumlah6_10      = 0;
            $terlambat11_15  = 0;
            $jumlah11_15     = 0;
            $terlambat16plus = 0;
            $jumlah16plus    = 0;

            $days = [];

            foreach ($periodDates as $dateObj) {
                $dStr = $dateObj->format('Y-m-d');
                $dayLogs = $logsByUserAndDate[$emp->id][$dStr] ?? [];

                // Hitung lembur/oncall hari ini
                $keyLembur = $emp->id . '|' . $dStr;
                $itemsLembur = $lemburs->get($keyLembur);

                $mLembur = 0;
                $mOncall = 0;
                if ($itemsLembur) {
                    foreach ($itemsLembur as $l) {
                        $mnt  = (float) ($l->total_jam_lembur ?? 0) * 60;
                        $norm = str_contains(
                            strtolower(str_replace(['-', ' ', '_'], '', $l->jenis_lembur ?? '')),
                            'oncall'
                        );
                        $norm ? $mOncall += $mnt : $mLembur += $mnt;
                    }
                }
                $totalLemburMenit += $mLembur;
                $totalOncallMenit += $mOncall;

                if (!empty($dayLogs)) {
                    $log = $dayLogs[0];
                    $isHadir = !empty($log->waktu_masuk);

                    if ($isHadir) {
                        $totalMasukDinas++;
                    }

                    $mntTerlambat = (int) ($log->menit_terlambat ?? 0);
                    $totalTerlambatMenit += $mntTerlambat;

                    if ($mntTerlambat >= 6 && $mntTerlambat <= 10) {
                        $terlambat6_10 += $mntTerlambat;
                        $jumlah6_10++;
                    } elseif ($mntTerlambat >= 11 && $mntTerlambat <= 15) {
                        $terlambat11_15 += $mntTerlambat;
                        $jumlah11_15++;
                    } elseif ($mntTerlambat >= 16) {
                        $terlambat16plus += $mntTerlambat;
                        $jumlah16plus++;
                    }

                    $days[] = [
                        'tanggal'      => $dateObj->format('d/m/Y'),
                        'tanggal_raw'  => $dStr,
                        'hari'         => $dateObj->translatedFormat('l'),
                        'jam_masuk'    => optional($log->waktu_masuk)->format('H:i') ?? '-',
                        'jam_keluar'   => optional($log->waktu_pulang)->format('H:i') ?? '-',
                        'durasi'       => $log->durasi_kerja ?? '-',
                        'status'       => $log->status_kehadiran ?? '-',
                        'menit_telat'  => $mntTerlambat,
                        'jarak'        => $log->jarak !== null ? $log->jarak . ' m' : '-',
                        'lembur_menit' => (int) round($mLembur),
                        'oncall_menit' => (int) round($mOncall),
                        'masuk_dinas'  => $isHadir ? 'Hadir' : '-',
                        'is_hadir'     => $isHadir,
                    ];
                } else {
                    // Hari tanpa absen / jadwal -> Dibuat strip (-) sesuai permintaan
                    $days[] = [
                        'tanggal'      => $dateObj->format('d/m/Y'),
                        'tanggal_raw'  => $dStr,
                        'hari'         => $dateObj->translatedFormat('l'),
                        'jam_masuk'    => '-',
                        'jam_keluar'   => '-',
                        'durasi'       => '-',
                        'status'       => '-',
                        'menit_telat'  => '-',
                        'jarak'        => '-',
                        'lembur_menit' => (int) round($mLembur),
                        'oncall_menit' => (int) round($mOncall),
                        'masuk_dinas'  => '-',
                        'is_hadir'     => false,
                    ];
                }
            }

            // Hitung potongan rupiah
            $rupiah6_10   = (int) round($jumlah6_10   * 25000 * 0.25);
            $rupiah11_15  = (int) round($jumlah11_15  * 25000 * 0.50);
            $rupiah16plus = (int) round($jumlah16plus * 25000 * 1.00);
            $totalPotonganRupiah = $rupiah6_10 + $rupiah11_15 + $rupiah16plus;

            $calendarData[] = [
                'user_id'              => $emp->id,
                'nama'                 => $emp->name ?? 'Tanpa Nama',
                'nik'                  => $emp->nik ?? '-',
                'unit'                 => $emp->unitKerja?->nama_unit ?? '-',
                'total_masuk_dinas'    => $totalMasukDinas,
                'total_lembur_menit'   => (int) round($totalLemburMenit),
                'total_oncall_menit'   => (int) round($totalOncallMenit),
                'total_terlambat_menit'=> $totalTerlambatMenit,
                'days'                 => $days,
                'summary'              => [
                    'total_masuk_dinas'     => $totalMasukDinas,
                    'total_lembur_menit'    => (int) round($totalLemburMenit),
                    'total_oncall_menit'    => (int) round($totalOncallMenit),
                    'total_terlambat_menit' => $totalTerlambatMenit,
                    'terlambat_6_10'        => $terlambat6_10,
                    'jumlah_6_10'           => $jumlah6_10,
                    'rupiah_6_10'           => $rupiah6_10,
                    'terlambat_11_15'       => $terlambat11_15,
                    'jumlah_11_15'          => $jumlah11_15,
                    'rupiah_11_15'          => $rupiah11_15,
                    'terlambat_16plus'      => $terlambat16plus,
                    'jumlah_16plus'         => $jumlah16plus,
                    'rupiah_16plus'         => $rupiah16plus,
                    'total_potongan_rupiah' => $totalPotonganRupiah,
                ],
            ];
        }

        return [$calendarData, $periodDates];
    }

    private function getAllowedUnitIds($user): ?array
    {
        if ($user->hasGlobalAccess()) {
            return null;
        }
        $ids = $user->managesUnits()->pluck('master_unit_kerja_id')->toArray();
        if ($user->unit_kerja_id && !in_array($user->unit_kerja_id, $ids)) {
            $ids[] = $user->unit_kerja_id;
        }
        return $ids;
    }

    private function buildData(string $startDate, string $endDate, $unit = null, ?array $allowedUnitIds = null)
    {
        $pengaturan = PengaturanAplikasi::first();
        $lat = $pengaturan ? (float) $pengaturan->latitude : 0;
        $lng = $pengaturan ? (float) $pengaturan->longitude : 0;

        $logs = LogAbsensi::select([
                'id', 'user_id', 'roster_id', 'tanggal_dinas', 'waktu_masuk', 'waktu_pulang',
                'menit_terlambat', 'status_kehadiran', 'jenis_absen',
                'latitude_masuk', 'longitude_masuk', 'latitude_pulang', 'longitude_pulang',
                'foto_masuk', 'foto_pulang', 'durasi_kerja'
            ])
            ->with([
                'user:id,name,nik,unit_kerja_id',
                'user.unitKerja:id,nama_unit',
                'roster:id,user_id,shift_id,tanggal_dinas,custom_jam_masuk,custom_jam_pulang',
                'roster.user:id,name,nik,unit_kerja_id',
                'roster.user.unitKerja:id,nama_unit',
                'roster.shift:id,nama_shift,jam_masuk,jam_pulang,toleransi_terlambat_menit'
            ])
            ->whereBetween('waktu_masuk', [$startDate, $endDate])
            ->when($unit, function ($q) use ($unit) {
                $q->where(function ($q2) use ($unit) {
                    $q2->whereHas('roster.user', fn($u) => $u->where('unit_kerja_id', $unit))
                        ->orWhereHas('user', fn($u) => $u->where('unit_kerja_id', $unit));
                });
            })
            ->when($allowedUnitIds !== null, function ($q) use ($allowedUnitIds) {
                $q->where(function ($q2) use ($allowedUnitIds) {
                    $q2->whereHas('roster.user', fn($u) => $u->whereIn('unit_kerja_id', $allowedUnitIds))
                        ->orWhereHas('user', fn($u) => $u->whereIn('unit_kerja_id', $allowedUnitIds));
                });
            })
            ->orderBy('waktu_masuk')
            ->get()
            ->map(function ($log) use ($lat, $lng) {
                // Hitung jarak
                $log->jarak_masuk = is_numeric($log->latitude_masuk)
                    ? round($this->haversine($lat, $lng, (float) $log->latitude_masuk, (float) $log->longitude_masuk))
                    : null;
                $log->jarak_pulang = is_numeric($log->latitude_pulang)
                    ? round($this->haversine($lat, $lng, (float) $log->latitude_pulang, (float) $log->longitude_pulang))
                    : null;
                $log->jarak = $log->jarak_masuk ?? $log->jarak_pulang;

                // ✅ HITUNG ULANG STATUS berdasarkan roster (real-time)
                $this->recalculateStatusForReport($log);

                // Hitung durasi kerja
                if ($log->waktu_masuk && $log->waktu_pulang) {
                    $m = $log->waktu_masuk->diffInMinutes($log->waktu_pulang);
                    $log->durasi_kerja = intdiv($m, 60) . 'j ' . ($m % 60) . 'm';
                } else {
                    $log->durasi_kerja = null;
                }

                return $log;
            });

        $lemburs = LogLembur::select([
                'id', 'user_id', 'jenis_lembur', 'waktu_mulai_lembur', 'waktu_selesai_lembur',
                'total_jam_lembur', 'status_validasi'
            ])
            ->with(['user:id,name,nik,unit_kerja_id'])
            ->where('status_validasi', 'Disetujui')   // ✅ filter utama
            ->whereBetween('waktu_mulai_lembur', [$startDate, $endDate])
            ->when($unit, fn($q) => $q->whereHas('user', fn($u) => $u->where('unit_kerja_id', $unit)))
            ->when($allowedUnitIds !== null, function ($q) use ($allowedUnitIds) {
                $q->whereHas('user', fn($u) => $u->whereIn('unit_kerja_id', $allowedUnitIds));
            })
            ->orderBy('waktu_mulai_lembur')
            ->get()
            ->groupBy(fn($l) => $l->user_id . '|' . $l->waktu_mulai_lembur->toDateString());

        return [$logs, $lemburs];
    }

    /**
     * ✅ Hitung ulang status untuk laporan (real-time, tidak mengubah database).
     * Jika ada roster → hitung keterlambatan berdasarkan shift.
     * Jika tidak ada roster → "Tanpa Jadwal" atau "Luar Jadwal".
     */
    private function recalculateStatusForReport($log): void
    {
        $roster = $log->roster;

        if (!$roster || !$log->waktu_masuk) {
            $log->status_kehadiran = ($log->jenis_absen === 'luar_jadwal')
                ? 'Luar Jadwal'
                : 'Tanpa Jadwal';
            $log->menit_terlambat = 0;
            return;
        }

        // ✅ Ambil jam dari custom atau shift
        $jamMasuk = $roster->custom_jam_masuk ?? ($roster->shift ? (string) $roster->shift->jam_masuk : null);
        $toleransi = $roster->shift ? (int) ($roster->shift->toleransi_terlambat_menit ?? 5) : 5;

        if (!$jamMasuk) {
            $log->status_kehadiran = 'Tanpa Jadwal';
            $log->menit_terlambat = 0;
            return;
        }

        // ✅ FIX: tanggal_dinas bisa berupa objek Carbon → format dulu ke Y-m-d
        $tanggalStr = \Carbon\Carbon::parse($roster->tanggal_dinas)->format('Y-m-d');
        $expected   = \Carbon\Carbon::parse($tanggalStr . ' ' . $jamMasuk);

        // Shift malam (overnight)
        $jamPulang = $roster->custom_jam_pulang ?? ($roster->shift ? (string) $roster->shift->jam_pulang : null);
        if ($jamPulang && $jamMasuk && $jamPulang < $jamMasuk && $log->waktu_masuk->hour < 12) {
            $expected->subDay();
        }

        $selisih   = (int) floor($expected->diffInMinutes($log->waktu_masuk, false));

        $log->menit_terlambat = ($selisih > $toleransi) ? (int) $selisih : 0;

        $log->status_kehadiran = ($log->jenis_absen === 'luar_jadwal')
            ? 'Luar Jadwal'
            : ($log->menit_terlambat > 0 ? 'Terlambat' : 'Tepat Waktu');
    }

    public function rekapLembur(Request $request)
    {
        $userLogin      = $request->user();
        $allowedUnitIds = $this->getAllowedUnitIds($userLogin);

        $bulan        = (int) $request->input('bulan', now()->month);
        $tahun        = (int) $request->input('tahun', now()->year);
        $statusFilter = $request->input('status', '');
        $unitFilter   = $request->input('unit_id', '');
        $tab          = $request->input('tab', 'dibayar'); // 'dibayar' | 'bank_jam' | 'pengambilan'

        $start = Carbon::createFromDate($tahun, $bulan, 1)->startOfDay();
        $end   = $start->copy()->endOfMonth()->endOfDay();

        // Dropdown Unit
        $unitsQuery = MasterUnitKerja::orderBy('nama_unit', 'asc');
        if ($allowedUnitIds !== null) {
            $unitsQuery->whereIn('id', $allowedUnitIds);
        }
        $units = $unitsQuery->get();

        // Radius RS untuk hitung jarak
        $pengaturan = PengaturanAplikasi::select(['latitude', 'longitude'])->first();
        $lat = $pengaturan ? (float) $pengaturan->latitude : 0;
        $lng = $pengaturan ? (float) $pengaturan->longitude : 0;

        // ==========================================
        // 1. DATA TAB 1: LEMBUR BERBAYAR (PAYROLL)
        // ==========================================
        $qDibayar = LogLembur::query()
            ->whereBetween('waktu_mulai_lembur', [$start, $end])
            ->whereHas('user.unitKerja', function ($u) {
                $u->where('jenis_lembur', 'dibayar');
            });

        if ($allowedUnitIds !== null) {
            $qDibayar->whereHas('user', fn($u) => $u->whereIn('unit_kerja_id', $allowedUnitIds));
        }
        if (!empty($unitFilter)) {
            $qDibayar->whereHas('user', fn($u) => $u->where('unit_kerja_id', $unitFilter));
        }

        // Stats Dibayar
        $statsDibayar = (clone $qDibayar)->selectRaw("
            COUNT(*) as total_pengajuan,
            COALESCE(SUM(CASE WHEN status_validasi = 'Disetujui' THEN total_jam_lembur ELSE 0 END), 0) as total_jam_disetujui,
            COALESCE(SUM(CASE WHEN status_validasi = 'Pending' THEN 1 ELSE 0 END), 0) as jml_pending
        ")->first();

        if (!empty($statusFilter)) {
            $qDibayar->where('status_validasi', $statusFilter);
        }

        $rowsDibayar = $qDibayar->with(['user.unitKerja'])
            ->orderByDesc('waktu_mulai_lembur')
            ->get()
            ->map(function ($l) use ($lat, $lng) {
                $jarak = '-';
                if (is_numeric($l->latitude_masuk) && is_numeric($l->longitude_masuk)) {
                    $jarak = round($this->haversine($lat, $lng, (float) $l->latitude_masuk, (float) $l->longitude_masuk)) . ' m';
                }

                $durasiMenit = (int) round(((float) $l->total_jam_lembur) * 60);

                return [
                    'id'              => $l->id,
                    'nama'            => $l->user?->name ?? '-',
                    'nik'             => $l->user?->nik ?? '-',
                    'unit'            => $l->user?->unitKerja?->nama_unit ?? '-',
                    'jenis'           => $l->jenis_lembur ?? '-',
                    'tanggal'         => optional($l->waktu_mulai_lembur)->format('d/m/Y') ?? '-',
                    'jam_masuk'       => optional($l->waktu_mulai_lembur)->format('H:i') ?? '-',
                    'jam_keluar'      => optional($l->waktu_selesai_lembur)->format('H:i') ?? '-',
                    'total_jam_raw'   => (float) $l->total_jam_lembur,
                    'durasi_menit'    => $durasiMenit,
                    'durasi'          => $durasiMenit . ' menit',
                    'jarak'           => $jarak,
                    'status'          => $l->status_validasi,
                    'alasan'          => $l->keterangan ?? '-',
                    'catatan'         => $l->catatan_validasi,
                    'divalidasi_pada' => optional($l->divalidasi_pada)->format('d/m/Y H:i'),
                ];
            });

        $totalMenitDibayarDisetujui = (int) round(((float) ($statsDibayar->total_jam_disetujui ?? 0)) * 60);

        // ==========================================
        // 2. DATA TAB 2: BANK JAM PEGAWAI (KOMPENSASI JAM)
        // ==========================================
        $qBankJam = User::query()
            ->whereHas('unitKerja', fn($u) => $u->where('jenis_lembur', 'ganti_jam'));

        if ($allowedUnitIds !== null) {
            $qBankJam->whereIn('unit_kerja_id', $allowedUnitIds);
        }
        if (!empty($unitFilter)) {
            $qBankJam->where('unit_kerja_id', $unitFilter);
        }

        $usersBankJam = $qBankJam->with(['unitKerja', 'updatedSaldoBy'])->orderBy('name', 'asc')->get();

        $rowsBankJam = $usersBankJam->map(function ($u) {
            $saldoAwalMenit = (int) ($u->saldo_awal_lembur_menit ?? 0);
            $menitLemburAplikasi = (int) round(
                LogLembur::where('user_id', $u->id)
                    ->where('status_validasi', 'Disetujui')
                    ->sum('total_jam_lembur') * 60
            );

            // Total perolehan sepanjang masa = Saldo Awal/Manual + Lembur Aplikasi
            $menitDiperoleh = $saldoAwalMenit + $menitLemburAplikasi;

            // Total yang sudah diambil sepanjang masa
            $menitDigunakan = (int) PengambilanLembur::where('user_id', $u->id)
                ->where('status_validasi', 'Disetujui')
                ->sum('durasi_menit');

            $menitPending = (int) PengambilanLembur::where('user_id', $u->id)
                ->where('status_validasi', 'Pending')
                ->sum('durasi_menit');

            $saldoBersihMenit   = max(0, $menitDiperoleh - $menitDigunakan);
            $saldoTersediaMenit = max(0, $saldoBersihMenit - $menitPending);

            $jSaldo = intdiv($saldoTersediaMenit, 60);
            $mSaldo = $saldoTersediaMenit % 60;

            return [
                'user_id'              => $u->id,
                'nama'                 => $u->name,
                'nik'                  => $u->nik ?? '-',
                'unit'                 => $u->unitKerja?->nama_unit ?? '-',
                'saldo_awal_menit'     => $saldoAwalMenit,
                'menit_aplikasi'       => $menitLemburAplikasi,
                'menit_diperoleh'      => $menitDiperoleh,
                'menit_digunakan'      => $menitDigunakan,
                'menit_pending'        => $menitPending,
                'saldo_menit'          => $saldoTersediaMenit,
                'keterangan_saldo'     => $u->keterangan_saldo_lembur,
                'updated_saldo_by'     => $u->updatedSaldoBy?->name,
                'updated_saldo_at'     => optional($u->updated_saldo_lembur_at)->format('d/m/Y H:i'),
                'menit_diperoleh_teks' => $menitDiperoleh . ' menit',
                'menit_digunakan_teks' => $menitDigunakan . ' menit',
                'saldo_tersedia_teks'  => $saldoTersediaMenit . ' menit',
                'saldo_jam_info'       => ($jSaldo > 0 ? "{$jSaldo}j " : '') . "{$mSaldo}m",
            ];
        });

        $totalSaldoMenitSemua = (int) $rowsBankJam->sum('saldo_menit');

        // ==========================================
        // 3. DATA TAB 3: PENGAMBILAN WAKTU LEMBUR
        // ==========================================
        $qPengambilan = PengambilanLembur::query()
            ->whereBetween('tanggal_dinas', [$start->toDateString(), $end->toDateString()]);

        if ($allowedUnitIds !== null) {
            $qPengambilan->whereHas('user', fn($u) => $u->whereIn('unit_kerja_id', $allowedUnitIds));
        }
        if (!empty($unitFilter)) {
            $qPengambilan->whereHas('user', fn($u) => $u->where('unit_kerja_id', $unitFilter));
        }

        // Stats Pengambilan
        $statsPengambilan = (clone $qPengambilan)->selectRaw("
            COUNT(*) as total_pengajuan,
            COALESCE(SUM(CASE WHEN status_validasi = 'Disetujui' THEN durasi_menit ELSE 0 END), 0) as total_menit_disetujui,
            COALESCE(SUM(CASE WHEN status_validasi = 'Pending' THEN 1 ELSE 0 END), 0) as jml_pending
        ")->first();

        if (!empty($statusFilter)) {
            $qPengambilan->where('status_validasi', $statusFilter);
        }

        $rowsPengambilan = $qPengambilan->with(['user.unitKerja', 'validator'])
            ->orderByDesc('tanggal_dinas')
            ->get()
            ->map(function ($p) {
                $j = intdiv($p->durasi_menit, 60);
                $m = $p->durasi_menit % 60;
                $durasiTeks = ($j > 0 ? "{$j} jam " : '') . ($m > 0 ? "{$m} mnt" : '');

                return [
                    'id'             => $p->id,
                    'nama'           => $p->user?->name ?? '-',
                    'nik'            => $p->user?->nik ?? '-',
                    'unit'           => $p->user?->unitKerja?->nama_unit ?? '-',
                    'tanggal'        => optional($p->tanggal_dinas)->format('d/m/Y') ?? '-',
                    'tipe_raw'       => $p->tipe_pengambilan,
                    'tipe_label'     => $p->tipe_pengambilan === 'masuk_diperlambat' ? 'Masuk Diperlambat' : 'Pulang Dipercepat',
                    'durasi_menit'   => $p->durasi_menit,
                    'durasi_teks'    => $p->durasi_menit . ' menit',
                    'status'         => $p->status_validasi,
                    'keterangan'     => $p->keterangan ?? '-',
                    'catatan'        => $p->catatan_validasi,
                    'validator_nama' => $p->validator?->name,
                    'divalidasi_pada'=> optional($p->divalidasi_pada)->format('d/m/Y H:i'),
                ];
            });

        $role = $userLogin->role;
        $canInputSaldo = in_array($role, ['superadmin', 'hrd', 'direktur', 'manajer', 'kepala_unit', 'penanggung_jawab']);

        return view('laporan.rekap-lembur', compact(
            'role',
            'canInputSaldo',
            'bulan',
            'tahun',
            'unitFilter',
            'statusFilter',
            'tab',
            'units',
            'statsDibayar',
            'totalMenitDibayarDisetujui',
            'rowsDibayar',
            'rowsBankJam',
            'totalSaldoMenitSemua',
            'statsPengambilan',
            'rowsPengambilan'
        ));
    }

    public function prosesValidasiLemburWeb(Request $request, $id)
    {
        $user = $request->user();
        if (!in_array($user->role, ['superadmin', 'hrd', 'direktur', 'manajer', 'kepala_unit', 'penanggung_jawab'])) {
            abort(403, 'Anda tidak memiliki hak akses untuk memvalidasi lembur.');
        }

        $request->validate([
            'status'  => 'required|in:Disetujui,Ditolak',
            'catatan' => 'nullable|string|max:255',
        ]);

        $lembur = LogLembur::with('user')->findOrFail($id);

        if (in_array($user->role, ['kepala_unit', 'penanggung_jawab', 'manajer'])) {
            $allowed = $this->getAllowedUnitIds($user);
            if ($allowed !== null && !in_array($lembur->user->unit_kerja_id, $allowed)) {
                abort(403, 'Pegawai ini bukan anggota unit yang Anda kelola.');
            }
        }

        $lembur->update([
            'status_validasi'  => $request->status,
            'catatan_validasi' => $request->catatan,
            'divalidasi_oleh'  => $user->id,
            'divalidasi_pada'  => now(),
        ]);

        return redirect()->back()->with('success', "Pengajuan lembur berhasil divalidasi ({$request->status}).");
    }

    public function prosesValidasiPengambilanWeb(Request $request, $id)
    {
        $user = $request->user();
        if (!in_array($user->role, ['superadmin', 'hrd', 'direktur', 'manajer', 'kepala_unit', 'penanggung_jawab'])) {
            abort(403, 'Anda tidak memiliki hak akses untuk memvalidasi pengambilan waktu.');
        }

        $request->validate([
            'status'  => 'required|in:Disetujui,Ditolak',
            'catatan' => 'nullable|string|max:255',
        ]);

        $pengambilan = PengambilanLembur::with('user')->findOrFail($id);

        if (in_array($user->role, ['kepala_unit', 'penanggung_jawab', 'manajer'])) {
            $allowed = $this->getAllowedUnitIds($user);
            if ($allowed !== null && !in_array($pengambilan->user->unit_kerja_id, $allowed)) {
                abort(403, 'Pegawai ini bukan anggota unit yang Anda kelola.');
            }
        }

        $pengambilan->update([
            'status_validasi'  => $request->status,
            'catatan_validasi' => $request->catatan,
            'divalidasi_oleh'  => $user->id,
            'divalidasi_pada'  => now(),
        ]);

        return redirect()->back()->with('success', "Pengajuan pengambilan waktu lembur berhasil divalidasi ({$request->status}).");
    }

    /**
     * Input / Penyesuaian Saldo Awal Jam Lembur Pegawai oleh Atasan (Ka. Instalasi/PJ/Manajer/HRD/Superadmin).
     */
    public function updateSaldoManual(Request $request, $userId)
    {
        $userLogin = $request->user();
        if (!in_array($userLogin->role, ['superadmin', 'hrd', 'direktur', 'manajer', 'kepala_unit', 'penanggung_jawab'])) {
            abort(403, 'Anda tidak memiliki hak akses untuk mengubah saldo lembur.');
        }

        $targetUser = User::with('unitKerja')->findOrFail($userId);

        if (in_array($userLogin->role, ['kepala_unit', 'penanggung_jawab', 'manajer'])) {
            $allowed = $this->getAllowedUnitIds($userLogin);
            if ($allowed !== null && !in_array($targetUser->unit_kerja_id, $allowed)) {
                abort(403, 'Pegawai ini bukan anggota unit yang Anda kelola.');
            }
        }

        $request->validate([
            'saldo_awal_menit' => 'required|integer|min:0',
            'keterangan'       => 'nullable|string|max:255',
        ]);

        $targetUser->update([
            'saldo_awal_lembur_menit' => (int) $request->saldo_awal_menit,
            'keterangan_saldo_lembur' => $request->keterangan,
            'updated_saldo_lembur_at' => now(),
            'updated_saldo_lembur_by' => $userLogin->id,
        ]);

        return redirect()->back()->with('success', "Saldo jam lembur untuk {$targetUser->name} berhasil diperbarui menjadi {$request->saldo_awal_menit} menit.");
    }

    public function exportLemburExcel(Request $request)
    {
        $userLogin      = $request->user();
        $allowedUnitIds = $this->getAllowedUnitIds($userLogin);

        $bulan        = (int) $request->input('bulan', now()->month);
        $tahun        = (int) $request->input('tahun', now()->year);
        $unitFilter   = $request->input('unit_id', '');

        $start = Carbon::createFromDate($tahun, $bulan, 1)->startOfDay();
        $end   = $start->copy()->endOfMonth()->endOfDay();

        $namaBulan = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        $periodLabel = ($namaBulan[$bulan] ?? $bulan) . ' ' . $tahun;

        // 1. Data Lembur Dibayar
        $qDibayar = LogLembur::query()
            ->whereBetween('waktu_mulai_lembur', [$start, $end])
            ->whereHas('user.unitKerja', fn($u) => $u->where('jenis_lembur', 'dibayar'));

        if ($allowedUnitIds !== null) {
            $qDibayar->whereHas('user', fn($u) => $u->whereIn('unit_kerja_id', $allowedUnitIds));
        }
        if (!empty($unitFilter)) {
            $qDibayar->whereHas('user', fn($u) => $u->where('unit_kerja_id', $unitFilter));
        }

        $dataDibayar = $qDibayar->with('user.unitKerja')
            ->orderByDesc('waktu_mulai_lembur')
            ->get()
            ->map(function ($l) {
                $durasiMenit = (int) round(((float) $l->total_jam_lembur) * 60);
                return [
                    'nama'          => $l->user?->name ?? '-',
                    'nik'           => $l->user?->nik ?? '-',
                    'unit'          => $l->user?->unitKerja?->nama_unit ?? '-',
                    'jenis'         => $l->jenis_lembur ?? '-',
                    'tanggal'       => optional($l->waktu_mulai_lembur)->format('d/m/Y') ?? '-',
                    'jam_masuk'     => optional($l->waktu_mulai_lembur)->format('H:i') ?? '-',
                    'jam_keluar'    => optional($l->waktu_selesai_lembur)->format('H:i') ?? '-',
                    'total_jam_raw' => (float) $l->total_jam_lembur,
                    'durasi_menit'  => $durasiMenit,
                    'durasi'        => $durasiMenit . ' menit',
                    'status'        => $l->status_validasi,
                    'alasan'        => $l->keterangan ?? '-',
                ];
            })->toArray();

        // 2. Data Bank Jam Pegawai (Sepanjang Masa / All-Time)
        $qBankJam = User::query()
            ->whereHas('unitKerja', fn($u) => $u->where('jenis_lembur', 'ganti_jam'));

        if ($allowedUnitIds !== null) {
            $qBankJam->whereIn('unit_kerja_id', $allowedUnitIds);
        }
        if (!empty($unitFilter)) {
            $qBankJam->where('unit_kerja_id', $unitFilter);
        }

        $dataBankJam = $qBankJam->with('unitKerja')->orderBy('name', 'asc')->get()
            ->map(function ($u) {
                $saldoAwalMenit = (int) ($u->saldo_awal_lembur_menit ?? 0);
                $menitLemburAplikasi = (int) round(
                    LogLembur::where('user_id', $u->id)
                        ->where('status_validasi', 'Disetujui')
                        ->sum('total_jam_lembur') * 60
                );
                $menitDiperoleh = $saldoAwalMenit + $menitLemburAplikasi;
                $menitDigunakan = (int) PengambilanLembur::where('user_id', $u->id)
                    ->where('status_validasi', 'Disetujui')
                    ->sum('durasi_menit');

                $saldoBersihMenit = max(0, $menitDiperoleh - $menitDigunakan);

                return [
                    'nama'                => $u->name,
                    'nik'                 => $u->nik ?? '-',
                    'unit'                => $u->unitKerja?->nama_unit ?? '-',
                    'saldo_awal_menit'    => $saldoAwalMenit,
                    'menit_diperoleh'     => $menitDiperoleh,
                    'menit_digunakan'     => $menitDigunakan,
                    'jam_diperoleh_teks'  => $menitDiperoleh . ' menit',
                    'jam_digunakan_teks'  => $menitDigunakan . ' menit',
                    'saldo_tersedia_teks' => $saldoBersihMenit . ' menit',
                    'saldo_menit'         => $saldoBersihMenit,
                ];
            })->toArray();

        // 3. Data Pengambilan Jam
        $qPengambilan = PengambilanLembur::query()
            ->whereBetween('tanggal_dinas', [$start->toDateString(), $end->toDateString()]);

        if ($allowedUnitIds !== null) {
            $qPengambilan->whereHas('user', fn($u) => $u->whereIn('unit_kerja_id', $allowedUnitIds));
        }
        if (!empty($unitFilter)) {
            $qPengambilan->whereHas('user', fn($u) => $u->where('unit_kerja_id', $unitFilter));
        }

        $dataPengambilan = $qPengambilan->with(['user.unitKerja', 'validator'])
            ->orderByDesc('tanggal_dinas')
            ->get()
            ->map(function ($p) {
                return [
                    'nama'           => $p->user?->name ?? '-',
                    'nik'            => $p->user?->nik ?? '-',
                    'unit'           => $p->user?->unitKerja?->nama_unit ?? '-',
                    'tanggal'        => optional($p->tanggal_dinas)->format('d/m/Y') ?? '-',
                    'tipe_label'     => $p->tipe_pengambilan === 'masuk_diperlambat' ? 'Masuk Diperlambat' : 'Pulang Dipercepat',
                    'durasi_menit'   => $p->durasi_menit,
                    'durasi_teks'    => $p->durasi_menit . ' menit',
                    'status'         => $p->status_validasi,
                    'keterangan'     => $p->keterangan ?? '-',
                    'validator_nama' => $p->validator?->name ?? '-',
                ];
            })->toArray();

        $filename = "Rekap_Lembur_BankJam_{$tahun}_{$bulan}.xlsx";

        return Excel::download(
            new RekapLemburExport($dataDibayar, $dataBankJam, $dataPengambilan, $periodLabel),
            $filename
        );
    }

    private function haversine($lat1, $lon1, $lat2, $lon2): float
    {
        $R = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
