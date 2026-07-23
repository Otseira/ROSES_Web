<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('log_absensis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('roster_id')->constrained('jadwal_rosters')->cascadeOnDelete();
            $table->timestamp('waktu_masuk')->nullable();
            $table->timestamp('waktu_pulang')->nullable();
            $table->integer('menit_terlambat')->default(0);
            $table->string('foto_masuk')->nullable();
            $table->string('foto_pulang')->nullable();
            $table->decimal('latitude_masuk', 10, 8)->nullable();
            $table->decimal('longitude_masuk', 11, 8)->nullable();
            $table->decimal('latitude_pulang', 10, 8)->nullable();
            $table->decimal('longitude_pulang', 11, 8)->nullable();
            $table->string('ip_address_masuk')->nullable();
            $table->string('ip_address_pulang')->nullable();
            $table->timestamps();

            // Perbaikan: Index untuk mempercepat pencarian log aktif
            $table->index('roster_id');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('log_absensis');
    }
};
