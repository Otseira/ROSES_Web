<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\LogAbsensi;
use App\Models\JadwalRoster; // Pastikan nama model sesuai dengan yang Anda gunakan
use Carbon\Carbon;

class WebDashboardController extends Controller
{
    public function index(Request $request)
    {
        $userLogin = $request->user();
        $hariIni = Carbon::today()->toDateString();
        $isGlobal = $userLogin->hasGlobalAccess();
        $canSeeManagement = $this->kkCanSee($userLogin);

        // Salam dinamis berdasarkan waktu lokal
        $hour = (int) Carbon::now()->format('H');
        if ($hour < 11) {
            $salam = 'Selamat Pagi';
        } elseif ($hour < 15) {
            $salam = 'Selamat Siang';
        } elseif ($hour < 18) {
            $salam = 'Selamat Sore';
        } else {
            $salam = 'Selamat Malam';
        }

        $scopeLabel = $isGlobal
            ? 'Seluruh Unit RS'
            : ($userLogin->unitKerja?->nama_unit ?? 'Unit Kerja Anda');

        // Managed unit IDs for non-global access
        $managedUnits = [];
        if (!$isGlobal) {
            $managedUnits = $userLogin->managesUnits()->pluck('master_unit_kerja_id')->toArray();
            if ($userLogin->unit_kerja_id && !in_array($userLogin->unit_kerja_id, $managedUnits)) {
                $managedUnits[] = $userLogin->unit_kerja_id;
            }
        }

        // Data Manajemen (Untuk Superadmin, HRD, Direktur, Manajer, Kepala Unit, PJ)
        $summary = null;
        $pendingLemburCount = 0;
        $recentAbsensi = collect();

        if ($canSeeManagement) {
            $h = $this->kkHitung($userLogin);
            $persenHadir = $h['dinas'] > 0 ? round(($h['hadir'] / $h['dinas']) * 100, 1) : 0;
            $summary = [
                'total'       => $h['total'],
                'dinas'       => $h['dinas'],
                'hadir'       => $h['hadir'],
                'terlambat'   => $h['terlambat'],
                'belum'       => $h['belum'],
                'tidak_dinas' => $h['tidak_dinas'],
                'persen'      => $persenHadir,
            ];

            // 1. Notifikasi lembur yang menunggu validasi
            $pendingQuery = \App\Models\LogLembur::where('status_validasi', 'Pending');
            if (!$isGlobal) {
                if (empty($managedUnits)) {
                    $pendingQuery->whereRaw('1 = 0');
                } else {
                    $pendingQuery->whereHas('user', function ($q) use ($managedUnits) {
                        $q->whereIn('unit_kerja_id', $managedUnits);
                    });
                }
            }
            $pendingLemburCount = $pendingQuery->count();

            // 2. Feed log absensi terkini hari ini (6 aktivitas terbaru)
            $recentQuery = LogAbsensi::select([
                    'id', 'user_id', 'roster_id', 'tanggal_dinas', 'waktu_masuk', 'waktu_pulang',
                    'menit_terlambat', 'status_kehadiran'
                ])
                ->with([
                    'user:id,name,nik,unit_kerja_id',
                    'user.unitKerja:id,nama_unit',
                    'roster.shift:id,nama_shift'
                ])
                ->where(function ($q) use ($hariIni) {
                    $q->whereDate('waktu_masuk', $hariIni)
                      ->orWhere('tanggal_dinas', $hariIni);
                })
                ->whereNotNull('waktu_masuk')
                ->latest('waktu_masuk');

            if (!$isGlobal) {
                if (empty($managedUnits)) {
                    $recentQuery->whereRaw('1 = 0');
                } else {
                    $recentQuery->whereHas('user', function ($q) use ($managedUnits) {
                        $q->whereIn('unit_kerja_id', $managedUnits);
                    });
                }
            }
            $recentAbsensi = $recentQuery->take(6)->get();
        }

        // Data Jadwal & Absensi Pribadi (Untuk Staf atau personal info user yang login)
        $myJadwalHariIni = JadwalRoster::with(['shift', 'logAbsensi'])
            ->where('user_id', $userLogin->id)
            ->where('tanggal_dinas', $hariIni)
            ->first();

        $myJadwalBesok = JadwalRoster::with('shift')
            ->where('user_id', $userLogin->id)
            ->where('tanggal_dinas', Carbon::tomorrow()->toDateString())
            ->first();

        $startBulan = Carbon::today()->startOfMonth()->toDateString();
        $endBulan   = Carbon::today()->endOfMonth()->toDateString();

        $myRekap = [
            'hadir'  => LogAbsensi::where('user_id', $userLogin->id)
                ->whereBetween('tanggal_dinas', [$startBulan, $endBulan])
                ->whereNotNull('waktu_masuk')
                ->count(),
            'telat'  => (int) LogAbsensi::where('user_id', $userLogin->id)
                ->whereBetween('tanggal_dinas', [$startBulan, $endBulan])
                ->sum('menit_terlambat'),
            'lembur' => (float) \App\Models\LogLembur::where('user_id', $userLogin->id)
                ->whereBetween('waktu_mulai_lembur', [$startBulan, $endBulan])
                ->where('status_validasi', 'Disetujui')
                ->sum('total_jam_lembur'),
        ];

        $myRecentAbsensi = LogAbsensi::with('roster.shift')
            ->where('user_id', $userLogin->id)
            ->latest('tanggal_dinas')
            ->take(5)
            ->get();

        return view('dashboard', compact(
            'userLogin',
            'hariIni',
            'salam',
            'scopeLabel',
            'canSeeManagement',
            'summary',
            'pendingLemburCount',
            'recentAbsensi',
            'myJadwalHariIni',
            'myJadwalBesok',
            'myRekap',
            'myRecentAbsensi'
        ));
    }

    private function kkCanSee($user)
    {
        return in_array($user->role, ['superadmin', 'hrd', 'direktur', 'manajer', 'manager', 'kepala_unit', 'penanggung_jawab']);
    }

    private function kkIsGlobal($user)
    {
        return $user->hasGlobalAccess();
    }

    private function kkScopeUsers($user)
    {
        return \App\Models\User::where('role', '!=', 'superadmin')
            ->when(!$this->kkIsGlobal($user), function ($q) use ($user) {
                $managed = $user->managesUnits()->pluck('master_unit_kerja_id')->toArray();
                if ($user->unit_kerja_id && !in_array($user->unit_kerja_id, $managed)) {
                    $managed[] = $user->unit_kerja_id;
                }
                $q->whereIn('unit_kerja_id', $managed);
            });
    }

    private function kkRostersHariIni($user)
    {
        return \App\Models\JadwalRoster::select(['id', 'user_id', 'shift_id', 'tanggal_dinas'])
            ->with([
                'shift:id,nama_shift',
                'logAbsensi:id,roster_id,waktu_masuk,menit_terlambat',
            ])
            ->where('tanggal_dinas', now()->toDateString())
            ->when(!$this->kkIsGlobal($user), function ($q) use ($user) {
                $managed = $user->managesUnits()->pluck('master_unit_kerja_id')->toArray();
                if ($user->unit_kerja_id && !in_array($user->unit_kerja_id, $managed)) {
                    $managed[] = $user->unit_kerja_id;
                }
                $q->whereHas('user', function ($u) use ($managed) {
                    $u->whereIn('unit_kerja_id', $managed);
                });
            })
            ->get();
    }

    private function kkIsLibur($roster)
    {
        $nama = strtolower(trim((string) ($roster->shift->nama_shift ?? '')));
        return $nama === '' || $nama === 'off' || str_contains($nama, 'libur');
    }

    private function kkHitung($user)
    {
        $rosters      = $this->kkRostersHariIni($user);
        $dinasR       = $rosters->reject(fn($r) => $this->kkIsLibur($r));
        $hadirR       = $dinasR->filter(fn($r) => !empty($r->logAbsensi?->waktu_masuk));
        $terlambatR   = $hadirR->filter(fn($r) => ($r->logAbsensi?->menit_terlambat ?? 0) > 0);

        $dinasIds     = $dinasR->pluck('user_id')->unique()->values();
        $hadirIds     = $hadirR->pluck('user_id')->unique()->values();
        $terlambatIds = $terlambatR->pluck('user_id')->unique()->values();
        $belumIds     = $dinasIds->diff($hadirIds)->values();

        $total        = $this->kkScopeUsers($user)->count();
        $rosterOrang  = $rosters->pluck('user_id')->unique()->count();
        $tidakDinas   = max(0, $total - $rosterOrang);

        return [
            'total'          => $total,
            'dinas'          => $dinasIds->count(),
            'hadir'          => $hadirIds->count(),
            'terlambat'      => $terlambatIds->count(),
            'belum'          => $belumIds->count(),
            'tidak_dinas'    => $tidakDinas,
            'dinas_ids'      => $dinasIds->all(),
            'hadir_ids'      => $hadirIds->all(),
            'terlambat_ids'  => $terlambatIds->all(),
            'belum_ids'      => $belumIds->all(),
        ];
    }

    public function ringkasanKaryawan(Request $request)
    {
        $user = $request->user();
        if (!$this->kkCanSee($user)) {
            return response()->json(['can_see' => false]);
        }
        $h = $this->kkHitung($user);
        $persenHadir = $h['dinas'] > 0 ? round(($h['hadir'] / $h['dinas']) * 100, 1) : 0;

        return response()->json([
            'can_see'     => true,
            'scope_label' => $this->kkIsGlobal($user)
                ? 'Seluruh Unit'
                : ($user->unitKerja?->nama_unit ?? 'Unit Anda'),
            'total'       => $h['total'],
            'dinas'       => $h['dinas'],
            'hadir'       => $h['hadir'],
            'terlambat'   => $h['terlambat'],
            'belum'       => $h['belum'],
            'tidak_dinas' => $h['tidak_dinas'],
            'persen'      => $persenHadir,
        ]);
    }

    public function karyawanHariIni(Request $request)
    {
        $user = $request->user();
        if (!$this->kkCanSee($user)) {
            return response()->json(['message' => 'Dilarang.'], 403);
        }

        $filter = $request->query('filter', 'total');
        $h      = $this->kkHitung($user);

        switch ($filter) {
            case 'hadir':
                $q = \App\Models\User::whereIn('id', $h['hadir_ids']);
                break;
            case 'terlambat':
                $q = \App\Models\User::whereIn('id', $h['terlambat_ids']);
                break;
            case 'belum':
                $q = \App\Models\User::whereIn('id', $h['belum_ids']);
                break;
            case 'dinas':
                $q = \App\Models\User::whereIn('id', $h['dinas_ids']);
                break;
            default:
                $q = $this->kkScopeUsers($user);
                break; // total
        }

        $todayRosters = $this->kkRostersHariIni($user)->keyBy('user_id');

        $rows = $q->select(['id', 'name', 'nik', 'unit_kerja_id'])
            ->with('unitKerja:id,nama_unit')
            ->orderBy('name', 'asc')
            ->get()
            ->map(function ($x) use ($todayRosters) {
                $roster = $todayRosters->get($x->id);
                $log = $roster?->logAbsensi;
                $jamMasuk = $log?->waktu_masuk ? Carbon::parse($log->waktu_masuk)->format('H:i') : null;
                $menitTelat = (int) ($log?->menit_terlambat ?? 0);
                $shiftNama = $roster?->shift?->nama_shift ?? '-';

                $statusText = 'Belum Absen';
                if ($log && $log->waktu_masuk) {
                    $statusText = $log->status_kehadiran ?: ($menitTelat > 0 ? 'Terlambat' : 'Tepat Waktu');
                } elseif (!$roster) {
                    $statusText = 'Tidak Dinas';
                }

                return [
                    'nama'        => $x->name,
                    'nik'         => $x->nik ?: '-',
                    'unit'        => $x->unitKerja?->nama_unit ?? '-',
                    'shift'       => $shiftNama,
                    'jam_masuk'   => $jamMasuk,
                    'menit_telat' => $menitTelat,
                    'status'      => $statusText,
                ];
            })->values();

        return response()->json(['count' => $rows->count(), 'rows' => $rows]);
    }
}
