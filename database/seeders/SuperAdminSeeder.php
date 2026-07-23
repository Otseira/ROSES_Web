<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        // Membuat akun independen tanpa pencarian unit kerja
        User::updateOrCreate(
            ['username' => 'admin'], // Menggunakan username sebagai patokan utama
            [
                'nik' => '000000', // NIK khusus/dummy untuk superadmin
                'name' => 'Administrator',
                'email' => 'admin@simrs.com',
                'password' => Hash::make('rahasia123'),
                'unit_kerja_id' => null, // Dibuat NULL karena tidak terikat unit manapun
                'role' => 'superadmin',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}