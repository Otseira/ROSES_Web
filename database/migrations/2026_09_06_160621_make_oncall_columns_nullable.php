<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Deteksi tipe kolom asli, lalu jadikan NULL-able (aman untuk tipe apa pun)
        $columns = collect(DB::select('SHOW COLUMNS FROM `log_lemburs`'))->keyBy('Field');

        foreach (['waktu_selesai_lembur', 'total_jam_lembur'] as $col) {
            $type = $columns[$col]->Type ?? null;
            if ($type) {
                DB::statement("ALTER TABLE `log_lemburs` MODIFY `$col` $type NULL");
            }
        }
    }

    public function down(): void
    {
        // dibiarkan nullable — aman
    }
};