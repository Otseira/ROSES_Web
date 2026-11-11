<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Api\AbsensiController as ApiAbsensiController;
use App\Http\Controllers\Api\LemburController as ApiLemburController;
use App\Http\Controllers\Api\ProfilController as ApiProfilController;
use App\Http\Controllers\Api\RosterController as ApiRosterController;
use App\Models\JadwalRoster;
use App\Models\LogAbsensi;
use App\Models\LogLembur;
use App\Models\PengaturanAplikasi;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class WebMobileController extends Controller
{
    /**
     * Tampilan Login khusus Web Mobile (ROSES Mobile Web)
     */
    public function loginView()
    {
        if (Auth::check()) {
            return redirect()->route('app.index');
        }

        $pengaturan = PengaturanAplikasi::first();

        return view('mobile.login', compact('pengaturan'));
    }

    /**
     * Proses Login khusus Web Mobile (Menerima SEMUA role karyawan termasuk staf)
     */
    public function loginAction(Request $request)
    {
        $credentials = $request->validate([
            'login'    => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $user = User::with('unitKerja')
            ->where('username', $credentials['login'])
            ->orWhere('nik', $credentials['login'])
            ->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Username/NIK atau kata sandi yang Anda masukkan salah.',
                ], 422);
            }

            return back()->withErrors([
                'login' => 'Username/NIK atau kata sandi yang Anda masukkan salah.',
            ])->withInput($request->only('login'));
        }

        if (isset($user->is_active) && !$user->is_active) {
            $msg = 'Akun Anda dinonaktifkan. Silakan hubungi administrator.';
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => $msg], 403);
            }
            return back()->withErrors(['login' => $msg])->withInput($request->only('login'));
        }

        Auth::login($user, true); // Selalu remember token untuk mobile experience
        $request->session()->regenerate();

        Log::info('Login Mobile Web Berhasil', [
            'user_id'    => $user->id,
            'nik'        => $user->nik,
            'user_agent' => $request->userAgent(),
        ]);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success'  => true,
                'message'  => 'Login berhasil.',
                'redirect' => route('app.index'),
            ]);
        }

        return redirect()->route('app.index');
    }

    /**
     * Shell Aplikasi Utama ROSES Mobile Web
     */
    public function index(Request $request)
    {
        $user = Auth::user()->load('unitKerja');
        $pengaturan = PengaturanAplikasi::first();

        // Data awal yang dibawa ke view
        return view('mobile.index', [
            'user'       => $user,
            'pengaturan' => $pengaturan,
        ]);
    }

    /**
     * Cek status absensi hari ini, shift, bank jam, dan on-call aktif
     */
    public function todayStatus(Request $request): JsonResponse
    {
        $user = $request->user()->load('unitKerja');
        $now = Carbon::now();
        $today = $now->toDateString();

        // Roster hari ini
        $rosterHariIni = JadwalRoster::with('shift')
            ->where('user_id', $user->id)
            ->where('tanggal_dinas', $today)
            ->orderBy('sesi')
            ->first();

        // Log absensi hari ini
        $logHariIni = LogAbsensi::with('roster.shift')
            ->where('user_id', $user->id)
            ->whereDate('waktu_masuk', $today)
            ->latest('waktu_masuk')
            ->first();

        // Jika tidak ada absensi hari ini, cek apakah ada sesi malam kemarin yang belum pulang
        if (!$logHariIni) {
            $logHariIni = LogAbsensi::with('roster.shift')
                ->where('user_id', $user->id)
                ->whereNull('waktu_pulang')
                ->where('waktu_masuk', '>=', $now->copy()->subHours(24))
                ->first();
        }

        // On-Call aktif
        $onCallAktif = LogLembur::where('user_id', $user->id)
            ->where('jenis_lembur', 'On-Call')
            ->whereNull('waktu_selesai_lembur')
            ->latest('waktu_mulai_lembur')
            ->first();

        // Bank jam
        $bankJam = null;
        if ($user->isGantiJam()) {
            $lemburController = new ApiLemburController();
            $bankJamRes = $lemburController->getBankJam($request);
            if ($bankJamRes->getStatusCode() === 200) {
                $bankJam = $bankJamRes->getData(true)['data'] ?? null;
            }
        }

        return response()->json([
            'success'    => true,
            'csrf_token' => csrf_token(),
            'data'       => [
                'tanggal'           => $now->translatedFormat('l, d F Y'),
                'jam_sekarang'      => $now->format('H:i'),
                'shift_nama'        => $rosterHariIni?->shift?->nama_shift ?? 'Tanpa Jadwal',
                'jam_masuk_jadwal'  => $rosterHariIni?->shift?->jam_masuk ? substr($rosterHariIni->shift->jam_masuk, 0, 5) : '-',
                'jam_pulang_jadwal' => $rosterHariIni?->shift?->jam_pulang ? substr($rosterHariIni->shift->jam_pulang, 0, 5) : '-',
                'is_masuk_done'     => $logHariIni && $logHariIni->waktu_masuk !== null,
                'waktu_masuk'       => $logHariIni?->waktu_masuk ? Carbon::parse($logHariIni->waktu_masuk)->format('H:i') : null,
                'is_pulang_done'    => $logHariIni && $logHariIni->waktu_pulang !== null,
                'waktu_pulang'      => $logHariIni?->waktu_pulang ? Carbon::parse($logHariIni->waktu_pulang)->format('H:i') : null,
                'status_kehadiran'  => $logHariIni?->status_kehadiran,
                'menit_terlambat'   => $logHariIni?->menit_terlambat ?? 0,
                'on_call_aktif'     => $onCallAktif ? [
                    'id'           => $onCallAktif->id,
                    'waktu_mulai'  => Carbon::parse($onCallAktif->waktu_mulai_lembur)->format('H:i'),
                    'tanggal_mulai'=> Carbon::parse($onCallAktif->waktu_mulai_lembur)->translatedFormat('d M Y'),
                    'keterangan'   => $onCallAktif->alasan_lembur,
                ] : null,
                'bank_jam'          => $bankJam,
                'can_validasi'      => $user->canValidasi(),
            ],
        ]);
    }

    /**
     * Refresh CSRF Token untuk PWA Safari yang baru dibangunkan dari background
     */
    public function csrfToken(): JsonResponse
    {
        return response()->json([
            'success'    => true,
            'csrf_token' => csrf_token(),
        ]);
    }

    /**
     * Live Geofencing Distance Check untuk browser/Safari
     */
    public function checkLocation(Request $request): JsonResponse
    {
        $request->validate([
            'latitude'  => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'accuracy'  => 'nullable|numeric',
        ]);

        if ((float) $request->latitude == 0.0 && (float) $request->longitude == 0.0) {
            return response()->json([
                'success'        => true,
                'in_radius'      => false,
                'accuracy_low'   => true,
                'distance_meter' => 0,
                'max_radius'     => 100,
                'message'        => 'Koordinat GPS tidak valid (0,0). Pastikan GPS iPhone aktif.',
            ]);
        }

        $pengaturan = PengaturanAplikasi::first();
        if (!$pengaturan || !$pengaturan->latitude || !$pengaturan->longitude) {
            return response()->json([
                'success'        => true,
                'in_radius'      => true,
                'accuracy_low'   => false,
                'distance_meter' => 0,
                'max_radius'     => 100,
                'message'        => 'Titik GPS kantor belum diset (Bebas radius)',
            ]);
        }

        $distance = $this->haversine(
            (float) $pengaturan->latitude,
            (float) $pengaturan->longitude,
            (float) $request->latitude,
            (float) $request->longitude
        );

        $maxRadius = (float) $this->getMaxRadius($pengaturan);
        $inRadius = $distance <= $maxRadius;

        $acc = $request->filled('accuracy') ? (float) $request->accuracy : null;
        $accuracyLow = $acc !== null && $acc > 150.0;
        $accInfo = $acc !== null ? ' • Akurasi ±' . round($acc) . 'm' : '';

        if ($accuracyLow) {
            $message = 'Sinyal GPS kurang akurat (±' . round($acc) . 'm). Dekatkan ke jendela atau tekan Refresh GPS.';
        } elseif ($inRadius) {
            $message = 'Dalam radius RS (' . round($distance) . 'm dari batas ' . $maxRadius . 'm)' . $accInfo;
        } else {
            $message = 'Di luar radius RS (' . round($distance) . 'm dari batas ' . $maxRadius . 'm)' . $accInfo;
        }

        return response()->json([
            'success'        => true,
            'in_radius'      => $inRadius,
            'accuracy_low'   => $accuracyLow,
            'distance_meter' => round($distance, 1),
            'max_radius'     => $maxRadius,
            'accuracy'       => $acc !== null ? round($acc, 1) : null,
            'message'        => $message,
        ]);
    }

    /**
     * Presensi Masuk
     */
    public function clockIn(Request $request)
    {
        return (new ApiAbsensiController())->clockIn($request);
    }

    /**
     * Presensi Pulang
     */
    public function clockOut(Request $request)
    {
        return (new ApiAbsensiController())->clockOut($request);
    }

    /**
     * Jadwal Dinas / Kalender Roster
     */
    public function jadwalDinas(Request $request)
    {
        return (new ApiRosterController())->jadwalDinas($request);
    }

    /**
     * Data Lengkap Lembur, Riwayat, dan Bank Jam
     */
    public function lemburData(Request $request): JsonResponse
    {
        $lemburController = new ApiLemburController();

        $riwayatLemburRes = $lemburController->riwayatSaya($request);
        $riwayatLembur = $riwayatLemburRes->getStatusCode() === 200
            ? ($riwayatLemburRes->getData(true)['data'] ?? [])
            : [];

        $bankJamRes = $lemburController->getBankJam($request);
        $bankJam = $bankJamRes->getStatusCode() === 200
            ? ($bankJamRes->getData(true)['data'] ?? null)
            : null;

        $riwayatAmbilJamRes = $lemburController->riwayatPengambilanJam($request);
        $riwayatAmbilJam = $riwayatAmbilJamRes->getStatusCode() === 200
            ? ($riwayatAmbilJamRes->getData(true)['data'] ?? [])
            : [];

        return response()->json([
            'success' => true,
            'data'    => [
                'riwayat_lembur'    => $riwayatLembur,
                'bank_jam'          => $bankJam,
                'riwayat_ambil_jam' => $riwayatAmbilJam,
            ],
        ]);
    }

    /**
     * Pengajuan Lembur Ekstensi Shift
     */
    public function storeEkstensi(Request $request)
    {
        return (new ApiLemburController())->storeEkstensi($request);
    }

    /**
     * Clock-In On-Call Darurat
     */
    public function clockInOnCall(Request $request)
    {
        return (new ApiLemburController())->clockInOnCall($request);
    }

    /**
     * Clock-Out On-Call Darurat
     */
    public function clockOutOnCall(Request $request)
    {
        return (new ApiLemburController())->clockOutOnCall($request);
    }

    /**
     * Pengajuan Ambil Saldo Jam (Time-off in Lieu)
     */
    public function storePengambilanJam(Request $request)
    {
        return (new ApiLemburController())->storePengambilanJam($request);
    }

    /**
     * Daftar Permohonan Pending untuk Atasan
     */
    public function validasiList(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->canValidasi()) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki hak akses validasi.',
            ], 403);
        }

        $lemburController = new ApiLemburController();

        // 1. Validasi Lembur & On-Call
        $resLembur = $lemburController->listValidasi($request);
        $listLembur = $resLembur->getStatusCode() === 200
            ? ($resLembur->getData(true)['data'] ?? [])
            : [];

        // 2. Validasi Pengambilan Saldo Jam
        $resAmbil = $lemburController->listValidasiPengambilanJam($request);
        $listAmbil = $resAmbil->getStatusCode() === 200
            ? ($resAmbil->getData(true)['data'] ?? [])
            : [];

        return response()->json([
            'success' => true,
            'data'    => [
                'lembur'    => $listLembur,
                'ambil_jam' => $listAmbil,
            ],
        ]);
    }

    /**
     * Proses Validasi Lembur / On-Call
     */
    public function validasiProses(Request $request, $id)
    {
        return (new ApiLemburController())->prosesValidasi($request, $id);
    }

    /**
     * Proses Validasi Pengambilan Saldo Jam
     */
    public function validasiPengambilanProses(Request $request, $id)
    {
        return (new ApiLemburController())->prosesValidasiPengambilanJam($request, $id);
    }

    /**
     * Update Foto Profil
     */
    public function updateFotoProfil(Request $request)
    {
        return (new ApiProfilController())->updateFoto($request);
    }

    /**
     * Update Password
     */
    public function updatePassword(Request $request)
    {
        return (new ApiProfilController())->updatePassword($request);
    }

    /**
     * Logout dari Mobile Web
     */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('app.login');
    }

    /**
     * Dynamic Web App Manifest untuk PWA (Installable di Home Screen iPhone)
     */
    public function manifest(): JsonResponse
    {
        $pengaturan = PengaturanAplikasi::first();
        $name = $pengaturan?->nama_instansi ?: 'ROSES Mobile';
        $shortName = 'ROSES';
        $logo = $pengaturan?->logo ? '/storage/' . $pengaturan->logo : '/favicon.ico';

        $manifest = [
            'name'             => $name,
            'short_name'       => $shortName,
            'description'      => 'Aplikasi Presensi dan Operasional Pegawai ' . $name,
            'start_url'        => '/app',
            'display'          => 'standalone',
            'background_color' => '#F8FAFC',
            'theme_color'      => '#10B981',
            'orientation'      => 'portrait',
            'icons'            => [
                [
                    'src'     => $logo,
                    'sizes'   => '192x192',
                    'type'    => 'image/png',
                    'purpose' => 'any maskable',
                ],
                [
                    'src'     => $logo,
                    'sizes'   => '512x512',
                    'type'    => 'image/png',
                    'purpose' => 'any maskable',
                ],
            ],
        ];

        return response()->json($manifest)
            ->header('Content-Type', 'application/manifest+json');
    }

    /**
     * Ambil nilai radius dari Pengaturan secara dinamis
     */
    private function getMaxRadius(PengaturanAplikasi $pengaturan): int
    {
        foreach ($pengaturan->getAttributes() as $key => $val) {
            if (str_contains($key, 'radius') && $val !== null && $val !== '') {
                return (int) $val;
            }
        }

        return 100;
    }

    /**
     * Perhitungan Jarak GPS Geofencing (Haversine Formula)
     */
    private function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $R    = 6371000; // Radius bumi dalam meter
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a    = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
