<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_shifts', function (Blueprint $table) {
            $table->id();
            $table->string('nama_shift', 50); // Contoh: Pagi, Siang, Malam, Reguler
            $table->time('jam_masuk');
            $table->time('jam_pulang');
            $table->integer('toleransi_terlambat_menit')->default(15);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_shifts');
    }
};