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

        // Cek wewenang
        $isGlobal = $userLogin->role === 'superadmin';

        // 1. Ambil query roster jadwal hari ini
        $queryJadwal = JadwalRoster::where('tanggal_dinas', $hariIni);

        // Jika bukan superadmin, kunci jadwal hanya untuk unit kerja user yang login
        if (!$isGlobal) {
            $queryJadwal->whereHas('user', function ($q) use ($userLogin) {
                $q->where('unit_kerja_id', $userLogin->unit_kerja_id);
            });
        }

        // 2. Kalkulasi Data Statistik
        $rosterHariIni = $queryJadwal->get();
        $totalJadwal = $rosterHariIni->count();
        $rosterIds = $rosterHariIni->pluck('id');

        // Tarik log absensi yang berkaitan dengan roster hari ini
        $logsAbsen = LogAbsensi::whereIn('roster_id', $rosterIds)->get();

        $hadir = $logsAbsen->whereNotNull('waktu_masuk')->count();
        $terlambat = $logsAbsen->where('menit_terlambat', '>', 0)->count();
        $belumHadir = $totalJadwal - $hadir;

        // MENGIRIM VARIABEL KE VIEW DASHBOARD
        return view('dashboard', compact('totalJadwal', 'hadir', 'terlambat', 'belumHadir', 'hariIni'));
    }

    private function kkCanSee($user)
    {
        return in_array($user->role, ['superadmin', 'hrd', 'kepala_unit', 'penanggung_jawab']);
    }

    private function kkIsGlobal($user)
    {
        return in_array($user->role, ['superadmin', 'hrd']);
    }

    private function kkScopeUsers($user)
    {
        return \App\Models\User::where('role', '!=', 'superadmin')
            ->when(!$this->kkIsGlobal($user), function ($q) use ($user) {
                $q->where('unit_kerja_id', $user->unit_kerja_id);
            });
    }

    private function kkRostersHariIni($user)
    {
        return \App\Models\JadwalRoster::with(['shift', 'user.unitKerja', 'logAbsensi'])
            ->where('tanggal_dinas', now()->toDateString())
            ->when(!$this->kkIsGlobal($user), function ($q) use ($user) {
                $q->whereHas('user', function ($u) use ($user) {
                    $u->where('unit_kerja_id', $user->unit_kerja_id);
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
        $rosters  = $this->kkRostersHariIni($user);
        $dinasR   = $rosters->reject(fn($r) => $this->kkIsLibur($r));
        $hadirR   = $dinasR->filter(fn($r) => !empty($r->logAbsensi?->waktu_masuk));

        $dinasIds = $dinasR->pluck('user_id')->unique()->values();
        $hadirIds = $hadirR->pluck('user_id')->unique()->values();
        $belumIds = $dinasIds->diff($hadirIds)->values();

        $total        = $this->kkScopeUsers($user)->count();
        $rosterOrang  = $rosters->pluck('user_id')->unique()->count();
        $tidakDinas   = max(0, $total - $rosterOrang);

        return [
            'total'       => $total,
            'dinas'       => $dinasIds->count(),
            'hadir'       => $hadirIds->count(),
            'belum'       => $belumIds->count(),
            'tidak_dinas' => $tidakDinas,
            'dinas_ids'   => $dinasIds->all(),
            'hadir_ids'   => $hadirIds->all(),
            'belum_ids'   => $belumIds->all(),
        ];
    }

    public function ringkasanKaryawan(Request $request)
    {
        $user = $request->user();
        if (!$this->kkCanSee($user)) {
            return response()->json(['can_see' => false]);
        }
        $h = $this->kkHitung($user);
        return response()->json([
            'can_see'     => true,
            'scope_label' => $this->kkIsGlobal($user)
                ? 'Seluruh Unit'
                : ($user->unitKerja?->nama_unit ?? 'Unit Anda'),
            'total'       => $h['total'],
            'dinas'       => $h['dinas'],
            'hadir'       => $h['hadir'],
            'belum'       => $h['belum'],
            'tidak_dinas' => $h['tidak_dinas'],
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

        $rows = $q->with('unitKerja')
            ->get(['id', 'name', 'nik', 'unit_kerja_id'])
            ->map(fn($x) => [
                'nama' => $x->name,
                'nik'  => $x->nik,
                'unit' => $x->unitKerja?->nama_unit ?? '-',
            ])->values();

        return response()->json(['count' => $rows->count(), 'rows' => $rows]);
    }
}
