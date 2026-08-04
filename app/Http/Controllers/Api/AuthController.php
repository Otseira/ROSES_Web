<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Logika Login Pegawai menggunakan Username atau NIK
     */
    public function login(Request $request)
    {
        // 1. Validasi Input Klien (Sekarang menggunakan 'login' untuk Username/NIK)
        $request->validate([
            'login' => 'required|string',
            'password' => 'required|string',
        ]);

        // 2. Cari user berdasarkan Username ATAU NIK, beserta relasi Unit Kerja
        $user = User::with('unitKerja')
            ->where(function ($q) use ($request) {
                $q->where('username', $request->login)
                    ->orWhere('nik', $request->login);
            })->first();

        // 3. Verifikasi keberadaan user dan kecocokan password
        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Username/NIK atau password yang Anda masukkan salah.',
            ], 401);
        }

        // 4. Hapus token lama jika ada (mencegah double login)
        $user->tokens()->delete();

        // 5. Buat token baru menggunakan Laravel Sanctum
        $token = $user->createToken('auth_token')->plainTextToken;

        // 6. Kembalikan JSON Response yang selaras dengan sistem RBAC baru
        return response()->json([
            'success' => true,
            'message' => 'Login berhasil.',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'data' => [
                'id' => $user->id,
                'nik' => $user->nik,
                'username' => $user->username,
                'nama' => $user->name,
                'email' => $user->email,
                'nomor_whatsapp' => $user->nomor_whatsapp,
                'unit_kerja' => $user->unitKerja ? $user->unitKerja->nama_unit : null,
                'role' => $user->role, // Menggantikan hak_akses/moduls
            ]
        ], 200);
    }

    /**
     * Mengambil data profil pegawai yang sedang login
     */
    public function me(Request $request)
    {
        // Mengambil user saat ini beserta unit kerjanya
        $user = $request->user()->load('unitKerja');

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'nik' => $user->nik,
                'username' => $user->username,
                'nama' => $user->name,
                'unit_kerja' => $user->unitKerja ? $user->unitKerja->nama_unit : null,
                'role' => $user->role,
            ]
        ], 200);
    }

    /**
     * Logika Logout Pegawai
     */
    public function logout(Request $request)
    {
        // Hapus token yang sedang digunakan saat ini dari database
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout berhasil, token telah dihapus.',
        ], 200);
    }
}
