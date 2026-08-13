<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Eksekusi tool (dengan kunci idempotensi), statistik keberhasilan tool,
 * dan antrean persetujuan manusia untuk tindakan berisiko.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_tool_executions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('task_id');
            $table->uuid('step_id')->nullable();
            $table->string('tool');
            $table->string('task_type')->nullable();
            $table->json('input')->nullable();    // sudah melalui redaksi rahasia
            $table->json('output')->nullable();   // dipangkas & diredaksi
            $table->string('status')->default('pending'); // succeeded | failed | skipped
            $table->text('error')->nullable();
            $table->string('error_class')->nullable();
            $table->unsignedInteger('duration_ms')->default(0);
            $table->unsignedInteger('attempt')->default(1);
            $table->string('idempotency_key');
            $table->boolean('replayed')->default(false); // hasil dipakai ulang, tool tidak dipanggil lagi
            $table->timestamps();


            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('task_id')->references('id')->on('agent_tasks')->cascadeOnDelete();
            $table->foreign('step_id')->references('id')->on('agent_task_steps')->nullOnDelete();

            // Sengaja indeks biasa, bukan unik: satu kunci idempotensi boleh
            // memiliki beberapa percobaan yang gagal. Yang dijamin unik adalah
            // percobaan yang BERHASIL — dijaga di Executor, yang memesan baris
            // 'running' sebelum tool dipanggil dan menolak mengulang tindakan
            // tak-idempoten yang nasibnya tidak diketahui.
            $table->index('idempotency_key');
            $table->index(['task_id', 'created_at']);
            $table->index(['organization_id', 'tool']);
        });

        Schema::create('agent_tool_stats', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->string('tool');
            $table->string('task_type')->default('general');
            $table->unsignedInteger('runs')->default(0);
            $table->unsignedInteger('successes')->default(0);
            $table->unsignedInteger('failures')->default(0);
            $table->unsignedBigInteger('total_duration_ms')->default(0);
            $table->json('common_errors')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->unique(['organization_id', 'tool', 'task_type']);
        });

        Schema::create('agent_approvals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('task_id');
            $table->uuid('step_id')->nullable();
            $table->string('tool')->nullable();
            $table->string('risk_level')->default('high');
            $table->string('summary');
            $table->text('rationale')->nullable();  // alasan agent meminta tindakan ini
            $table->json('payload')->nullable();    // pratinjau tindakan (sudah diredaksi)
            $table->string('status')->default('pending'); // pending | approved | rejected | expired | cancelled
            $table->uuid('decided_by')->nullable();
            $table->string('decided_via')->nullable(); // web | telegram
            $table->text('decision_note')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('task_id')->references('id')->on('agent_tasks')->cascadeOnDelete();
            $table->foreign('step_id')->references('id')->on('agent_task_steps')->nullOnDelete();
            $table->foreign('decided_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['organization_id', 'status']);
            $table->index(['task_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_approvals');
        Schema::dropIfExists('agent_tool_stats');
        Schema::dropIfExists('agent_tool_executions');
    }
};
