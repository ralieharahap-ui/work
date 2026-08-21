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
        if (Schema::hasTable('task_comments')) {
            return;
        }

        Schema::create('task_comments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('task_id');
            $table->uuid('user_id')->nullable();
            $table->text('body');
            $table->timestamps();

            $table->foreign('task_id')->references('id')->on('tasks')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_comments');
    }
};
