<?php

namespace App\Http\Controllers;

use App\Models\MasterShift;
use App\Models\MasterUnitKerja;
use Illuminate\Http\Request;

class WebShiftController extends Controller
{
    private function authorizeShiftManager(Request $request): void
    {
        if (!$request->user()->canManageUsers()) {
            abort(403, 'Akses ditolak. Anda tidak memiliki izin untuk mengelola master shift.');
        }
    }

    /** Daftar master shift + unit terkait */
    public function index(Request $request)
    {
        $userLogin = $request->user();
        $query = MasterShift::with('unitKerja')
            ->orderBy('unit_kerja_id')
            ->orderBy('jam_masuk');

        if (!$userLogin->hasGlobalAccess()) {
            $managedUnits = $userLogin->managesUnits()->pluck('master_unit_kerja_id')->toArray();
            if ($userLogin->unit_kerja_id && !in_array($userLogin->unit_kerja_id, $managedUnits)) {
                $managedUnits[] = $userLogin->unit_kerja_id;
            }
            $query->where(function ($q) use ($managedUnits) {
                $q->whereNull('unit_kerja_id')
                  ->orWhereIn('unit_kerja_id', $managedUnits);
            });
        }

        $shifts = $query->get();

        return view('shift.index', compact('shifts'));
    }

    /** Form tambah shift */
    public function create(Request $request)
    {
        $this->authorizeShiftManager($request);
        $units = MasterUnitKerja::orderBy('nama_unit', 'asc')->get();

        return view('shift.create', compact('units'));
    }

    /** Simpan shift baru */
    public function store(Request $request)
    {
        $this->authorizeShiftManager($request);

        $request->validate([
            'nama_shift'  => 'required|string|max:100',
            'jam_masuk'   => 'required|string|regex:/^\d{2}:\d{2}(:\d{2})?$/',
            'jam_pulang'  => 'required|string|regex:/^\d{2}:\d{2}(:\d{2})?$/',
            'toleransi_terlambat_menit' => 'nullable|integer|min:0|max:120',
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
    public function edit(Request $request, $id)
    {
        $this->authorizeShiftManager($request);
        $shift = MasterShift::findOrFail($id);
        $units = MasterUnitKerja::orderBy('nama_unit', 'asc')->get();

        return view('shift.edit', compact('shift', 'units'));
    }

    /** Update shift */
    public function update(Request $request, $id)
    {
        $this->authorizeShiftManager($request);
        $shift = MasterShift::findOrFail($id);

        $request->validate([
            'nama_shift'  => 'required|string|max:100',
            'jam_masuk'   => 'required|string|regex:/^\d{2}:\d{2}(:\d{2})?$/',
            'jam_pulang'  => 'required|string|regex:/^\d{2}:\d{2}(:\d{2})?$/',
            'toleransi_terlambat_menit' => 'nullable|integer|min:0|max:120',
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
    public function destroy(Request $request, $id)
    {
        $this->authorizeShiftManager($request);
        $shift = MasterShift::findOrFail($id);

        if ($shift->rosters()->exists()) {
            return redirect()->route('master-shift.index')
                ->withErrors(['Gagal menghapus! Shift ini sedang digunakan oleh jadwal pegawai.']);
        }

        $shift->delete();
        return redirect()->route('master-shift.index')->with('success', 'Data Master Shift berhasil dihapus.');
    }
}
