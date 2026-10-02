<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('superadmin', 'hrd', 'direktur', 'manajer', 'kepala_unit', 'penanggung_jawab', 'staf') NOT NULL DEFAULT 'staf'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('superadmin', 'hrd', 'kepala_unit', 'penanggung_jawab', 'staf') NOT NULL DEFAULT 'staf'");
    }
};
