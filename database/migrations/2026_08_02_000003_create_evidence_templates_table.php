<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evidence_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // NULL = template bawaan sistem, tersedia untuk seluruh organisasi.
            $table->uuid('organization_id')->nullable();
            $table->uuid('created_by')->nullable();

            $table->string('code')->nullable()->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category')->default('Berita Acara');

            // Isi dokumen dalam HTML, memuat penanda {{...}} yang diisi otomatis
            // dari data task saat dokumen dibuat.
            $table->longText('body_html');

            // Definisi kolom isian tambahan: [{key,label,type,placeholder,required}]
            $table->json('fields')->nullable();

            $table->string('orientation')->default('portrait'); // portrait | landscape
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['organization_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evidence_templates');
    }
};
