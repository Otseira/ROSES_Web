<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LogLembur;
use App\Models\JadwalRoster;
use Carbon\Carbon;
use Illuminate\Http\Request;

class LemburController extends Controller
{
    /**
     * 1. Mencatat Lembur Ekstensi Shift (Lanjutan dari Shift Reguler)
     */
    public function storeEkstensi(Request $request)
    {
        $request->validate([
            'keterangan' => 'required|string|max:255',
        ]);

        $user = $request->user()->load('unitKerja');
        $now = Carbon::now();

        // Cari jadwal dinas hari ini untuk menentukan waktu selesai shift asli
        $roster = JadwalRoster::with('shift')
            ->where('user_id', $user->id)
            ->where('tanggal_dinas', Carbon::today()->toDateString())
            ->first();

        if (!$roster) {
            return response()->json([
                'success' => false,
                'message' => 'Jadwal dinas tidak ditemukan untuk kalkulasi lembur.',
            ], 422);
        }

        $shift = $roster->shift;
        $jamPulangShift = Carbon::parse($roster->tanggal_dinas . ' ' . $shift->jam_pulang);
        
        // Antisipasi jika shift malam (lintas hari)
        if (Carbon::parse($shift->jam_pulang)->lessThan(Carbon::parse($shift->jam_masuk))) {
            $jamPulangShift->addDay();
        }

        // Hitung total jam lembur (selisih dari jam pulang shift hingga sekarang)
        $totalJam = $now->diffInMinutes($jamPulangShift) / 60;
        $totalJamBulat = round($totalJam, 2); // Pembulatan 2 angka di belakang koma

        // Simpan ke database
        $lembur = LogLembur::create([
            'user_id' => $user->id,
            'jenis_lembur' => 'Ekstensi Shift',
            'waktu_mulai_lembur' => $jamPulangShift,
            'waktu_selesai_lembur' => $now,
            'total_jam_lembur' => $totalJamBulat,
            'status_validasi' => 'Pending',
            'keterangan' => $request->keterangan,
        ]);

        // Ambil nomor WA atasan dari logika internal atau profil unit (jika ada)
        // Untuk contoh ini, kita sediakan template teks laporan WA
        $pesanWa = "Lapor Kehadiran,\n\nSaya *" . $user->name . "* dari unit *" . ($user->unitKerja->nama_unit ?? '-') . "* telah mengajukan *Lembur Ekstensi Shift* pada tanggal " . Carbon::today()->translatedFormat('d F Y') . ".\n\nDetail:\n- Mulai: " . $jamPulangShift->format('H:i') . " WIB\n- Selesai: " . $now->format('H:i') . " WIB\n- Durasi: " . $totalJamBulat . " Jam\n- Keterangan: " . $request->keterangan . "\n\nMohon untuk dilakukan validasi pada sistem. Terima kasih.";

        return response()->json([
            'success' => true,
            'message' => 'Data lembur ekstensi berhasil disimpan.',
            'data' => [
                'total_jam' => $totalJamBulat,
                'text_whatsapp' => $pesanWa,
            ]
        ], 200);
    }

    /**
     * 2. Clock-In Khusus Karyawan On-Call (Panggilan Darurat)
     */
    public function clockInOnCall(Request $request)
    {
        $request->validate([
            'keterangan' => 'required|string|max:255',
        ]);

        $user = $request->user()->load('unitKerja');
        $now = Carbon::now();

        // Cek apakah ada lembur On-Call yang statusnya masih berjalan (belum clock-out)
        $cekOnCall = LogLembur::where('user_id', $user->id)
            ->where('jenis_lembur', 'On-Call')
            ->whereNull('waktu_selesai_lembur')
            ->first();

        if ($cekOnCall) {
            return response()->json([
                'success' => false,
                'message' => 'Anda masih memiliki sesi On-Call yang belum diselesaikan.',
            ], 422);
        }

        // Simpan log lembur masuk
        LogLembur::create([
            'user_id' => $user->id,
            'jenis_lembur' => 'On-Call',
            'waktu_mulai_lembur' => $now,
            'status_validasi' => 'Pending',
            'keterangan' => $request->keterangan,
        ]);

        $pesanWa = "Lapor Kehadiran,\n\nSaya *" . $user->name . "* dari unit *" . ($user->unitKerja->nama_unit ?? '-') . "* baru saja masuk tugas *On-Call (Panggilan Darurat)* pada jam " . $now->format('H:i') . " WIB.\n\nAlasan/Kasus: " . $request->keterangan . "\n\nTerima kasih.";

        return response()->json([
            'success' => true,
            'message' => 'Sesi On-Call berhasil dimulai.',
            'data' => [
                'waktu_mulai' => $now->toDateTimeString(),
                'text_whatsapp' => $pesanWa,
            ]
        ], 200);
    }

    /**
     * 3. Clock-Out Khusus Karyawan On-Call
     */
    public function clockOutOnCall(Request $request)
    {
        $user = $request->user()->load('unitKerja');
        $now = Carbon::now();

        // Cari sesi On-Call yang sedang berjalan
        $lembur = LogLembur::where('user_id', $user->id)
            ->where('jenis_lembur', 'On-Call')
            ->whereNull('waktu_selesai_lembur')
            ->latest()
            ->first();

        if (!$lembur) {
            return response()->json([
                'success' => false,
                'message' => 'Sesi On-Call aktif tidak ditemukan. Silakan lakukan masuk On-Call terlebih dahulu.',
            ], 422);
        }

        // Hitung durasi kerja On-Call
        $waktuMulai = Carbon::parse($lembur->waktu_mulai_lembur);
        $totalJam = $now->diffInMinutes($waktuMulai) / 60;
        $totalJamBulat = round($totalJam, 2);

        // Update data lembur selesai
        $lembur->update([
            'waktu_selesai_lembur' => $now,
            'total_jam_lembur' => $totalJamBulat,
        ]);

        $pesanWa = "Lapor Kehadiran,\n\nSaya *" . $user->name . "* dari unit *" . ($user->unitKerja->nama_unit ?? '-') . "* telah menyelesaikan tugas *On-Call (Panggilan Darurat)*.\n\nDetail Sesi:\n- Masuk: " . $waktuMulai->format('H:i') . " WIB\n- Keluar: " . $now->format('H:i') . " WIB\n- Total Durasi: " . $totalJamBulat . " Jam\n- Kasus: " . $lembur->keterangan . "\n\nMohon untuk dilakukan validasi pada sistem. Terima kasih.";

        return response()->json([
            'success' => true,
            'message' => 'Sesi On-Call berhasil diselesaikan.',
            'data' => [
                'waktu_selesai' => $now->toDateTimeString(),
                'total_jam' => $totalJamBulat,
                'text_whatsapp' => $pesanWa,
            ]
        ], 200);
    }
}