<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengambilan_lemburs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            
            // Tanggal dinas yang ingin digunakan untuk kompensasi lembur
            $table->date('tanggal_dinas');
            
            // Jenis kompensasi: jam masuk diperlambat ATAU jam pulang dipercepat
            $table->enum('tipe_pengambilan', ['masuk_diperlambat', 'pulang_dipercepat']);
            
            // Durasi kompensasi yang diambil dalam menit (contoh: 30, 60, 120)
            $table->integer('durasi_menit');
            
            $table->text('keterangan')->nullable();
            
            // Status validasi oleh atasan
            $table->enum('status_validasi', ['Pending', 'Disetujui', 'Ditolak'])->default('Pending');
            
            // Audit validasi atasan
            $table->foreignId('divalidasi_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('divalidasi_pada')->nullable();
            $table->text('catatan_validasi')->nullable();
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengambilan_lemburs');
    }
};

