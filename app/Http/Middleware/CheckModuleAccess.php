<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckModuleAccess
{
    /**
     * Menangani permintaan (request) yang masuk.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $moduleCode (Parameter kode modul yang diwajibkan)
     */
    public function handle(Request $request, Closure $next, string $moduleCode): Response
    {
        // 1. Ambil data user yang sedang login melalui token Sanctum
        $user = $request->user();

        // 2. Periksa apakah user memiliki relasi ke kode_modul yang diminta
        // Kita menggunakan query 'exists()' agar pengecekan di database sangat cepat
        $punyaAkses = $user->moduls()->where('kode_modul', $moduleCode)->exists();

        // 3. Jika tidak punya akses, kunci gerbang dan kembalikan JSON Error 403
        if (!$punyaAkses) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Akun Anda tidak memiliki otoritas untuk modul: ' . $moduleCode,
            ], 403);
        }

        // 4. Jika lolos verifikasi, izinkan request melanjutkan perjalanan ke Controller
        return $next($request);
    }
}