<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProfilController extends Controller
{
    /** 
     * Perbarui data pribadi: nama, username, email, nomor_whatsapp.  
     * PUT /api/profil 
     */
    public function update(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'nama'           => 'required|string|max:255',
            'username'       => [
                'required',
                'string',
                'min:3',
                'max:50',
                'alpha_dash',
                Rule::unique('users', 'username')->ignore($user->id),
            ],
            'email'          => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'nomor_whatsapp' => 'nullable|string|max:20',
        ]);

        $user->name           = $request->nama;
        $user->username       = $request->username;
        $user->email          = $request->email;
        $user->nomor_whatsapp = $request->nomor_whatsapp;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Profil berhasil diperbarui.',
            'data'    => $this->formatUser($user->fresh()),
        ], 200);
    }

    /** 
     * Ganti password.  
     * PUT /api/profil/password 
     */
    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password'     => 'required|string|min:6|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['success' => false, 'message' => 'Password saat ini salah.'], 422);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Password berhasil diganti.',
        ], 200);
    }

    /**
     * Update foto profil.
     * POST /api/profil/foto  (multipart/form-data, field: "foto")
     */
    public function updateFoto(Request $request)
    {
        $request->validate([
            'foto' => 'required|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $user = $request->user();

        // Hapus foto lama dari storage agar tidak menumpuk
        if ($user->foto_profil && Storage::disk('public')->exists($user->foto_profil)) {
            Storage::disk('public')->delete($user->foto_profil);
        }

        // Simpan foto baru ke storage/app/public/foto-profil
        $path = $request->file('foto')->store('foto-profil', 'public');

        $user->foto_profil = $path;
        $user->save();

        $baseUrl = rtrim((string) config('app.url'), '/');

        return response()->json([
            'success' => true,
            'message' => 'Foto profil berhasil diperbarui.',
            'data' => [
                'foto_profil' => $path,
                'foto_url'    => $baseUrl . '/storage/' . $path,
            ],
        ], 200);
    }

    /**
     * Format data user untuk response JSON
     */
    private function formatUser($user)
    {
        $baseUrl = rtrim((string) config('app.url'), '/');
        $fotoUrl = $user->foto_profil
            ? $baseUrl . '/storage/' . $user->foto_profil
            : null;

        return [
            'id'              => $user->id,
            'nik'             => $user->nik,
            'username'        => $user->username,
            'nama'            => $user->name,
            'email'           => $user->email,
            'nomor_whatsapp'  => $user->nomor_whatsapp,
            'unit_kerja'      => $user->unitKerja?->nama_unit,
            'role'            => $user->role,
            'foto_profil'     => $fotoUrl, // <-- agar Flutter langsung dapat URL lengkap
        ];
    }
}
