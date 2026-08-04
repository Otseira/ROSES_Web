<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PengaturanAplikasi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PengaturanController extends Controller
{
    /**
     * Branding publik: logo + nama instansi untuk halaman login.
     * GET /api/branding  — TIDAK butuh token.
     * ⚠️ JANGAN sertakan latitude/longitude/radius (rahasia keamanan geofencing).
     */
    public function branding()
    {
        $p = PengaturanAplikasi::first();
        $base = rtrim((string) config('app.url'), '/');

        $logoUrl = null;
        if ($p?->logo) {
            // Tambahkan cache-buster berdasarkan waktu update record
            // agar logo selalu ter-refresh saat diupload ulang
            $cacheBuster = $p->updated_at ? $p->updated_at->timestamp : time();
            $logoUrl = $base . '/storage/' . $p->logo . '?v=' . $cacheBuster;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'nama_instansi' => $p?->nama_instansi,
                'tagline'       => $p?->tagline,
                'logo_url'      => $logoUrl,
            ],
        ]);
    }
}
