<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\MasterUnitKerja;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class WebPegawaiController extends Controller
{
    /**
     * Menampilkan Daftar Pegawai
     */
    public function index(Request $request)
    {
        $userLogin = $request->user();

        // Query dasar mengambil user beserta unit kerjanya
        $query = User::with('unitKerja');

        // Jika yang login BUKAN superadmin dan BUKAN hrd (misal: Kepala Unit / Penanggung Jawab)
        // Maka mereka hanya bisa melihat pegawai yang ada di unit kerja mereka sendiri
        if ($userLogin->role !== 'superadmin' && $userLogin->role !== 'hrd') {
            $query->where('unit_kerja_id', $userLogin->unit_kerja_id);
        }

        $pegawai = $query->latest()->get();
        return view('pegawai.index', compact('pegawai'));
    }

    /**
     * Menampilkan Form Tambah Pegawai (Create)
     */
    public function create(Request $request)
    {
        $userLogin = $request->user();

        // Role yang bisa mengelola banyak unit
        $rolesManajemen = ['kepala_unit', 'penanggung_jawab', 'manajer'];

        if ($userLogin->role === 'superadmin' || $userLogin->role === 'hrd') {
            $unitKerja = MasterUnitKerja::orderBy('nama_unit', 'asc')->get();
        } else {
            // Dropdown dikunci hanya untuk unit kerja mereka sendiri
            $unitKerja = MasterUnitKerja::where('id', $userLogin->unit_kerja_id)->get();
        }

        return view('pegawai.create', compact('unitKerja'));
    }


    /**
     * Menyimpan data pegawai baru (Store)
     */
    public function store(Request $request)
    {
        // Tambahkan 'manajer' dan 'direktur' ke validasi role
        $request->validate([
            'nik' => 'required|string|unique:users,nik|max:20',
            'username' => 'required|string|max:50|unique:users,username',
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'nomor_whatsapp' => 'nullable|string|max:20',
            'unit_kerja_id' => 'required|exists:master_unit_kerjas,id',
            'role' => 'required|string|in:staf,kepala_unit,penanggung_jawab,manajer,direktur,hrd,superadmin',
            'password' => 'required|string|min:6',
            'manages_units' => 'nullable|array', // <-- Validasi array unit yang dikelola
            'manages_units.*' => 'exists:master_unit_kerjas,id',
        ]);

        DB::transaction(function () use ($request) {
            $user = User::create([
                'nik' => $request->nik,
                'username' => $request->username,
                'name' => $request->name,
                'email' => $request->email,
                'nomor_whatsapp' => $request->nomor_whatsapp,
                'unit_kerja_id' => $request->unit_kerja_id,
                'role' => $request->role,
                'password' => Hash::make($request->password),
            ]);

            // Jika role adalah Manajer, Kepala Unit, atau Penanggung Jawab, simpan unit yang dikelola
            $rolesManajemen = ['kepala_unit', 'penanggung_jawab', 'manajer'];
            if (in_array($request->role, $rolesManajemen) && $request->has('manages_units')) {
                $user->managesUnits()->sync($request->manages_units);
            }
        });

        return redirect('/master-pegawai')->with('success', 'Data pegawai baru berhasil ditambahkan.');
    }

    /**
     * Menampilkan formulir ubah data pegawai (Edit)
     */
    public function edit(User $master_pegawai)
    {
        $unitKerja = MasterUnitKerja::orderBy('nama_unit', 'asc')->get();
        return view('pegawai.edit', compact('master_pegawai', 'unitKerja'));
    }

    /**
     * Menyimpan perubahan data pegawai (Update)
     */
    public function update(Request $request, User $master_pegawai)
    {
        $request->validate([
            'nik' => 'required|string|max:20|unique:users,nik,' . $master_pegawai->id,
            'username' => 'required|string|max:50|unique:users,username,' . $master_pegawai->id,
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $master_pegawai->id,
            'nomor_whatsapp' => 'nullable|string|max:20',
            'unit_kerja_id' => 'required|exists:master_unit_kerjas,id',
            'role' => 'required|string|in:staf,kepala_unit,penanggung_jawab,manajer,direktur,hrd,superadmin',
            'manages_units' => 'nullable|array',
            'manages_units.*' => 'exists:master_unit_kerjas,id',
        ]);

        DB::transaction(function () use ($request, $master_pegawai) {
            $master_pegawai->update([
                'nik' => $request->nik,
                'username' => $request->username,
                'name' => $request->name,
                'email' => $request->email,
                'nomor_whatsapp' => $request->nomor_whatsapp,
                'unit_kerja_id' => $request->unit_kerja_id,
                'role' => $request->role,
            ]);

            if ($request->filled('password')) {
                $master_pegawai->update(['password' => Hash::make($request->password)]);
            }

            // Sinkronisasi unit yang dikelola
            $rolesManajemen = ['kepala_unit', 'penanggung_jawab', 'manajer'];
            if (in_array($request->role, $rolesManajemen)) {
                $master_pegawai->managesUnits()->sync($request->manages_units ?? []);
            } else {
                // Jika role diubah ke staf/direktur/hrd, hapus semua relasi unit yang dikelola
                $master_pegawai->managesUnits()->detach();
            }
        });

        return redirect('/master-pegawai')->with('success', 'Data pegawai berhasil diperbarui.');
    }

    /**
     * Menghapus data pegawai (Delete)
     */
    public function destroy(User $master_pegawai)
    {
        $master_pegawai->delete();
        return redirect('/master-pegawai')->with('success', 'Data pegawai berhasil dihapus.');
    }
}
