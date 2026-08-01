<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('project_id')->nullable();
            $table->uuid('division_id')->nullable();
            $table->uuid('pic_id')->nullable();      // penanggung jawab / assignee
            $table->uuid('created_by')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category')->default('Operasional'); // Operasional | Strategis
            $table->string('status')->default('To Do');         // To Do|In Progress|Review|Blocked|Done
            $table->string('priority')->default('Medium');      // Low|Medium|High|Urgent
            $table->date('deadline')->nullable();

            // Bukti penyelesaian (wajib diunggah saat status diubah menjadi Done)
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

            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'pic_id']);
            $table->index(['organization_id', 'deadline']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
