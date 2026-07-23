<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('log_absensis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('roster_id')->constrained('jadwal_rosters')->cascadeOnDelete();
            
            // Waktu masuk & pulang
            $table->dateTime('waktu_masuk')->nullable();
            $table->dateTime('waktu_pulang')->nullable();
            
            // Total menit terlambat (dihitung otomatis saat clock-in)
            $table->integer('menit_terlambat')->default(0); 
            
            // Foto (Menyimpan nama file/path)
            $table->string('foto_masuk')->nullable();
            $table->string('foto_pulang')->nullable();
            
            // Koordinat (Tipe Decimal sangat presisi untuk GPS)
            $table->decimal('latitude_masuk', 10, 8)->nullable();
            $table->decimal('longitude_masuk', 11, 8)->nullable();
            $table->decimal('latitude_pulang', 10, 8)->nullable();
            $table->decimal('longitude_pulang', 11, 8)->nullable();
            
            // Validasi jaringan
            $table->string('ip_address_masuk', 45)->nullable();
            $table->string('ip_address_pulang', 45)->nullable();
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('log_absensis');
    }
};