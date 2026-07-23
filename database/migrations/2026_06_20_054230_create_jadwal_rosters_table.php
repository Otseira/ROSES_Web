<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jadwal_rosters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('shift_id')->constrained('master_shifts')->cascadeOnDelete();
            $table->date('tanggal_dinas');
            $table->timestamps();
            
            // Mencegah duplikasi jadwal 1 pegawai pada tanggal yang sama
            $table->unique(['user_id', 'tanggal_dinas']); 
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jadwal_rosters');
    }
};