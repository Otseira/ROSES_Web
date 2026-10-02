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
        Schema::create('libur_nasionals', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal')->unique();
            $table->string('nama');
            $table->string('jenis', 20)->default('nasional'); // nasional | cuti_bersama
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('libur_nasionals');
    }
};
