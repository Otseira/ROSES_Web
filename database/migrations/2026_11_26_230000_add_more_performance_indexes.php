<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Composite indexes tambahan untuk mempercepat Rekap Absensi, Monitoring, dan Bank Jam.
     */
    public function up(): void
    {
        if (Schema::hasTable('log_absensis')) {
            Schema::table('log_absensis', function (Blueprint $table) {
                $table->index(['user_id', 'waktu_masuk'], 'idx_log_absensi_user_waktu');
                $table->index(['waktu_masuk', 'user_id'], 'idx_log_absensi_waktu_user');
            });
        }

        if (Schema::hasTable('pengambilan_lemburs')) {
            Schema::table('pengambilan_lemburs', function (Blueprint $table) {
                $table->index(['user_id', 'status_validasi'], 'idx_pengambilan_user_status');
                $table->index(['tanggal_dinas', 'status_validasi'], 'idx_pengambilan_tgl_status');
            });
        }

        if (Schema::hasTable('unit_kerja_users')) {
            Schema::table('unit_kerja_users', function (Blueprint $table) {
                $table->index(['user_id', 'master_unit_kerja_id'], 'idx_uku_user_unit');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('log_absensis')) {
            Schema::table('log_absensis', function (Blueprint $table) {
                $table->dropIndex('idx_log_absensi_user_waktu');
                $table->dropIndex('idx_log_absensi_waktu_user');
            });
        }

        if (Schema::hasTable('pengambilan_lemburs')) {
            Schema::table('pengambilan_lemburs', function (Blueprint $table) {
                $table->dropIndex('idx_pengambilan_user_status');
                $table->dropIndex('idx_pengambilan_tgl_status');
            });
        }

        if (Schema::hasTable('unit_kerja_users')) {
            Schema::table('unit_kerja_users', function (Blueprint $table) {
                $table->dropIndex('idx_uku_user_unit');
            });
        }
    }
};
