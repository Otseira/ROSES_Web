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
        $request->validate([
            'login' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = User::with('unitKerja')
            ->where(function ($q) use ($request) {
                $q->where('username', $request->login)
                    ->orWhere('nik', $request->login);
            })->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Username/NIK atau password yang Anda masukkan salah.',
            ], 401);
        }

        $user->tokens()->delete();
        $token = $user->createToken('auth_token')->plainTextToken;

        // Load relasi managesUnits untuk user yang punya role manajemen
        $managesUnits = [];
        if ($user->isManajemen()) {
            $managesUnits = $user->managesUnits->map(function ($unit) {
                return [
                    'id' => $unit->id,
                    'nama_unit' => $unit->nama_unit,
                ];
            })->toArray();
        }

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
                'role' => $user->role,
                'manages_units' => $managesUnits, // <-- DATA BARU
            ]
        ], 200);
    }

    /**
     * Mengambil data profil pegawai yang sedang login
     */
    public function me(Request $request)
    {
        $user = $request->user()->load('unitKerja');

        $managesUnits = [];
        if ($user->isManajemen()) {
            $managesUnits = $user->managesUnits->map(function ($unit) {
                return [
                    'id' => $unit->id,
                    'nama_unit' => $unit->nama_unit,
                ];
            })->toArray();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'nik' => $user->nik,
                'username' => $user->username,
                'nama' => $user->name,
                'unit_kerja' => $user->unitKerja ? $user->unitKerja->nama_unit : null,
                'role' => $user->role,
                'manages_units' => $managesUnits, // <-- DATA BARU
            ]
        ], 200);
    }

    /**
     * Logika Logout Pegawai
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout berhasil, token telah dihapus.',
        ], 200);
    }
}
