<?php

namespace App\Http\Controllers;

use App\Models\MasterUnitKerja;
use Illuminate\Http\Request;

class WebUnitKerjaController extends Controller
{
    private function authorizeAdminOrHrd(Request $request): void
    {
        if (!in_array($request->user()->role, ['superadmin', 'hrd', 'direktur'])) {
            abort(403, 'Akses ditolak. Hanya SDM/HRD atau Administrator yang memiliki izin mengelola unit kerja.');
        }
    }

    /**
     * Tampilkan Daftar Unit Kerja
     */
    public function index(Request $request)
    {
        $this->authorizeAdminOrHrd($request);

        $query = MasterUnitKerja::withCount('users')->orderBy('nama_unit', 'asc');

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('nama_unit', 'like', "%{$search}%")
                  ->orWhere('deskripsi', 'like', "%{$search}%");
            });
        }

        $units = $query->paginate(15)->withQueryString();

        return view('unit.index', compact('units'));
    }

    /**
     * Form Tambah Unit
     */
    public function create(Request $request)
    {
        $this->authorizeAdminOrHrd($request);

        return view('unit.create');
    }

    /**
     * Simpan Unit Baru
     */
    public function store(Request $request)
    {
        $this->authorizeAdminOrHrd($request);

        $validated = $request->validate([
            'nama_unit' => 'required|string|max:100|unique:master_unit_kerjas,nama_unit',
            'deskripsi' => 'nullable|string|max:500',
        ], [
            'nama_unit.required' => 'Nama unit kerja wajib diisi.',
            'nama_unit.unique'   => 'Nama unit kerja sudah terdaftar.',
            'nama_unit.max'      => 'Nama unit kerja maksimal 100 karakter.',
        ]);

        MasterUnitKerja::create($validated);

        return redirect()->route('master-unit.index')
            ->with('success', "Unit kerja '{$validated['nama_unit']}' berhasil ditambahkan.");
    }

    /**
     * Form Edit Unit
     */
    public function edit(Request $request, $id)
    {
        $this->authorizeAdminOrHrd($request);

        $unit = MasterUnitKerja::findOrFail($id);

        return view('unit.edit', compact('unit'));
    }

    /**
     * Perbarui Unit
     */
    public function update(Request $request, $id)
    {
        $this->authorizeAdminOrHrd($request);

        $unit = MasterUnitKerja::findOrFail($id);

        $validated = $request->validate([
            'nama_unit' => "required|string|max:100|unique:master_unit_kerjas,nama_unit,{$id}",
            'deskripsi' => 'nullable|string|max:500',
        ], [
            'nama_unit.required' => 'Nama unit kerja wajib diisi.',
            'nama_unit.unique'   => 'Nama unit kerja sudah terdaftar.',
            'nama_unit.max'      => 'Nama unit kerja maksimal 100 karakter.',
        ]);

        $unit->update($validated);

        return redirect()->route('master-unit.index')
            ->with('success', "Unit kerja '{$unit->nama_unit}' berhasil diperbarui.");
    }

    /**
     * Hapus Unit
     */
    public function destroy(Request $request, $id)
    {
        $this->authorizeAdminOrHrd($request);

        $unit = MasterUnitKerja::withCount('users')->findOrFail($id);

        if ($unit->users_count > 0) {
            return redirect()->route('master-unit.index')
                ->withErrors(['error' => "Unit '{$unit->nama_unit}' tidak dapat dihapus karena masih memiliki {$unit->users_count} pegawai terdaftar."]);
        }

        $nama = $unit->nama_unit;
        $unit->delete();

        return redirect()->route('master-unit.index')
            ->with('success', "Unit kerja '{$nama}' berhasil dihapus.");
    }
}

