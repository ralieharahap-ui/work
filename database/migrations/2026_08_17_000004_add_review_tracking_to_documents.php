<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jejak review dokumen: siapa yang terakhir menyunting draft & berapa kali direvisi.
 * Ditampilkan di halaman Dokumentasi agar perubahan oleh reviewer terekam.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('documents')) {
            return;
        }

        Schema::table('documents', function (Blueprint $table) {
            if (! Schema::hasColumn('documents', 'last_edited_by')) {
                $table->foreignUuid('last_edited_by')->nullable()->after('released_at')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('documents', 'last_edited_at')) {
                $table->timestamp('last_edited_at')->nullable()->after('last_edited_by');
            }
            if (! Schema::hasColumn('documents', 'revision_count')) {
                $table->unsignedInteger('revision_count')->default(0)->after('last_edited_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            foreach (['revision_count', 'last_edited_at', 'last_edited_by'] as $col) {
                if (Schema::hasColumn('documents', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
