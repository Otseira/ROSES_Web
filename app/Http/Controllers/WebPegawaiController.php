<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\MasterUnitKerja;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class WebPegawaiController extends Controller
{
    private function authorizeManager(Request $request): void
    {
        if (!$request->user()->canManageUsers()) {
            abort(403, 'Akses ditolak. Anda tidak memiliki izin untuk mengelola data pegawai.');
        }
    }

    /**
     * Menampilkan Daftar Pegawai
     */
    public function index(Request $request)
    {
        $userLogin = $request->user();

        $query = User::with(['unitKerja', 'managesUnits']);

        if (!$userLogin->hasGlobalAccess()) {
            $managedUnits = $userLogin->managesUnits()->pluck('master_unit_kerja_id')->toArray();
            if ($userLogin->unit_kerja_id && !in_array($userLogin->unit_kerja_id, $managedUnits)) {
                $managedUnits[] = $userLogin->unit_kerja_id;
            }
            if (empty($managedUnits)) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('unit_kerja_id', $managedUnits);
            }
        }

        if ($request->filled('unit')) {
            $query->where('unit_kerja_id', $request->unit);
        }

        if ($request->filled('q')) {
            $keyword = trim((string) $request->q);
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', '%' . $keyword . '%')
                    ->orWhere('username', 'like', '%' . $keyword . '%')
                    ->orWhere('nik', 'like', '%' . $keyword . '%');
            });
        }

        $pegawai = $query->orderBy('name', 'asc')->get();

        $units = MasterUnitKerja::orderBy('nama_unit', 'asc')->get();

        return view('pegawai.index', compact('pegawai', 'units'));
    }

    /**
     * Menampilkan Form Tambah Pegawai (Create)
     */
    public function create(Request $request)
    {
        $this->authorizeManager($request);
        $userLogin = $request->user();

        if ($userLogin->hasGlobalAccess()) {
            $unitKerja = MasterUnitKerja::orderBy('nama_unit', 'asc')->get();
        } else {
            $managedUnits = $userLogin->managesUnits()->pluck('master_unit_kerja_id')->toArray();
            if ($userLogin->unit_kerja_id && !in_array($userLogin->unit_kerja_id, $managedUnits)) {
                $managedUnits[] = $userLogin->unit_kerja_id;
            }
            $unitKerja = MasterUnitKerja::whereIn('id', $managedUnits)->orderBy('nama_unit', 'asc')->get();
        }

        return view('pegawai.create', compact('unitKerja'));
    }

    /**
     * Menyimpan data pegawai baru (Store)
     */
    public function store(Request $request)
    {
        $this->authorizeManager($request);
        $userLogin = $request->user();

        // Pencegahan Privilege Escalation: Hanya Superadmin yang boleh membuat Superadmin
        if ($request->role === 'superadmin' && !$userLogin->isSuperadmin()) {
            abort(403, 'Akses ditolak. Hanya Super Administrator yang dapat membuat akun dengan peran Super Admin.');
        }

        $request->validate([
            'nik'            => 'nullable|string|max:20|unique:users,nik',
            'email'          => 'nullable|email|max:255|unique:users,email',
            'nomor_whatsapp' => 'nullable|string|max:20',
            'username'       => 'required|string|max:50|alpha_dash|unique:users,username',
            'name'           => 'required|string|max:255',
            'unit_kerja_id'  => 'required|exists:master_unit_kerjas,id',
            'role'           => 'required|string|in:staf,kepala_unit,penanggung_jawab,manajer,direktur,hrd,superadmin',
            'password'       => 'required|string|min:6',

            'manages_units'   => 'nullable|array',
            'manages_units.*' => 'exists:master_unit_kerjas,id',
        ]);

        DB::transaction(function () use ($request) {
            $user = User::create([
                'nik'            => $request->nik,
                'username'       => $request->username,
                'name'           => $request->name,
                'email'          => $request->email,
                'nomor_whatsapp' => $request->nomor_whatsapp,
                'unit_kerja_id'  => $request->unit_kerja_id,
                'role'           => $request->role,
                'password'       => Hash::make($request->password),
                'is_active'      => true,
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
    public function edit(Request $request, User $master_pegawai)
    {
        $this->authorizeManager($request);
        $userLogin = $request->user();

        // Pencegahan IDOR / Escalation pada akun Super Admin
        if ($master_pegawai->isSuperadmin() && !$userLogin->isSuperadmin()) {
            abort(403, 'Akses ditolak. Anda tidak memiliki izin untuk mengedit data Super Administrator.');
        }

        $unitKerja = MasterUnitKerja::orderBy('nama_unit', 'asc')->get();
        return view('pegawai.edit', compact('master_pegawai', 'unitKerja'));
    }

    /**
     * Menyimpan perubahan data pegawai (Update)
     */
    public function update(Request $request, User $master_pegawai)
    {
        $this->authorizeManager($request);
        $userLogin = $request->user();

        // Pencegahan IDOR / Escalation pada akun Super Admin
        if ($master_pegawai->isSuperadmin() && !$userLogin->isSuperadmin()) {
            abort(403, 'Akses ditolak. Anda tidak memiliki izin untuk mengedit data Super Administrator.');
        }

        // Pencegahan Privilege Escalation: Hanya Superadmin yang boleh menugaskan peran Superadmin
        if ($request->role === 'superadmin' && !$userLogin->isSuperadmin()) {
            abort(403, 'Akses ditolak. Hanya Super Administrator yang dapat menugaskan peran Super Admin.');
        }

        $request->validate([
            'nik'            => 'nullable|string|max:20|unique:users,nik,' . $master_pegawai->id,
            'email'          => 'nullable|email|max:255|unique:users,email,' . $master_pegawai->id,
            'nomor_whatsapp' => 'nullable|string|max:20',
            'username'       => 'required|string|max:50|alpha_dash|unique:users,username,' . $master_pegawai->id,
            'name'           => 'required|string|max:255',
            'unit_kerja_id'  => 'required|exists:master_unit_kerjas,id',
            'role'           => 'required|string|in:staf,kepala_unit,penanggung_jawab,manajer,direktur,hrd,superadmin',
            'password'       => 'nullable|string|min:6',

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
    public function destroy(Request $request, User $master_pegawai)
    {
        $this->authorizeManager($request);
        $userLogin = $request->user();

        // Cegah menghapus diri sendiri
        if ($master_pegawai->id === $userLogin->id) {
            return redirect('/master-pegawai')->withErrors(['Anda tidak dapat menghapus akun Anda sendiri.']);
        }

        // Cegah non-superadmin menghapus superadmin
        if ($master_pegawai->isSuperadmin()) {
            if (!$userLogin->isSuperadmin()) {
                abort(403, 'Akses ditolak. Hanya Super Administrator yang dapat menghapus akun Super Admin.');
            }

            // Cegah menghapus satu-satunya superadmin
            if (User::where('role', 'superadmin')->count() <= 1) {
                return redirect('/master-pegawai')->withErrors(['Tidak dapat menghapus satu-satunya akun Super Administrator dalam sistem.']);
            }
        }

        $master_pegawai->delete();
        return redirect('/master-pegawai')->with('success', 'Data pegawai berhasil dihapus.');
    }
}
