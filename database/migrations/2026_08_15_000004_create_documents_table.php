<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('documents')) {
            return;
        }

        Schema::create('documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');          // invoice | faktur | kwitansi | surat_jalan | voucher_jurnal | ...
            $table->string('number');        // nomor dokumen (mis. INV-2026-0001)
            $table->date('doc_date');
            $table->json('meta');            // seluruh field spesifik per jenis dokumen
            $table->string('ref_type')->nullable(); // sumber tertaut (journal_entry / scenario)
            $table->uuid('ref_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'type']);
            $table->unique(['organization_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
