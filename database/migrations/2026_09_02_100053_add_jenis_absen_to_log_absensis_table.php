<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('log_absensis', function (Blueprint $table) {
            if (!Schema::hasColumn('log_absensis', 'jenis_absen')) {
                $table->string('jenis_absen', 30)
                    ->nullable()
                    ->default('dalam_radius')
                    ->after('foto_masuk');
            }
        });
    }

    public function down(): void
    {
        Schema::table('log_absensis', function (Blueprint $table) {
            if (Schema::hasColumn('log_absensis', 'jenis_absen')) {
                $table->dropColumn('jenis_absen');
            }
        });
    }
};
