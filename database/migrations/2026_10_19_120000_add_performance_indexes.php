<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Menambahkan composite indexes untuk mempercepat respon query
     * pada fitur Jadwal Roster, Live Monitoring, Rekap Laporan, dan Absensi.
     */
    public function up(): void
    {
        // 1. Optimasi tabel jadwal_rosters
        Schema::table('jadwal_rosters', function (Blueprint $table) {
            // Index untuk pencarian berdasarkan tanggal dinas (Dashboard & Live Monitoring)
            $table->index(['tanggal_dinas', 'shift_id'], 'idx_jadwal_tgl_shift');
            // Index untuk filter tanggal per user (Matriks Roster Bulanan & Rekap Pegawai)
            $table->index(['tanggal_dinas', 'user_id'], 'idx_jadwal_tgl_user');
        });

        // 2. Optimasi tabel log_absensis
        Schema::table('log_absensis', function (Blueprint $table) {
            // Index untuk lookup absensi pegawai berdasarkan tanggal dinas
            $table->index(['user_id', 'tanggal_dinas'], 'idx_log_absensi_user_tgl');
            // Index untuk query riwayat kehadiran berdasarkan rentang waktu masuk
            $table->index(['waktu_masuk', 'status_kehadiran'], 'idx_log_absensi_waktu_status');
            // Index untuk verifikasi cepat apakah roster sudah diabsen
            $table->index(['roster_id', 'waktu_masuk'], 'idx_log_absensi_roster_waktu');
        });

        // 3. Optimasi tabel log_lemburs
        Schema::table('log_lemburs', function (Blueprint $table) {
            // Index untuk antrean approval lembur dan filter status
            $table->index(['status_validasi', 'waktu_mulai_lembur'], 'idx_lembur_status_waktu');
            // Index untuk riwayat lembur per user
            $table->index(['user_id', 'waktu_mulai_lembur'], 'idx_lembur_user_waktu');
        });

        // 4. Optimasi tabel users
        Schema::table('users', function (Blueprint $table) {
            // Index untuk filtering pegawai aktif per unit kerja
            $table->index(['unit_kerja_id', 'is_active'], 'idx_users_unit_active');
            // Index untuk filtering pegawai per role
            $table->index(['role', 'is_active'], 'idx_users_role_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('jadwal_rosters', function (Blueprint $table) {
            $table->dropIndex('idx_jadwal_tgl_shift');
            $table->dropIndex('idx_jadwal_tgl_user');
        });

        Schema::table('log_absensis', function (Blueprint $table) {
            $table->dropIndex('idx_log_absensi_user_tgl');
            $table->dropIndex('idx_log_absensi_waktu_status');
            $table->dropIndex('idx_log_absensi_roster_waktu');
        });

        Schema::table('log_lemburs', function (Blueprint $table) {
            $table->dropIndex('idx_lembur_status_waktu');
            $table->dropIndex('idx_lembur_user_waktu');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_users_unit_active');
            $table->dropIndex('idx_users_role_active');
        });
    }
};

