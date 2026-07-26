<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ProfilController extends Controller
{
    /**
     * Perbarui data pribadi: nama, email, nomor_whatsapp.
     * PUT /api/profil
     */
    public function update(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'nama'           => 'required|string|max:255',
            'email'          => 'nullable|email|max:255|unique:users,email,' . $user->id,
            'nomor_whatsapp' => 'nullable|string|max:20',
        ]);

        // Hanya kolom pribadi yang ditulis — role/unit/nik/username TIDAK disentuh
        $user->name           = $request->nama;
        $user->email          = $request->email;          // null bila dikosongkan
        $user->nomor_whatsapp = $request->nomor_whatsapp; // null bila dikosongkan
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Profil berhasil diperbarui.',
            'data'    => $this->formatUser($user->fresh()),
        ], 200);
    }

    /**
     * Ganti password (verifikasi password lama manual — aman untuk Sanctum).
     * PUT /api/profil/password
     */
    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password'     => 'required|string|min:6|confirmed',
        ]);

        $user = $request->user();

        // Hash::check manual: tidak bergantung pada guard, pasti benar untuk token Sanctum
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Password saat ini salah.',
            ], 422);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Password berhasil diubah.',
        ], 200);
    }

    /** Format user agar konsisten dengan /me & UserModel Flutter. */
    private function formatUser($user)
    {
        $user->load('unitKerja');
        return [
            'id'             => $user->id,
            'nik'            => $user->nik,
            'username'       => $user->username,
            'nama'           => $user->name,
            'email'          => $user->email,
            'nomor_whatsapp' => $user->nomor_whatsapp,
            'unit_kerja'     => $user->unitKerja?->nama_unit,
            'role'           => $user->role,
        ];
    }
}