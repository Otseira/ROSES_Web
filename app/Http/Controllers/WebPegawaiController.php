<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\MasterUnitKerja;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

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

        // 1. Jika yang login adalah Superadmin atau HRD, mereka BEBAS memilih & melihat semua unit kerja
        if ($userLogin->role === 'superadmin' || $userLogin->role === 'hrd') {
            $unitKerja = MasterUnitKerja::orderBy('nama_unit', 'asc')->get();
        } else {
            // 2. Jika Kepala Unit / Penanggung Jawab, dropdown DIKUNCI hanya untuk unit kerja mereka sendiri
            $unitKerja = MasterUnitKerja::where('id', $userLogin->unit_kerja_id)->get();
        }

        // KUNCI COBA (Bypass): Jika dropdown masih kosong saat uji coba, matikan if-else di atas 
        // dan aktifkan baris di bawah ini untuk memaksa semua unit muncul:
        // $unitKerja = MasterUnitKerja::orderBy('nama_unit', 'asc')->get();

        return view('pegawai.create', compact('unitKerja'));
    }

    /**
     * Menyimpan data pegawai baru (Store)
     */
    public function store(Request $request)
    {
        // Validasi data input tanpa master modul
        $request->validate([
            'nik' => 'required|string|unique:users,nik|max:20',
            'username' => 'required|string|max:50|unique:users,username',
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'nomor_whatsapp' => 'nullable|string|max:20',
            'unit_kerja_id' => 'required|exists:master_unit_kerjas,id',
            'role' => 'required|string|in:staf,kepala_unit,penanggung_jawab,hrd,superadmin', // Validasi Role langsung
            'password' => 'required|string|min:6',
        ]);

        // Simpan data User baru ke database
        User::create([
            'nik' => $request->nik,
            'username' => $request->username,
            'name' => $request->name,
            'email' => $request->email,
            'nomor_whatsapp' => $request->nomor_whatsapp,
            'unit_kerja_id' => $request->unit_kerja_id,
            'role' => $request->role, // Menyimpan role langsung
            'password' => Hash::make($request->password),
        ]);

        return redirect('/master-pegawai')->with('success', 'Data pegawai baru berhasil ditambahkan.');
    }

    /**
     * Menampilkan formulir ubah data pegawai (Edit)
     */
    public function edit(User $master_pegawai)
    {
        // Mengambil semua unit kerja untuk kebutuhan edit data
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
            'role' => 'required|string|in:staf,kepala_unit,penanggung_jawab,hrd,superadmin',
        ]);

        // Update data dasar
        $master_pegawai->update([
            'nik' => $request->nik,
            'username' => $request->username,
            'name' => $request->name,
            'email' => $request->email,
            'nomor_whatsapp' => $request->nomor_whatsapp,
            'unit_kerja_id' => $request->unit_kerja_id,
            'role' => $request->role,
        ]);

        // Jika password diisi, ganti password
        if ($request->filled('password')) {
            $master_pegawai->update(['password' => Hash::make($request->password)]);
        }

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