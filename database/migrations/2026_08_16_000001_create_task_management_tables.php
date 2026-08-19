<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Guard idempoten: tabel-tabel ini bisa sudah dibuat lebih dulu oleh
        // migrasi 2026_08_01_0000001-4 (modul akuntansi & manajemen tugas
        // dikembangkan paralel di branch terpisah sebelum digabung).
        if (! Schema::hasTable('task_projects')) {
            Schema::create('task_projects', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('organization_id');
                $table->uuid('owner_id')->nullable();
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('status');
                $table->date('end_date')->nullable();
                $table->timestamps();

                $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
                $table->foreign('owner_id')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('tasks')) {
            Schema::create('tasks', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('organization_id');
                $table->uuid('project_id')->nullable();
                $table->uuid('division_id')->nullable();
                $table->uuid('pic_id')->nullable();
                $table->uuid('created_by')->nullable();
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('category');
                $table->string('status');
                $table->string('priority');
                $table->date('deadline')->nullable();
                $table->string('evidence_path')->nullable();
                $table->string('evidence_original_name')->nullable();
                $table->uuid('closed_by')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->timestamps();

                $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
                $table->foreign('project_id')->references('id')->on('task_projects')->nullOnDelete();
                $table->foreign('division_id')->references('id')->on('divisions')->nullOnDelete();
                $table->foreign('pic_id')->references('id')->on('users')->nullOnDelete();
                $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
                $table->foreign('closed_by')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('task_checklist_items')) {
            Schema::create('task_checklist_items', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('task_id');
                $table->text('text');
                $table->boolean('is_completed')->default(false);
                $table->integer('position')->default(0);
                $table->timestamps();

                $table->foreign('task_id')->references('id')->on('tasks')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('task_comments')) {
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
    }

    public function down(): void
    {
        Schema::dropIfExists('task_comments');
        Schema::dropIfExists('task_checklist_items');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('task_projects');
    }
};
