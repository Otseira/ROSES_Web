<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('master_unit_kerjas', function (Blueprint $table) {
            if (!Schema::hasColumn('master_unit_kerjas', 'jenis_lembur')) {
                // 'dibayar' = Lembur dibayarkan berupa uang / payroll
                // 'ganti_jam' = Lembur diganti jam (jam masuk diperlambat / jam pulang dipercepat)
                $table->enum('jenis_lembur', ['dibayar', 'ganti_jam'])
                    ->default('dibayar')
                    ->after('deskripsi');
            }
        });
    }

    public function down(): void
    {
        Schema::table('master_unit_kerjas', function (Blueprint $table) {
            if (Schema::hasColumn('master_unit_kerjas', 'jenis_lembur')) {
                $table->dropColumn('jenis_lembur');
            }
        });
    }
};

