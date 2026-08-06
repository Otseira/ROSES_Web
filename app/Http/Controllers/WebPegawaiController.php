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

        $query = User::with(['unitKerja', 'managesUnits']);

        // Jika yang login BUKAN superadmin dan BUKAN hrd
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
        $request->validate([
            'nik'            => 'nullable|string|max:20|unique:users,nik',
            'email'          => 'nullable|email|max:255|unique:users,email',
            'nomor_whatsapp' => 'nullable|string|max:20',
            'username'       => 'required|string|max:50|unique:users,username',
            'name'           => 'required|string|max:255',
            'unit_kerja_id'  => 'required|exists:master_unit_kerjas,id',
            'role'           => 'required|string|in:staf,kepala_unit,penanggung_jawab,manajer,direktur,hrd,superadmin',
            'password'       => 'required|string|min:6',

            'manages_units'   => 'nullable|array',
            'manages_units.*' => 'exists:master_unit_kerjas,id',
        ]);

        DB::transaction(function () use ($request) {
            $user = User::create([
                'nik'            => $request->nik,             // boleh null
                'username'       => $request->username,
                'name'           => $request->name,
                'email'          => $request->email,           // boleh null
                'nomor_whatsapp' => $request->nomor_whatsapp,  // boleh null
                'unit_kerja_id'  => $request->unit_kerja_id,
                'role'           => $request->role,
                'password'       => Hash::make($request->password),
            ]);

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
            'nik'            => 'nullable|string|max:20|unique:users,nik,' . $master_pegawai->id,
            'email'          => 'nullable|email|max:255|unique:users,email,' . $master_pegawai->id,
            'nomor_whatsapp' => 'nullable|string|max:20',
            'username'      => 'required|string|max:50|unique:users,username,' . $master_pegawai->id,
            'name'          => 'required|string|max:255',
            'unit_kerja_id' => 'required|exists:master_unit_kerjas,id',
            'role'          => 'required|string|in:staf,kepala_unit,penanggung_jawab,manajer,direktur,hrd,superadmin',
            'password'      => 'nullable|string|min:6',

            'manages_units'   => 'nullable|array',
            'manages_units.*' => 'exists:master_unit_kerjas,id',
        ]);

        DB::transaction(function () use ($request, $master_pegawai) {
            $master_pegawai->update([
                'nik'            => $request->nik,
                'username'       => $request->username,
                'name'           => $request->name,
                'email'          => $request->email,
                'nomor_whatsapp' => $request->nomor_whatsapp,
                'unit_kerja_id'  => $request->unit_kerja_id,
                'role'           => $request->role,
            ]);

            if ($request->filled('password')) {
                $master_pegawai->update(['password' => Hash::make($request->password)]);
            }

            $rolesManajemen = ['kepala_unit', 'penanggung_jawab', 'manajer'];
            if (in_array($request->role, $rolesManajemen)) {
                $master_pegawai->managesUnits()->sync($request->manages_units ?? []);
            } else {
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
