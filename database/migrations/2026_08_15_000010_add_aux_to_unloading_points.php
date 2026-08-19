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
            if (! Schema::hasColumn('unloading_points', 'code')) {
                $table->string('code')->nullable()->after('customer_name'); // kode bantu customer (piutang)
            }
            if (! Schema::hasColumn('unloading_points', 'receivable_balance')) {
                $table->decimal('receivable_balance', 18, 2)->default(0)->after('code'); // saldo piutang awal
            }
        });
    }

    public function down(): void
    {
        Schema::table('unloading_points', function (Blueprint $table) {
            foreach (['receivable_balance', 'code'] as $col) {
                if (Schema::hasColumn('unloading_points', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
