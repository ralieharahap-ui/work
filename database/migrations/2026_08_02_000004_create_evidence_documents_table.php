<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evidence_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('task_id');
            $table->uuid('template_id')->nullable();
            $table->uuid('created_by')->nullable();

            $table->string('number')->nullable();   // nomor dokumen / kertas kerja
            $table->string('title');
            $table->longText('content_html');
            $table->json('data')->nullable();       // nilai kolom isian template
            $table->string('orientation')->default('portrait');

            // draft  → masih bisa diedit
            // signed → sudah ditandatangani PIC dan dibekukan menjadi PDF
            $table->string('status')->default('draft');

            $table->string('signature_path')->nullable();
            $table->uuid('signer_id')->nullable();
            $table->string('signer_name')->nullable();
            $table->string('signer_position')->nullable();
            $table->string('signature_place')->nullable();
            $table->timestamp('signed_at')->nullable();

            $table->string('pdf_path')->nullable();
            $table->string('pdf_original_name')->nullable();

            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('task_id')->references('id')->on('tasks')->cascadeOnDelete();
            $table->foreign('template_id')->references('id')->on('evidence_templates')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('signer_id')->references('id')->on('users')->nullOnDelete();

            $table->index(['organization_id', 'status']);
            $table->index(['task_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evidence_documents');
    }
};
