<?php

namespace App\Http\Controllers;

use App\Models\PengaturanAplikasi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class WebPengaturanController extends Controller
{
    public function index(Request $request)
    {
        if (!$request->user()->isSuperadmin()) {
            abort(403, 'Akses ditolak. Pengaturan sistem hanya dapat diakses oleh Super Administrator.');
        }

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
        if (!$request->user()->isSuperadmin()) {
            abort(403, 'Akses ditolak. Pengaturan sistem hanya dapat diubah oleh Super Administrator.');
        }

        $request->validate([
            'nama_instansi'   => 'required|string|max:255',
            'tagline'         => 'nullable|string|max:120',
            'latitude'        => 'required|numeric|between:-90,90',
            'longitude'       => 'required|numeric|between:-180,180',
            'radius_meter'    => 'required|integer|min:10|max:50000',
            'logo'            => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'splash_bg'       => 'nullable|image|mimes:jpeg,png,jpg,webp|max:3072',
            'splash_bg_color' => 'nullable|string|max:9|regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3}|[A-Fa-f0-9]{8})$/',
        ]);

        $pengaturan = PengaturanAplikasi::first();
        if (!$pengaturan) {
            $pengaturan = new PengaturanAplikasi();
        }

        // Logo aplikasi (kolom lama)
        if ($request->hasFile('logo')) {
            if ($pengaturan->logo && Storage::disk('public')->exists($pengaturan->logo)) {
                Storage::disk('public')->delete($pengaturan->logo);
            }
            $pengaturan->logo = $request->file('logo')->store('logos', 'public');
        }

        // ✅ Background splash (kolom baru)
        if ($request->hasFile('splash_bg')) {
            if ($pengaturan->splash_bg && Storage::disk('public')->exists($pengaturan->splash_bg)) {
                Storage::disk('public')->delete($pengaturan->splash_bg);
            }
            $pengaturan->splash_bg = $request->file('splash_bg')->store('logos', 'public');
        }

        // ✅ Assignment langsung (aman tanpa tergantung $fillable model)
        $pengaturan->nama_instansi   = $request->nama_instansi;
        $pengaturan->tagline         = $request->tagline;
        $pengaturan->latitude        = (string) $request->latitude;
        $pengaturan->longitude       = (string) $request->longitude;
        $pengaturan->radius_meter    = (int) $request->radius_meter;
        $pengaturan->splash_bg_color = $request->splash_bg_color;
        $pengaturan->save();

        // Invalidate global settings cache
        Cache::forget('pengaturan_aplikasi_global');

        return redirect('/pengaturan')->with('success', 'Pengaturan global sistem berhasil diperbarui.');
    }
}
