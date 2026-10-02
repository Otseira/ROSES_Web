<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pengaturan_aplikasis', function (Blueprint $table) {
            if (!Schema::hasColumn('pengaturan_aplikasis', 'splash_bg')) {
                $table->string('splash_bg')->nullable()->after('logo');
            }
            if (!Schema::hasColumn('pengaturan_aplikasis', 'splash_bg_color')) {
                $table->string('splash_bg_color', 9)->nullable()->after('splash_bg');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pengaturan_aplikasis', function (Blueprint $table) {
            $table->dropColumn(['splash_bg', 'splash_bg_color']);
        });
    }
};
