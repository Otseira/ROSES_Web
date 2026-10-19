<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\MasterModul;
use Illuminate\Http\Request;

class WebHakAksesController extends Controller
{
    /**
     * Menampilkan daftar semua pengguna beserta modul yang mereka miliki
     */
    public function index(Request $request)
    {
        if (!$request->user()->isSuperadmin()) {
            abort(403, 'Akses ditolak. Pengaturan hak akses hanya dapat dikelola oleh Super Administrator.');
        }

        // Tarik data user beserta relasi unit kerja dan modulnya
        $users = User::with(['unitKerja', 'moduls'])->get();
        return view('hak-akses.index', compact('users'));
    }

    /**
     * Menampilkan formulir untuk mengubah hak akses 1 pengguna spesifik
     */
    public function edit(Request $request, User $user)
    {
        if (!$request->user()->isSuperadmin()) {
            abort(403, 'Akses ditolak. Pengaturan hak akses hanya dapat dikelola oleh Super Administrator.');
        }

        $moduls = MasterModul::all();
        return view('hak-akses.edit', compact('user', 'moduls'));
    }

    /**
     * Menyimpan perubahan hak akses ke database (Tabel Pivot akses_users)
     */
    public function update(Request $request, User $user)
    {
        if (!$request->user()->isSuperadmin()) {
            abort(403, 'Akses ditolak. Pengaturan hak akses hanya dapat dikelola oleh Super Administrator.');
        }

        // Validasi input array dari checkbox
        $request->validate([
            'moduls' => 'nullable|array',
            'moduls.*' => 'exists:master_moduls,id',
        ]);

        // FUNGSI AJAIB LARAVEL: sync()
        // Otomatis menghapus akses lama dan menggantinya dengan array akses yang baru dicentang
        // Jika $request->moduls kosong (tidak ada yang dicentang), maka semua akses dicabut
        $user->moduls()->sync($request->moduls ?? []);

        return redirect('/hak-akses')->with('success', 'Hak akses untuk pegawai ' . $user->name . ' berhasil diperbarui.');
    }
}