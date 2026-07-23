<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Panggil seeder sesuai urutan ketergantungan data
        $this->call([
            MasterUnitKerjaSeeder::class,
            MasterModulSeeder::class,
            UserDanAksesSeeder::class,
        ]);
    }
}