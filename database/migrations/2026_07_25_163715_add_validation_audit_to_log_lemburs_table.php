<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('log_lemburs', function (Blueprint $table) {
            $table->unsignedBigInteger('divalidasi_oleh')->nullable()->after('status_validasi');
            $table->timestamp('divalidasi_pada')->nullable()->after('divalidasi_oleh');
            $table->string('catatan_validasi')->nullable()->after('divalidasi_pada');
        });
    }

    public function down(): void
    {
        Schema::table('log_lemburs', function (Blueprint $table) {
            $table->dropColumn(['divalidasi_oleh', 'divalidasi_pada', 'catatan_validasi']);
        });
    }
};
