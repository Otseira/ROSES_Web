<?php

namespace App\Http\Controllers;

use App\Models\MasterShift;
use Illuminate\Http\Request;

class WebShiftController extends Controller
{
    /**
     * Menampilkan daftar master shift (Read)
     */
    public function index()
    {
        $shifts = MasterShift::orderBy('jam_masuk')->get();
        return view('shift.index', compact('shifts'));
    }

    /**
     * Menampilkan formulir tambah shift (Create)
     */
    public function create()
    {
        return view('shift.create');
    }

    /**
     * Menyimpan data shift baru (Store)
     */
    public function store(Request $request)
    {
        $request->validate([
            'nama_shift' => 'required|string|max:50',
            'jam_masuk' => 'required|date_format:H:i',
            'jam_pulang' => 'required|date_format:H:i',
            'toleransi_terlambat_menit' => 'required|integer|min:0',
        ]);

        MasterShift::create($request->all());

        return redirect('/master-shift')->with('success', 'Data Master Shift baru berhasil ditambahkan.');
    }

    /**
     * Menampilkan formulir ubah shift (Edit)
     */
    public function edit(MasterShift $master_shift)
    {
        return view('shift.edit', compact('master_shift'));
    }

    /**
     * Memperbarui data shift (Update)
     */
    public function update(Request $request, MasterShift $master_shift)
    {
        $request->validate([
            'nama_shift' => 'required|string|max:50',
            'jam_masuk' => 'required|date_format:H:i', // Format jam:menit
            'jam_pulang' => 'required|date_format:H:i',
            'toleransi_terlambat_menit' => 'required|integer|min:0',
        ]);

        $master_shift->update($request->all());

        return redirect('/master-shift')->with('success', 'Data Master Shift berhasil diperbarui.');
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