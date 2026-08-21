<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Guard idempoten: tabel ini bisa sudah dibuat lebih dulu oleh migrasi
        // 2026_08_16_000001 (modul akuntansi & manajemen tugas dikembangkan
        // paralel di branch terpisah sebelum digabung).
        if (Schema::hasTable('task_projects')) {
            return;
        }

        Schema::create('task_projects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('owner_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('Planning'); // Planning | Ongoing | On Hold | Closed
            $table->date('end_date')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('owner_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_projects');
    }
};
