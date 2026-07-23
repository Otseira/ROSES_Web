<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserDanAksesSeeder extends Seeder
{
    public function run(): void
    {
        // Cari ID Unit Kerja IT yang baru saja dibuat oleh MasterUnitKerjaSeeder
        $unitItId = DB::table('master_unit_kerjas')
            ->where('nama_unit', 'like', '%IT dan SIMRS%')
            ->value('id');

        // Buat Akun Utama Administrator IT
        $userId = DB::table('users')->insertGetId([
            'nik' => '1373041803010001',
            'name' => 'Luthfi Ariesto Prayoga',
            'email' => 'administrator@rumahsakit.com',
            'nomor_whatsapp' => '6285156759610',
            'unit_kerja_id' => $unitItId,
            'password' => Hash::make('admin123'), // Pastikan diubah setelah login pertama
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Ambil semua ID modul yang terdaftar
        $allModulIds = DB::table('master_moduls')->pluck('id');

        // Hubungkan akun Admin IT ke semua modul di tabel jembatan (akses_users)
        foreach ($allModulIds as $modulId) {
            DB::table('akses_users')->insert([
                'user_id' => $userId,
                'modul_id' => $modulId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
