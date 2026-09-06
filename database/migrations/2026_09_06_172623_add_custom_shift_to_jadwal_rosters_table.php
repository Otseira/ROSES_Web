<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jadwal_rosters', function (Blueprint $table) {
            $table->time('custom_jam_masuk')->nullable()->after('shift_id');
            $table->time('custom_jam_pulang')->nullable()->after('custom_jam_masuk');
            $table->string('custom_nama_shift', 50)->nullable()->after('custom_jam_pulang');

            // shift_id boleh null jika pakai custom
            $table->unsignedBigInteger('shift_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('jadwal_rosters', function (Blueprint $table) {
            $table->dropColumn(['custom_jam_masuk', 'custom_jam_pulang', 'custom_nama_shift']);
        });
    }
};
