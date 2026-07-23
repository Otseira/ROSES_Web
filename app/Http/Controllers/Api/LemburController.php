<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LogLembur;
use App\Models\LogAbsensi;
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

        // ✅ FIX #1: $user didefinisikan SEBELUM digunakan
        $user = $request->user()->load('unitKerja');
        $now = Carbon::now();

        // ✅ FIX #2: Cek duplikasi lembur ekstensi hari ini (dipindah setelah $user didefinisikan)
        $cekEkstensi = LogLembur::where('user_id', $user->id)
            ->where('jenis_lembur', 'Ekstensi Shift')
            ->whereDate('waktu_mulai_lembur', Carbon::today())
            ->exists();

        if ($cekEkstensi) {
            return response()->json([
                'success' => false,
                'message' => 'Anda sudah mengajukan lembur ekstensi untuk hari ini.',
            ], 422);
        }

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

        // ✅ FIX #3: Cek apakah user sudah absen pulang (clock-out) terlebih dahulu
        $logAbsen = LogAbsensi::where('roster_id', $roster->id)->first();

        if (!$logAbsen || $logAbsen->waktu_pulang === null) {
            return response()->json([
                'success' => false,
                'message' => 'Anda harus melakukan absen pulang terlebih dahulu sebelum mengajukan lembur ekstensi.',
            ], 422);
        }

        $shift = $roster->shift;
        $jamPulangShift = Carbon::parse($roster->tanggal_dinas . ' ' . $shift->jam_pulang);

        // Antisipasi jika shift malam (lintas hari)
        if (Carbon::parse($shift->jam_pulang)->lessThan(Carbon::parse($shift->jam_masuk))) {
            $jamPulangShift->addDay();
        }

        // ✅ FIX #4: Validasi bahwa waktu sekarang sudah melewati jam pulang shift
        if ($now->lessThan($jamPulangShift)) {
            return response()->json([
                'success' => false,
                'message' => 'Lembur ekstensi hanya bisa diajukan setelah jam pulang shift (' . $jamPulangShift->format('H:i') . ' WIB).',
            ], 422);
        }

        // ✅ FIX #5: Urutan diffInMinutes yang benar (dari waktu awal ke waktu akhir)
        // Carbon 3.x: $start->diffInMinutes($end) menghasilkan nilai positif
        $totalJam = $jamPulangShift->diffInMinutes($now) / 60;
        $totalJamBulat = round($totalJam, 2);

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

        // ✅ FIX #6: $user->name → $user->nama (sesuai kolom di tabel users)
        $pesanWa = "Lapor Kehadiran,\n\n"
            . "Saya *" . $user->nama . "* dari unit *"
            . ($user->unitKerja->nama_unit ?? '-') . "* telah mengajukan "
            . "*Lembur Ekstensi Shift* pada tanggal "
            . Carbon::today()->translatedFormat('d F Y') . ".\n\n"
            . "Detail:\n"
            . "- Mulai: " . $jamPulangShift->format('H:i') . " WIB\n"
            . "- Selesai: " . $now->format('H:i') . " WIB\n"
            . "- Durasi: " . $totalJamBulat . " Jam\n"
            . "- Keterangan: " . $request->keterangan . "\n\n"
            . "Mohon untuk dilakukan validasi pada sistem. Terima kasih.";

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

        // ✅ FIX #6: $user->name → $user->nama
        $pesanWa = "Lapor Kehadiran,\n\n"
            . "Saya *" . $user->nama . "* dari unit *"
            . ($user->unitKerja->nama_unit ?? '-') . "* baru saja masuk tugas "
            . "*On-Call (Panggilan Darurat)* pada jam "
            . $now->format('H:i') . " WIB.\n\n"
            . "Alasan/Kasus: " . $request->keterangan . "\n\n"
            . "Terima kasih.";

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

        // ✅ FIX #5: Urutan diffInMinutes yang benar
        $waktuMulai = Carbon::parse($lembur->waktu_mulai_lembur);
        $totalJam = $waktuMulai->diffInMinutes($now) / 60;
        $totalJamBulat = round($totalJam, 2);

        // Update data lembur selesai
        $lembur->update([
            'waktu_selesai_lembur' => $now,
            'total_jam_lembur' => $totalJamBulat,
        ]);

        // ✅ FIX #6: $user->name → $user->nama
        $pesanWa = "Lapor Kehadiran,\n\n"
            . "Saya *" . $user->nama . "* dari unit *"
            . ($user->unitKerja->nama_unit ?? '-') . "* telah menyelesaikan tugas "
            . "*On-Call (Panggilan Darurat)*.\n\n"
            . "Detail Sesi:\n"
            . "- Masuk: " . $waktuMulai->format('H:i') . " WIB\n"
            . "- Keluar: " . $now->format('H:i') . " WIB\n"
            . "- Total Durasi: " . $totalJamBulat . " Jam\n"
            . "- Kasus: " . $lembur->keterangan . "\n\n"
            . "Mohon untuk dilakukan validasi pada sistem. Terima kasih.";

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
