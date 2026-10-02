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
            $table->date('tanggal_dinas')->nullable()->after('roster_id');
            $table->index(['tanggal_dinas', 'waktu_masuk']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('log_absensis', function (Blueprint $table) {
            //
        });
    }
};
