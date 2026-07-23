<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MasterModulSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('master_moduls')->insert([
            // --- KELOMPOK FITUR UTAMA PEGAWAI ---
            [
                'kode_modul' => 'modul_absensi',
                'nama_modul' => 'Presensi Mobile (Clock-In/Out & Kamera)',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'kode_modul' => 'modul_perizinan',
                'nama_modul' => 'Pengajuan Cuti, Sakit & Izin',
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // --- KELOMPOK MANAJEMEN UNIT & ROSTER ---
            [
                'kode_modul' => 'modul_roster',
                'nama_modul' => 'Manajemen Roster Jadwal Unit',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'kode_modul' => 'modul_monitoring',
                'nama_modul' => 'Pantauan Kehadiran Real-Time (Live Dashboard)',
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // --- KELOMPOK ADMINISTRASI & SDM / HRD ---
            [
                'kode_modul' => 'modul_master',
                'nama_modul' => 'Manajemen Data Pegawai, Unit & Shift',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'kode_modul' => 'modul_payroll',
                'nama_modul' => 'Rekapitulasi Laporan Bulanan & Payroll',
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // --- KELOMPOK KHUSUS ADMINISTRATOR IT ---
            [
                'kode_modul' => 'modul_akses',
                'nama_modul' => 'Pengaturan Hak Akses Karyawan (ACL)',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'kode_modul' => 'modul_log_system',
                'nama_modul' => 'Log Sistem & Keamanan Jaringan',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}