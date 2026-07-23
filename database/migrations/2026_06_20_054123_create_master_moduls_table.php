<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_moduls', function (Blueprint $table) {
            $table->id();
            $table->string('kode_modul', 50)->unique(); // Contoh: modul_roster, modul_laporan
            $table->string('nama_modul', 100);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_moduls');
    }
};
