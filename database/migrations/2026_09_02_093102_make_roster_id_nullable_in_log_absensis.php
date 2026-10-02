<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('log_absensis', function (Blueprint $table) {
            // Drop foreign key lama dulu (jika ada)
            $table->dropForeign(['roster_id']);

            // Jadikan nullable
            $table->unsignedBigInteger('roster_id')->nullable()->change();

            // Tambah foreign key baru dengan nullOnDelete
            $table->foreign('roster_id')
                ->references('id')->on('jadwal_rosters')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('log_absensis', function (Blueprint $table) {
            $table->dropForeign(['roster_id']);
            $table->unsignedBigInteger('roster_id')->nullable(false)->change();
            $table->foreign('roster_id')->references('id')->on('jadwal_rosters');
        });
    }
};
