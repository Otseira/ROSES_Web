<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MasterUnitKerjaSeeder extends Seeder
{
    public function run(): void
    {
        $units = [
            ['nama_unit' => 'Ins Rawat Inap', 'deskripsi' => 'Instalasi Rawat Inap'],
            ['nama_unit' => 'Ins Kamar Operasi', 'deskripsi' => 'Instalasi Kamar Operasi'],
            ['nama_unit' => 'Ins Farmasi', 'deskripsi' => 'Instalasi Farmasi'],
            ['nama_unit' => 'Ins Rekam Medis', 'deskripsi' => 'Instalasi Rekam Medis'],
            ['nama_unit' => 'Labor', 'deskripsi' => 'Laboratorium'],
            ['nama_unit' => 'Gizi', 'deskripsi' => 'Unit Gizi'],
            ['nama_unit' => 'Sopir', 'deskripsi' => 'Ambulans & Operasional'],
            ['nama_unit' => 'Keuangan', 'deskripsi' => 'Bagian Keuangan'],
            ['nama_unit' => 'Loundry', 'deskripsi' => 'Unit Laundry'],
            ['nama_unit' => 'Satpam', 'deskripsi' => 'Keamanan / Security'],
            ['nama_unit' => 'Rontgen', 'deskripsi' => 'Radiologi / Rontgen'],
            ['nama_unit' => 'SDM', 'deskripsi' => 'Sumber Daya Manusia / Kepegawaian'],
            ['nama_unit' => 'Cleaning Servis', 'deskripsi' => 'Clening Service'],
            ['nama_unit' => 'IPRS', 'deskripsi' => 'Instalasi Pemeliharaan Sarana Rumah Sakit'],
            ['nama_unit' => 'DOKTER JAGA', 'deskripsi' => 'Tim Dokter Jaga'],
            ['nama_unit' => 'Akutansi', 'deskripsi' => 'Bagian Akuntansi'],
            ['nama_unit' => 'BPJS', 'deskripsi' => 'Unit Pengelola BPJS & Asuransi'],
            ['nama_unit' => 'Wadir Pelayanan Medis', 'deskripsi' => 'Wakil Direktur Pelayanan Medis'],
            ['nama_unit' => 'Sekretaris Direksi', 'deskripsi' => 'Sekretariat Direksi'],
            ['nama_unit' => 'Kasir', 'deskripsi' => 'Bagian Kasir / Pembayaran'],
            ['nama_unit' => 'Logistik Umum', 'deskripsi' => 'Bagian Logistik Umum'],
            ['nama_unit' => 'Logistik Obat', 'deskripsi' => 'Bagian Logistik Obat & Alkes'],
            ['nama_unit' => 'Brankarman', 'deskripsi' => 'Petugas Brankar / Transporter'],
            ['nama_unit' => 'Ins IGD', 'deskripsi' => 'Instalasi Gawat Darurat'],
            ['nama_unit' => 'Ins Rawat Jalan', 'deskripsi' => 'Instalasi Rawat Jalan / Poliklinik'],
            ['nama_unit' => 'Labor/PA', 'deskripsi' => 'Laboratorium Patologi Anatomi'],
            ['nama_unit' => 'CS RANAP', 'deskripsi' => 'Cleaning Service Rawat Inap'],
            ['nama_unit' => 'Mutu', 'deskripsi' => 'Komite / Tim Mutu Rumah Sakit'],
            ['nama_unit' => 'Manager Penunjang Medis', 'deskripsi' => 'Manajer Penunjang Medis'],
            ['nama_unit' => 'Manager Medis', 'deskripsi' => 'Manajer Medis'],
            ['nama_unit' => 'PKRS', 'deskripsi' => 'Promosi Kesehatan Rumah Sakit'],
            ['nama_unit' => 'CSSD', 'deskripsi' => 'Central Sterile Supply Department'],
            ['nama_unit' => 'KESLING', 'deskripsi' => 'Kesehatan Lingkungan / Sanitarian'],
            ['nama_unit' => 'KEPERAWATAN', 'deskripsi' => 'Bidang / Komite Keperawatan'],
            ['nama_unit' => 'CODER', 'deskripsi' => 'Petugas Coder Klaim'],
            ['nama_unit' => 'CASE MANAGER', 'deskripsi' => 'Manajer Pelayanan Pasien (MPP)'],
            ['nama_unit' => 'DPJP', 'deskripsi' => 'Dokter Penanggung Jawab Pelayanan'],
            ['nama_unit' => 'DIREKTUR', 'deskripsi' => 'Direktur Utama Rumah Sakit'],
            ['nama_unit' => 'IT dan SIMRS', 'deskripsi' => 'Teknologi Informasi & Sistem Informasi Manajemen RS'],
            ['nama_unit' => 'SPI', 'deskripsi' => 'Satuan Pengawas Internal'],
        ];

        // Otomatis menambahkan timestamp untuk setiap data
        foreach ($units as &$unit) {
            $unit['created_at'] = now();
            $unit['updated_at'] = now();
        }

        DB::table('master_unit_kerjas')->insert($units);
    }
}