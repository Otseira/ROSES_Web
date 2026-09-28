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
        $p    = PengaturanAplikasi::first();
        $base = rtrim((string) config('app.url'), '/');

        // Cache-buster: logo & background selalu fresh setelah diupload ulang
        $v = $p?->updated_at ? $p->updated_at->timestamp : time();

        return response()->json([
            'success' => true,
            'data'    => [
                'nama_instansi'   => $p?->nama_instansi,
                'tagline'         => $p?->tagline,
                'logo_url'        => $p?->logo ? $base . '/storage/' . $p->logo . '?v=' . $v : null,
                'splash_bg_url'   => $p?->splash_bg ? $base . '/storage/' . $p->splash_bg . '?v=' . $v : null,
                'splash_bg_color' => $p?->splash_bg_color ?: null,
                // ⚠️ latitude/longitude/radius TIDAK disertakan (rahasia geofencing)
            ],
        ]);
    }
}
