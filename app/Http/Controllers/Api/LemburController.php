<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LogLembur;
use App\Models\LogAbsensi;
use App\Models\JadwalRoster;
use App\Models\PengaturanAplikasi;
use Carbon\Carbon;
use Illuminate\Http\Request;

class LemburController extends Controller
{
    /**
     * 1. Lembur Ekstensi Shift — dengan verifikasi GPS + foto
     */
    public function storeEkstensi(Request $request)
    {
        $request->validate([
            'keterangan' => 'required|string|max:255',
            'latitude'   => 'required|numeric',
            'longitude'  => 'required|numeric',
            'foto_masuk' => 'required|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $user = $request->user()->load('unitKerja');
        $now  = Carbon::now();

        // Cegah duplikasi ekstensi hari ini
        $cekEkstensi = LogLembur::where('user_id', $user->id)
            ->where('jenis_lembur', 'Ekstensi Shift')
            ->whereDate('waktu_mulai_lembur', Carbon::today())
            ->exists();
        if ($cekEkstensi) {
            return response()->json(['success' => false, 'message' => 'Anda sudah mengajukan lembur ekstensi untuk hari ini.'], 422);
        }

        // Harus ada jadwal & sudah absen pulang
        $roster = JadwalRoster::with('shift')
            ->where('user_id', $user->id)
            ->where('tanggal_dinas', Carbon::today()->toDateString())
            ->first();
        if (!$roster) {
            return response()->json(['success' => false, 'message' => 'Jadwal dinas tidak ditemukan untuk kalkulasi lembur.'], 422);
        }
        $logAbsen = LogAbsensi::where('roster_id', $roster->id)->first();
        if (!$logAbsen || $logAbsen->waktu_pulang === null) {
            return response()->json(['success' => false, 'message' => 'Anda harus absen pulang terlebih dahulu sebelum mengajukan lembur ekstensi.'], 422);
        }

        $shift = $roster->shift;
        $jamPulangShift = Carbon::parse($roster->tanggal_dinas . ' ' . $shift->jam_pulang);
        if (Carbon::parse($shift->jam_pulang)->lessThan(Carbon::parse($shift->jam_masuk))) {
            $jamPulangShift->addDay();
        }
        if ($now->lessThan($jamPulangShift)) {
            return response()->json(['success' => false, 'message' => 'Lembur ekstensi hanya bisa diajukan setelah jam pulang shift (' . $jamPulangShift->format('H:i') . ').'], 422);
        }

        // ✅ VERIFIKASI GPS (radius)
        $cekRadius = $this->verifikasiRadius($request);
        if ($cekRadius !== true) return $cekRadius;

        // ✅ SIMPAN FOTO
        $file = $request->file('foto_masuk');
        $path = $file->storeAs('lembur_masuk', 'lembur_' . ($user->nik ?? $user->id) . '_' . time() . '.' . $file->extension(), 'public');

        $totalJamBulat = round($jamPulangShift->diffInMinutes($now) / 60, 2);

        LogLembur::create([
            'user_id' => $user->id,
            'jenis_lembur' => 'Ekstensi Shift',
            'waktu_mulai_lembur' => $jamPulangShift,
            'waktu_selesai_lembur' => $now,
            'total_jam_lembur' => $totalJamBulat,
            'status_validasi' => 'Pending',
            'keterangan' => $request->keterangan,
            'latitude_masuk' => $request->latitude,
            'longitude_masuk' => $request->longitude,
            'foto_masuk' => $path,
        ]);

        $pesanWa = "Lapor Kehadiran,\n\nSaya *" . $user->nama . "* dari unit *" . ($user->unitKerja->nama_unit ?? '-') . "* mengajukan *Lembur Ekstensi Shift* pada " . Carbon::today()->translatedFormat('d F Y') . ".\n- Mulai: " . $jamPulangShift->format('H:i') . " WIB\n- Selesai: " . $now->format('H:i') . " WIB\n- Durasi: " . $totalJamBulat . " Jam\n- Keterangan: " . $request->keterangan . "\n\nMohon validasi pada sistem. Terima kasih.";

        return response()->json(['success' => true, 'message' => 'Data lembur ekstensi berhasil disimpan.', 'data' => ['total_jam' => $totalJamBulat, 'text_whatsapp' => $pesanWa]], 200);
    }

    /**
     * 2. On-Call Masuk — dengan verifikasi GPS + foto
     */
    public function clockInOnCall(Request $request)
    {
        $request->validate([
            'keterangan' => 'required|string|max:255',
            'latitude'   => 'required|numeric',
            'longitude'  => 'required|numeric',
            'foto_masuk' => 'required|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $user = $request->user()->load('unitKerja');
        $now  = Carbon::now();

        $cekOnCall = LogLembur::where('user_id', $user->id)
            ->where('jenis_lembur', 'On-Call')
            ->whereNull('waktu_selesai_lembur')
            ->first();
        if ($cekOnCall) {
            return response()->json(['success' => false, 'message' => 'Anda masih memiliki sesi On-Call yang belum diselesaikan.'], 422);
        }

        // ✅ VERIFIKASI GPS (radius)
        $cekRadius = $this->verifikasiRadius($request);
        if ($cekRadius !== true) return $cekRadius;

        // ✅ SIMPAN FOTO
        $file = $request->file('foto_masuk');
        $path = $file->storeAs('oncall_masuk', 'oncall_masuk_' . ($user->nik ?? $user->id) . '_' . time() . '.' . $file->extension(), 'public');

        LogLembur::create([
            'user_id' => $user->id,
            'jenis_lembur' => 'On-Call',
            'waktu_mulai_lembur' => $now,
            'status_validasi' => 'Pending',
            'keterangan' => $request->keterangan,
            'latitude_masuk' => $request->latitude,
            'longitude_masuk' => $request->longitude,
            'foto_masuk' => $path,
        ]);

        $pesanWa = "Lapor Kehadiran,\n\nSaya *" . $user->nama . "* dari unit *" . ($user->unitKerja->nama_unit ?? '-') . "* masuk tugas *On-Call* pada jam " . $now->format('H:i') . " WIB.\nKasus: " . $request->keterangan . "\n\nTerima kasih.";

        return response()->json(['success' => true, 'message' => 'Sesi On-Call berhasil dimulai.', 'data' => ['waktu_mulai' => $now->toDateTimeString(), 'text_whatsapp' => $pesanWa]], 200);
    }

    /**
     * 3. On-Call Keluar — dengan verifikasi GPS + foto
     */
    public function clockOutOnCall(Request $request)
    {
        $request->validate([
            'latitude'    => 'required|numeric',
            'longitude'   => 'required|numeric',
            'foto_keluar' => 'required|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $user = $request->user()->load('unitKerja');
        $now  = Carbon::now();

        $lembur = LogLembur::where('user_id', $user->id)
            ->where('jenis_lembur', 'On-Call')
            ->whereNull('waktu_selesai_lembur')
            ->latest()
            ->first();
        if (!$lembur) {
            return response()->json(['success' => false, 'message' => 'Sesi On-Call aktif tidak ditemukan. Silakan lakukan masuk On-Call terlebih dahulu.'], 422);
        }

        // ✅ VERIFIKASI GPS (radius)
        $cekRadius = $this->verifikasiRadius($request);
        if ($cekRadius !== true) return $cekRadius;

        // ✅ SIMPAN FOTO
        $file = $request->file('foto_keluar');
        $path = $file->storeAs('oncall_keluar', 'oncall_keluar_' . ($user->nik ?? $user->id) . '_' . time() . '.' . $file->extension(), 'public');

        $waktuMulai = Carbon::parse($lembur->waktu_mulai_lembur);
        $totalJamBulat = round($waktuMulai->diffInMinutes($now) / 60, 2);

        $lembur->update([
            'waktu_selesai_lembur' => $now,
            'total_jam_lembur' => $totalJamBulat,
            'latitude_keluar' => $request->latitude,
            'longitude_keluar' => $request->longitude,
            'foto_keluar' => $path,
        ]);

        $pesanWa = "Lapor Kehadiran,\n\nSaya *" . $user->nama . "* dari unit *" . ($user->unitKerja->nama_unit ?? '-') . "* telah menyelesaikan *On-Call*.\n- Masuk: " . $waktuMulai->format('H:i') . " WIB\n- Keluar: " . $now->format('H:i') . " WIB\n- Durasi: " . $totalJamBulat . " Jam\n- Kasus: " . $lembur->keterangan . "\n\nMohon validasi pada sistem. Terima kasih.";

        return response()->json(['success' => true, 'message' => 'Sesi On-Call berhasil diselesaikan.', 'data' => ['waktu_selesai' => $now->toDateTimeString(), 'total_jam' => $totalJamBulat, 'text_whatsapp' => $pesanWa]], 200);
    }

    /**
     * ✅ Helper: verifikasi lokasi dalam radius rumah sakit.
     * Return `true` bila lolos, atau Response JSON bila ditolak.
     */
    private function verifikasiRadius(Request $request)
    {
        $pengaturan = PengaturanAplikasi::first();
        $hospitalLat = $pengaturan ? (float) $pengaturan->latitude  : -0.9471;
        $hospitalLng = $pengaturan ? (float) $pengaturan->longitude : 100.3511;
        $maxRadius   = $pengaturan ? (int) $pengaturan->radius_meter : 50;

        $distance = $this->calculateDistance($request->latitude, $request->longitude, $hospitalLat, $hospitalLng);
        if ($distance > $maxRadius) {
            return response()->json([
                'success' => false,
                'message' => 'Ditolak. Anda berada di luar radius rumah sakit (Jarak Anda: ' . round($distance) . ' meter).',
            ], 403);
        }
        return true;
    }

    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371000;
        $latDelta = deg2rad($lat2 - $lat1);
        $lonDelta = deg2rad($lon2 - $lon1);
        $a = sin($latDelta / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($lonDelta / 2) ** 2;
        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * 4. DAFTAR LEMBUR UNTUK VALIDASI (scoped by role/unit)
     *    GET /api/lembur/validasi?status=Pending
     */
    public function listValidasi(Request $request)
    {
        $user = $request->user();
        if (!in_array($user->role, ['kepala_unit', 'hrd', 'superadmin'])) {
            return response()->json(['success' => false, 'message' => 'Anda tidak memiliki akses validasi.'], 403);
        }

        $status = $request->query('status', 'Pending'); // Pending | Disetujui | Ditolak

        $query = LogLembur::with(['user.unitKerja'])
            ->where('status_validasi', $status)
            ->orderByDesc('waktu_mulai_lembur');

        // kepala_unit hanya melihat unitnya sendiri
        if ($user->role === 'kepala_unit') {
            $query->whereHas('user', function ($q) use ($user) {
                $q->where('unit_kerja_id', $user->unit_kerja_id);
            });
        }

        $fotoUrl = fn($p) => $p ? rtrim(config('app.url'), '/') . '/storage/' . $p : null;

        $data = $query->get()->map(function ($l) use ($fotoUrl) {
            return [
                'id'              => $l->id,
                'jenis_lembur'    => $l->jenis_lembur,
                'status_validasi' => $l->status_validasi,
                'pegawai_nama'    => $l->user->name ?? '-',
                'pegawai_nik'     => $l->user->nik ?? '-',
                'unit_nama'       => $l->user->unitKerja->nama_unit ?? '-',
                'waktu_mulai'     => $l->waktu_mulai_lembur,
                'waktu_selesai'   => $l->waktu_selesai_lembur,
                'total_jam'       => $l->total_jam_lembur,
                'keterangan'      => $l->keterangan,
                'foto_masuk_url'  => $fotoUrl($l->foto_masuk),
                'foto_keluar_url' => $fotoUrl($l->foto_keluar),
                'lat_masuk'       => $l->latitude_masuk,
                'lng_masuk'       => $l->longitude_masuk,
                'lat_keluar'      => $l->latitude_keluar,
                'lng_keluar'      => $l->longitude_keluar,
                'divalidasi_oleh' => $l->divalidasi_oleh,
                'divalidasi_pada' => $l->divalidasi_pada,
                'catatan_validasi' => $l->catatan_validasi,
            ];
        });

        return response()->json(['success' => true, 'data' => $data], 200);
    }

    /**
     * 5. SETUJUI / TOLAK LEMBUR
     *    POST /api/lembur/validasi/{id}   body: { action: 'Disetujui'|'Ditolak', catatan?: '...' }
     */
    public function prosesValidasi(Request $request, $id)
    {
        $request->validate([
            'action'  => 'required|in:Disetujui,Ditolak',
            'catatan' => 'nullable|string|max:255',
        ]);

        $user = $request->user();
        if (!in_array($user->role, ['kepala_unit', 'hrd', 'superadmin'])) {
            return response()->json(['success' => false, 'message' => 'Anda tidak memiliki akses validasi.'], 403);
        }

        $lembur = LogLembur::with('user')->findOrFail($id);

        // kepala_unit hanya boleh validasi unitnya sendiri
        if ($user->role === 'kepala_unit' && (int) $lembur->user->unit_kerja_id !== (int) $user->unit_kerja_id) {
            return response()->json(['success' => false, 'message' => 'Lembur ini bukan dari unit kerja Anda.'], 403);
        }

        if ($lembur->status_validasi !== 'Pending') {
            return response()->json(['success' => false, 'message' => 'Lembur ini sudah divalidasi sebelumnya (' . $lembur->status_validasi . ').'], 422);
        }

        $lembur->update([
            'status_validasi'  => $request->action,
            'divalidasi_oleh'  => $user->id,
            'divalidasi_pada'  => now(),
            'catatan_validasi' => $request->catatan,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Lembur berhasil ' . strtolower($request->action) . '.',
            'data' => ['id' => $lembur->id, 'status_validasi' => $lembur->status_validasi],
        ], 200);
    }
}
