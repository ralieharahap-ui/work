<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lampiran dokumen + jejak hierarki (pembuat → reviewer → approval → penandatangan)
 * yang ditampilkan lewat QR verifikasi publik.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('documents')) {
            return;
        }

        Schema::table('documents', function (Blueprint $table) {
            if (! Schema::hasColumn('documents', 'attachment_path')) {
                $table->string('attachment_path')->nullable();
                $table->string('attachment_name')->nullable();
            }
            if (! Schema::hasColumn('documents', 'reviewed_by')) {
                $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
            }
            if (! Schema::hasColumn('documents', 'approved_by')) {
                $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
            }
            if (! Schema::hasColumn('documents', 'signed_by')) {
                $table->foreignUuid('signed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('signed_at')->nullable();
            }
            if (! Schema::hasColumn('documents', 'verify_token')) {
                $table->string('verify_token', 64)->nullable()->unique();
            }
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            foreach (['reviewed_by', 'approved_by', 'signed_by'] as $fk) {
                if (Schema::hasColumn('documents', $fk)) {
                    $table->dropConstrainedForeignId($fk);
                }
            }
            foreach (['attachment_path', 'attachment_name', 'reviewed_at', 'approved_at', 'signed_at', 'verify_token'] as $col) {
                if (Schema::hasColumn('documents', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
