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
        Schema::table('users', function (Blueprint $table) {
            $table->integer('saldo_awal_lembur_menit')->default(0)->after('unit_kerja_id');
            $table->string('keterangan_saldo_lembur')->nullable()->after('saldo_awal_lembur_menit');
            $table->timestamp('updated_saldo_lembur_at')->nullable()->after('keterangan_saldo_lembur');
            $table->foreignId('updated_saldo_lembur_by')->nullable()->after('updated_saldo_lembur_at')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['updated_saldo_lembur_by']);
            $table->dropColumn([
                'saldo_awal_lembur_menit',
                'keterangan_saldo_lembur',
                'updated_saldo_lembur_at',
                'updated_saldo_lembur_by'
            ]);
        });
    }
};

