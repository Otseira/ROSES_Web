<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('log_lemburs', function (Blueprint $table) {
            $table->decimal('latitude_masuk', 10, 8)->nullable()->after('keterangan');
            $table->decimal('longitude_masuk', 11, 8)->nullable()->after('latitude_masuk');
            $table->string('foto_masuk')->nullable()->after('longitude_masuk');
            $table->decimal('latitude_keluar', 10, 8)->nullable()->after('foto_masuk');
            $table->decimal('longitude_keluar', 11, 8)->nullable()->after('latitude_keluar');
            $table->string('foto_keluar')->nullable()->after('longitude_keluar');
        });
    }

    public function down(): void
    {
        Schema::table('log_lemburs', function (Blueprint $table) {
            $table->dropColumn([
                'latitude_masuk',
                'longitude_masuk',
                'foto_masuk',
                'latitude_keluar',
                'longitude_keluar',
                'foto_keluar',
            ]);
        });
    }
};
