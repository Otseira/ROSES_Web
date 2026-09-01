<?php

namespace App\Http\Controllers;

use App\Models\MasterShift;
use App\Models\MasterUnitKerja;
use Illuminate\Http\Request;

class WebShiftController extends Controller
{
    /**
     * Menampilkan daftar master shift (Read)
     */
    public function index()
    {
        // ✅ Muat relasi unit & kelompokkan tampilan per unit
        $shifts = MasterShift::with('unitKerja')
            ->orderBy('unit_kerja_id')
            ->orderBy('jam_masuk')
            ->get();

        return view('shift.index', compact('shifts'));
    }

    /**
     * Menampilkan formulir tambah shift (Create)
     */
    public function create()
    {
        // ✅ BARU: kirim daftar unit ke form
        $units = MasterUnitKerja::orderBy('nama_unit', 'asc')->get();

        return view('shift.create', compact('units'));
    }


    /**
     * Menyimpan data shift baru (Store)
     */
    public function store(Request $request)
    {
        $request->validate([
            'nama_shift'  => 'required|string|max:100',
            'jam_masuk'   => 'required',
            'jam_pulang'  => 'required',
            'toleransi_terlambat_menit' => 'nullable|integer|min:0',
            'unit_kerja_id' => 'nullable|exists:master_unit_kerjas,id', // ✅ BARU
        ]);

        MasterShift::create([
            'nama_shift'  => $request->nama_shift,
            'jam_masuk'   => $request->jam_masuk,
            'jam_pulang'  => $request->jam_pulang,
            'toleransi_terlambat_menit' => $request->toleransi_terlambat_menit ?? 5,
            'unit_kerja_id' => $request->unit_kerja_id ?: null, // ✅ BARU
        ]);

        return redirect('/shift')->with('success', 'Shift baru berhasil ditambahkan.');
    }

    /**
     * Menampilkan formulir ubah shift (Edit)
     */
    public function edit($id)
    {
        $shift = MasterShift::findOrFail($id);
        $units = MasterUnitKerja::orderBy('nama_unit', 'asc')->get(); // ✅ BARU

        return view('shift.edit', compact('shift', 'units'));
    }

    /**
     * Memperbarui data shift (Update)
     */
    public function update(Request $request, $id)
    {
        $shift = MasterShift::findOrFail($id);

        $request->validate([
            'nama_shift'  => 'required|string|max:100',
            'jam_masuk'   => 'required',
            'jam_pulang'  => 'required',
            'toleransi_terlambat_menit' => 'nullable|integer|min:0',
            'unit_kerja_id' => 'nullable|exists:master_unit_kerjas,id',
        ]);

        $shift->update([
            'nama_shift'  => $request->nama_shift,
            'jam_masuk'   => $request->jam_masuk,
            'jam_pulang'  => $request->jam_pulang,
            'toleransi_terlambat_menit' => $request->toleransi_terlambat_menit ?? 5,
            'unit_kerja_id' => $request->unit_kerja_id ?: null, // ✅ BARU
        ]);

        return redirect('/shift')->with('success', 'Shift berhasil diperbarui.');
    }

    /**
     * Menghapus data shift (Delete)
     */
    public function destroy(MasterShift $master_shift)
    {
        // Cegah penghapusan jika shift ini sudah dipakai di jadwal roster
        if ($master_shift->rosters()->exists()) {
            return redirect('/master-shift')->withErrors(['Gagal menghapus! Shift ini sedang digunakan oleh jadwal pegawai.']);
        }

        $master_shift->delete();
        return redirect('/master-shift')->with('success', 'Data Master Shift berhasil dihapus.');
    }
}
