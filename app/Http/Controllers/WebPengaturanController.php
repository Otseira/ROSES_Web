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
            'nama_instansi'   => 'required|string|max:255',
            'tagline'         => 'nullable|string|max:120',
            'latitude'        => 'required|string',
            'longitude'       => 'required|string',
            'radius_meter'    => 'required|integer|min:10',
            'logo'            => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'splash_bg'       => 'nullable|image|mimes:jpeg,png,jpg|max:3072',
            'splash_bg_color' => 'nullable|string|max:9',
        ]);

        $pengaturan = PengaturanAplikasi::first();

        // Logo aplikasi (kolom lama)
        if ($request->hasFile('logo')) {
            if ($pengaturan->logo && Storage::exists('public/' . $pengaturan->logo)) {
                Storage::delete('public/' . $pengaturan->logo);
            }
            $pengaturan->logo = $request->file('logo')->store('logos', 'public');
        }

        // ✅ Background splash (kolom baru)
        if ($request->hasFile('splash_bg')) {
            if ($pengaturan->splash_bg && Storage::exists('public/' . $pengaturan->splash_bg)) {
                Storage::delete('public/' . $pengaturan->splash_bg);
            }
            $pengaturan->splash_bg = $request->file('splash_bg')->store('logos', 'public');
        }

        // ✅ Assignment langsung (aman tanpa tergantung $fillable model)
        $pengaturan->nama_instansi   = $request->nama_instansi;
        $pengaturan->tagline         = $request->tagline;
        $pengaturan->latitude        = $request->latitude;
        $pengaturan->longitude       = $request->longitude;
        $pengaturan->radius_meter    = $request->radius_meter;
        $pengaturan->splash_bg_color = $request->splash_bg_color;
        $pengaturan->save();

        return redirect('/pengaturan')->with('success', 'Pengaturan global sistem berhasil diperbarui.');
    }
}
