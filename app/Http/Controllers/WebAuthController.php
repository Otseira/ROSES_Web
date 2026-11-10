<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use App\Models\User;

class WebAuthController extends Controller
{
    /**
     * Menampilkan halaman login
     */
    public function showLogin()
    {
        // Jika user sudah login, langsung arahkan sesuai role
        if (Auth::check()) {
            $user = Auth::user();
            if (in_array($user->role, ['staf', 'karyawan'])) {
                return redirect()->route('app.index');
            }
            return redirect()->intended('/dashboard');
        }

        return view('auth.login');
    }

    /**
     * Memproses data login dari form web
     */
    public function login(Request $request)
    {
        // 1. Validasi input
        $credentials = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        // 2. Cari user berdasarkan username atau NIK
        $user = User::where('username', $credentials['login'])
            ->orWhere('nik', $credentials['login'])
            ->first();

        // 3. Cek kredensial (User ada DAN password cocok)
        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            Log::warning('Percobaan login gagal (Kredensial tidak valid)', [
                'identifier' => $credentials['login'],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return back()->withErrors([
                'login' => 'Username/NIK atau kata sandi yang Anda masukkan salah.',
            ])->withInput($request->only('login'));
        }

        // 4. Deteksi Role Staf/Karyawan untuk Pengalihan ke Web Mobile (/app)
        $isMobileOnlyRole = in_array($user->role, ['staf', 'karyawan']);

        // 5. Cek Status Akun Aktif
        if (isset($user->is_active) && !$user->is_active) {
            Log::warning('Percobaan login ditolak (Akun nonaktif)', [
                'user_id' => $user->id,
                'nik' => $user->nik,
                'ip_address' => $request->ip(),
            ]);

            return back()->withErrors([
                'login' => 'Akun Anda telah dinonaktifkan. Silakan hubungi administrator.',
            ])->withInput($request->only('login'));
        }

        // 6. Login Berhasil
        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate(); // Mencegah Session Fixation

        Log::info('Login berhasil', [
            'user_id' => $user->id,
            'nik' => $user->nik,
            'role' => $user->role,
            'ip_address' => $request->ip(),
        ]);

        $targetPortal = $request->input('target_portal', 'web');

        // Jika user memilih Portal Mobile (SIRO Mobile iPhone):
        if ($targetPortal === 'mobile' || $request->input('target') === 'mobile') {
            return redirect()->route('app.index');
        }

        // Jika user memilih Portal Web:
        // Cek apakah role user dibatasi di dashboard desktop (misal staf/karyawan)
        $blockedRoles = config('auth.blocked_web_roles', ['staf', 'karyawan']);
        if (in_array($user->role, $blockedRoles)) {
            Log::info('Staf dialihkan ke SIRO Mobile dari login web', ['user_id' => $user->id]);
            return redirect()->route('app.index')->with('info', 'Akses Dashboard Web dibatasi untuk manajemen. Anda telah dialihkan ke SIRO Mobile.');
        }

        return redirect()->intended('/dashboard');
    }

    /**
     * Proses logout dari web
     */
    public function logout(Request $request)
    {
        if (Auth::check()) {
            Log::info('User logout', [
                'user_id' => Auth::id(),
                'nik' => Auth::user()->nik,
            ]);
        }

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken(); // Mencegah CSRF token reuse

        return redirect('/');
    }

    /**
     * Menampilkan form ubah kata sandi
     */
    public function editPassword()
    {
        return view('profil.ubah-password');
    }

    /**
     * Memproses pembaruan kata sandi
     */
    public function updatePassword(Request $request)
    {
        $request->validate([
            'password_lama' => ['required', 'string'],
            'password_baru' => ['required', 'string', 'min:6', 'confirmed'],
        ], [
            'password_baru.min' => 'Kata sandi baru minimal harus 6 karakter.',
            'password_baru.confirmed' => 'Konfirmasi kata sandi baru tidak cocok.',
        ]);

        $user = $request->user();

        // Cek apakah password lama yang dimasukkan benar
        if (!Hash::check($request->password_lama, $user->password)) {
            return back()->withErrors(['password_lama' => 'Kata sandi lama yang Anda masukkan salah.']);
        }

        // Simpan password baru
        $user->update([
            'password' => Hash::make($request->password_baru)
        ]);

        Log::info('Kata sandi berhasil diperbarui', [
            'user_id' => $user->id,
            'nik' => $user->nik,
        ]);

        return redirect('/dashboard')->with('success', 'Kata sandi Anda berhasil diperbarui demi keamanan.');
    }
}
