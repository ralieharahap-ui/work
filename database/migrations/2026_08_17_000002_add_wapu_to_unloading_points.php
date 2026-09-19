<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('unloading_points')) {
            return;
        }

        Schema::table('unloading_points', function (Blueprint $table) {
            if (! Schema::hasColumn('unloading_points', 'is_wapu')) {
                $table->boolean('is_wapu')->default(false)->after('receivable_balance'); // status Wajib Pungut PPN
            }
            if (! Schema::hasColumn('unloading_points', 'npwp')) {
                $table->string('npwp')->nullable()->after('is_wapu');
            }
        });
    }

    public function down(): void
    {
        Schema::table('unloading_points', function (Blueprint $table) {
            foreach (['npwp', 'is_wapu'] as $col) {
                if (Schema::hasColumn('unloading_points', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
