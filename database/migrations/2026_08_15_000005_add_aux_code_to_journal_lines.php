<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('journal_lines') && ! Schema::hasColumn('journal_lines', 'aux_code')) {
            Schema::table('journal_lines', function (Blueprint $table) {
                $table->string('aux_code')->nullable()->after('account_id'); // kode bantu (vendor/customer/aset)
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('journal_lines', 'aux_code')) {
            Schema::table('journal_lines', function (Blueprint $table) {
                $table->dropColumn('aux_code');
            });
        }
    }
};
