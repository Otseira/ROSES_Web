<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jadwal_rosters', function (Blueprint $table) {
            $table->unsignedTinyInteger('sesi')->default(1)->after('tanggal_dinas');
        });

        // Unik lama (user+tanggal) diganti unik baru (user+tanggal+sesi)
        try {
            Schema::table('jadwal_rosters', function (Blueprint $table) {
                $table->dropUnique(['user_id', 'tanggal_dinas']);
            });
        } catch (\Exception $e) {
            // jika index unik tidak ada, abaikan
        }

        Schema::table('jadwal_rosters', function (Blueprint $table) {
            $table->unique(['user_id', 'tanggal_dinas', 'sesi'], 'jdwl_roster_user_tgl_sesi_unique');
        });
    }

    public function down(): void
    {
        Schema::table('jadwal_rosters', function (Blueprint $table) {
            $table->dropUnique('jdwl_roster_user_tgl_sesi_unique');
            $table->dropColumn('sesi');
        });
    }
};
