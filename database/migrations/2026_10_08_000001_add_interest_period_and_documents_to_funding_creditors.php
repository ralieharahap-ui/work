<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Periode bunga: tahun | bulan (data lama dianggap per tahun)
        if (! Schema::hasColumn('funding_creditors', 'interest_period')) {
            Schema::table('funding_creditors', function (Blueprint $table) {
                $table->string('interest_period', 10)->default('tahun')->after('interest_rate');
            });
        }

        // Dokumen underlying & pendukung kreditur (kontrak, bukti transfer, foto jurnal, kwitansi)
        if (! Schema::hasTable('funding_creditor_documents')) {
            Schema::create('funding_creditor_documents', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('funding_creditor_id')->constrained('funding_creditors')->cascadeOnDelete();
                $table->string('doc_type', 30);        // kontrak | bukti_transfer | foto_jurnal | kwitansi
                $table->string('path');
                $table->string('original_name');
                $table->string('mime')->nullable();
                $table->unsignedBigInteger('size')->nullable();
                $table->foreignUuid('uploaded_by')->nullable();
                $table->timestamps();

                $table->index(['funding_creditor_id', 'doc_type']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('funding_creditor_documents');
        if (Schema::hasColumn('funding_creditors', 'interest_period')) {
            Schema::table('funding_creditors', fn (Blueprint $table) => $table->dropColumn('interest_period'));
        }
    }
};
