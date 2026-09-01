<?php

namespace App\Http\Controllers;

use App\Models\MasterShift;
use App\Models\MasterUnitKerja;
use Illuminate\Http\Request;

class WebShiftController extends Controller
{
    /** Daftar master shift + unit terkait */
    public function index()
    {
        $shifts = MasterShift::with('unitKerja')
            ->orderBy('unit_kerja_id')
            ->orderBy('jam_masuk')
            ->get();

        return view('shift.index', compact('shifts'));
    }

    /** Form tambah shift */
    public function create()
    {
        $units = MasterUnitKerja::orderBy('nama_unit', 'asc')->get();

        return view('shift.create', compact('units'));
    }

    /** Simpan shift baru */
    public function store(Request $request)
    {
        $request->validate([
            'nama_shift'  => 'required|string|max:100',
            'jam_masuk'   => 'required',
            'jam_pulang'  => 'required',
            'toleransi_terlambat_menit' => 'nullable|integer|min:0',
            'unit_kerja_id' => 'nullable|exists:master_unit_kerjas,id',
        ]);

        MasterShift::create([
            'nama_shift'  => $request->nama_shift,
            'jam_masuk'   => $request->jam_masuk,
            'jam_pulang'  => $request->jam_pulang,
            'toleransi_terlambat_menit' => $request->toleransi_terlambat_menit ?? 5,
            'unit_kerja_id' => $request->unit_kerja_id ?: null,
        ]);

        return redirect()->route('master-shift.index')->with('success', 'Shift baru berhasil ditambahkan.');
    }

    /** Form edit shift */
    public function edit($id)
    {
        $shift = MasterShift::findOrFail($id);
        $units = MasterUnitKerja::orderBy('nama_unit', 'asc')->get();

        return view('shift.edit', compact('shift', 'units'));
    }

    /** Update shift */
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
            'unit_kerja_id' => $request->unit_kerja_id ?: null,
        ]);

        return redirect()->route('master-shift.index')->with('success', 'Shift berhasil diperbarui.');
    }

    /** Hapus shift (dicegah jika masih dipakai roster) */
    public function destroy($id)
    {
        $shift = MasterShift::findOrFail($id);

        if ($shift->rosters()->exists()) {
            return redirect()->route('master-shift.index')
                ->withErrors(['Gagal menghapus! Shift ini sedang digunakan oleh jadwal pegawai.']);
        }

        $shift->delete();
        return redirect()->route('master-shift.index')->with('success', 'Data Master Shift berhasil dihapus.');
    }
}
