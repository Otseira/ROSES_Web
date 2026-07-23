<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
{
    Schema::create('pengaturan_aplikasis', function (Blueprint $table) {
        $table->id();
        $table->string('nama_instansi')->default('RSKB Ropanasuri');
        $table->string('logo')->nullable(); // Untuk menyimpan path file logo
        $table->string('latitude')->default('-0.9471'); // Default titik koordinat Padang
        $table->string('longitude')->default('100.3511');
        $table->integer('radius_meter')->default(50); // Radius toleransi jarak absen
        $table->timestamps();
    });
}
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pengaturan_aplikasis');
    }
};
