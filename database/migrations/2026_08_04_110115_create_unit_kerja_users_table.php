<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    // database/migrations/xxxx_create_unit_kerja_users_table.php
    public function up(): void
    {
        Schema::create('unit_kerja_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('master_unit_kerja_id')->constrained('master_unit_kerjas')->cascadeOnDelete();
            $table->timestamps();

            // Cegah duplikasi
            $table->unique(['user_id', 'master_unit_kerja_id']);
        });
    }
};
