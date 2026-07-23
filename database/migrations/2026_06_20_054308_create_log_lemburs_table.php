<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('log_lemburs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            
            $table->enum('jenis_lembur', ['On-Call', 'Ekstensi Shift']);
            $table->dateTime('waktu_mulai_lembur');
            $table->dateTime('waktu_selesai_lembur')->nullable();
            
            // Total durasi lembur (dihitung otomatis saat clock-out lembur)
            $table->decimal('total_jam_lembur', 5, 2)->default(0); 
            
            $table->enum('status_validasi', ['Pending', 'Disetujui', 'Ditolak'])->default('Pending');
            $table->text('keterangan')->nullable(); // Alasan lembur/on-call
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('log_lemburs');
    }
};