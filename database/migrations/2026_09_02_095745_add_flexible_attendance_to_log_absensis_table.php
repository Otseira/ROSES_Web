<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ===== 1. Tambah kolom baru (aman: cek dulu, tidak akan duplikat) =====
        Schema::table('log_absensis', function (Blueprint $table) {
            if (!Schema::hasColumn('log_absensis', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->after('id');
            }
            if (!Schema::hasColumn('log_absensis', 'status_kehadiran')) {
                $table->string('status_kehadiran', 30)->nullable();
            }
            if (!Schema::hasColumn('log_absensis', 'menit_terlambat')) {
                $table->integer('menit_terlambat')->nullable()->default(0);
            }
            if (!Schema::hasColumn('log_absensis', 'durasi_kerja')) {
                $table->integer('durasi_kerja')->nullable();
            }
        });

        // ===== 2. Jadikan roster_id NULLABLE (absen boleh tanpa jadwal) =====
        if (Schema::hasColumn('log_absensis', 'roster_id')) {
            // Drop foreign key lama apa pun namanya (deteksi otomatis)
            $fks = DB::select("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
                               WHERE TABLE_SCHEMA = DATABASE()
                                 AND TABLE_NAME = 'log_absensis'
                                 AND COLUMN_NAME = 'roster_id'
                                 AND REFERENCED_TABLE_NAME IS NOT NULL");
            foreach ($fks as $fk) {
                DB::statement("ALTER TABLE `log_absensis` DROP FOREIGN KEY `{$fk->CONSTRAINT_NAME}`");
            }

            Schema::table('log_absensis', function (Blueprint $table) {
                $table->unsignedBigInteger('roster_id')->nullable()->change();
            });
        }

        // ===== 3. BACKFILL: isi user_id data lama dari roster (data tetap utuh) =====
        DB::table('log_absensis')
            ->whereNull('user_id')
            ->whereNotNull('roster_id')
            ->orderBy('id')
            ->chunkById(200, function ($logs) {
                foreach ($logs as $log) {
                    $roster = DB::table('jadwal_rosters')->where('id', $log->roster_id)->first();
                    if ($roster) {
                        DB::table('log_absensis')
                            ->where('id', $log->id)
                            ->update(['user_id' => $roster->user_id]);
                    }
                }
            });

        // ===== 4. Pasang foreign key baru =====
        Schema::table('log_absensis', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();

            if (Schema::hasColumn('log_absensis', 'roster_id')) {
                $table->foreign('roster_id')->references('id')->on('jadwal_rosters')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('log_absensis', function (Blueprint $table) {
            $table->dropColumn(['user_id', 'status_kehadiran', 'menit_terlambat', 'durasi_kerja']);
        });
    }
};
