<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengaturan_payrolls', function (Blueprint $table) {
            $table->id();
            $table->decimal('potongan_terlambat_per_menit', 10, 2)->default(0);
            $table->decimal('uang_lembur_per_jam', 10, 2)->default(0);
            $table->integer('tanggal_cut_off_mulai')->default(24);
            $table->integer('tanggal_cut_off_selesai')->default(23);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengaturan_payrolls');
    }
};
