<?php

namespace App\Http\Controllers;

use App\Models\PengaturanAplikasi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class WebPengaturanController extends Controller
{
    public function index()
    {
        // Ambil data pengaturan pertama, jika belum ada di database, buat otomatis
        $pengaturan = PengaturanAplikasi::firstOrCreate(
            ['id' => 1],
            [
                'nama_instansi' => 'RSKB Ropanasuri',
                'latitude' => '-0.9471',
                'longitude' => '100.3511',
                'radius_meter' => 50
            ]
        );

        return view('pengaturan.index', compact('pengaturan'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'nama_instansi' => 'required|string|max:255',
            'latitude' => 'required|string',
            'longitude' => 'required|string',
            'radius_meter' => 'required|integer|min:10',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048', // Maksimal 2MB
        ]);

        $pengaturan = PengaturanAplikasi::first();

        // Proses unggah Logo jika ada file yang dikirim
        if ($request->hasFile('logo')) {
            // Hapus logo lama jika ada
            if ($pengaturan->logo && Storage::exists('public/' . $pengaturan->logo)) {
                Storage::delete('public/' . $pengaturan->logo);
            }
            
            // Simpan logo baru ke storage/app/public/logos
            $logoPath = $request->file('logo')->store('logos', 'public');
            $pengaturan->logo = $logoPath;
        }

        $pengaturan->update([
            'nama_instansi' => $request->nama_instansi,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'radius_meter' => $request->radius_meter,
        ]);

        return redirect('/pengaturan')->with('success', 'Pengaturan global sistem berhasil diperbarui.');
    }
}