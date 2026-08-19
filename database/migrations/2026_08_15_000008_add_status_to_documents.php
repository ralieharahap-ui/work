<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('documents')) {
            return;
        }

        Schema::table('documents', function (Blueprint $table) {
            if (! Schema::hasColumn('documents', 'status')) {
                $table->string('status')->default('on_review')->after('doc_date'); // on_review|signed|released|cancelled
            }
            if (! Schema::hasColumn('documents', 'released_by')) {
                $table->foreignUuid('released_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('documents', 'released_at')) {
                $table->timestamp('released_at')->nullable()->after('released_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            foreach (['released_at', 'released_by', 'status'] as $col) {
                if (Schema::hasColumn('documents', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
