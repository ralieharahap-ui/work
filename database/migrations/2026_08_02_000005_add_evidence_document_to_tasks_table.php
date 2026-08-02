<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // Diisi bila bukti penutupan berasal dari dokumen evidence yang
            // dibuat & ditandatangani di dalam aplikasi (bukan unggahan manual).
            $table->uuid('evidence_document_id')->nullable()->after('evidence_original_name');

            $table->foreign('evidence_document_id')->references('id')->on('evidence_documents')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // SQLite tidak mendukung DROP FOREIGN KEY; kolomnya langsung dibuang.
            if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                $table->dropForeign(['evidence_document_id']);
            }

            $table->dropColumn('evidence_document_id');
        });
    }
};
