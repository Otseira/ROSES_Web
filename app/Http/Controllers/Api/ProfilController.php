<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ProfilController extends Controller
{
    /** Perbarui data pribadi: nama, email, nomor_whatsapp.  PUT /api/profil */
    public function update(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'nama'           => 'required|string|max:255',
            'email'          => 'nullable|email|max:255|unique:users,email,' . $user->id,
            'nomor_whatsapp' => 'nullable|string|max:20',
        ]);

        $user->name           = $request->nama;
        $user->email          = $request->email;
        $user->nomor_whatsapp = $request->nomor_whatsapp;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Profil berhasil diperbarui.',
            'data'    => $this->formatUser($user->fresh()),
        ], 200);
    }

    /** Ganti password.  PUT /api/profil/password */
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

        return response()->json(['success' => true, 'message' => 'Password berhasil diubah.'], 200);
    }

    /** ✅ BARU — Upload / ganti foto profil.  POST /api/profil/foto  (multipart: field "foto") */
    public function updateFoto(Request $request)
    {
        $request->validate([
            'foto' => 'required|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $user = $request->user();
        $file = $request->file('foto');

        // Hapus foto lama dari storage (bila ada) agar tidak menumpuk
        if ($user->foto_profil) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($user->foto_profil);
        }

        $nik  = $user->nik ?? $user->id;
        $name = 'profil_' . preg_replace('/[^a-zA-Z0-9]/', '', (string) $nik) . '_' . time() . '.' . $file->extension();
        $path = $file->storeAs('foto_profil', $name, 'public');

        $user->foto_profil = $path;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Foto profil berhasil diperbarui.',
            'data'    => $this->formatUser($user->fresh()),
        ], 200);
    }

    /** Format user konsisten dengan /me & UserModel Flutter (termasuk URL foto). */
    private function formatUser($user)
    {
        $user->load('unitKerja');
        $base = rtrim((string) config('app.url'), '/');
        return [
            'id'             => $user->id,
            'nik'            => $user->nik,
            'username'       => $user->username,
            'nama'           => $user->name,
            'email'          => $user->email,
            'nomor_whatsapp' => $user->nomor_whatsapp,
            'unit_kerja'     => $user->unitKerja?->nama_unit,
            'role'           => $user->role,
            'foto_profil'    => $user->foto_profil ? ($base . '/storage/' . $user->foto_profil) : null,
        ];
    }
}