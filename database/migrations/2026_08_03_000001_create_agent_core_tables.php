<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inti runtime agent: definisi agent, task beserta state-nya, langkah rencana,
 * dan jejak audit peristiwa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->string('name');
            $table->string('slug');
            $table->string('role')->default('personal_office_employee');
            $table->text('persona')->nullable();      // ringkas: gaya kerja & batasan
            $table->json('capabilities')->nullable(); // daftar tool yang diizinkan (null = semua)
            $table->string('autonomy')->default('balanced'); // supervised | balanced | autonomous
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->unique(['organization_id', 'slug']);
        });

        Schema::create('agent_tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('agent_id');
            $table->uuid('user_id')->nullable();          // pemilik pekerjaan
            $table->string('source')->default('web');     // web | telegram | api | system
            $table->string('external_ref')->nullable();   // id pesan asal (chat)

            $table->string('title');
            $table->text('objective');
            $table->string('task_type')->default('general');
            $table->json('context')->nullable();
            $table->json('constraints')->nullable();
            $table->text('expected_output')->nullable();

            $table->string('priority')->default('Medium'); // Low | Medium | High | Urgent
            $table->timestamp('deadline')->nullable();
            $table->string('risk_level')->default('low');  // low | medium | high

            $table->string('status')->default('PENDING');

            $table->json('plan')->nullable();              // rencana terstruktur terakhir
            $table->unsignedInteger('plan_version')->default(0);
            $table->unsignedInteger('replans')->default(0);
            $table->unsignedInteger('steps_executed')->default(0);
            $table->uuid('current_step_id')->nullable();

            $table->json('working_memory')->nullable();    // observasi & error terakhir
            $table->json('intermediate_outputs')->nullable();
            $table->longText('final_output')->nullable();
            $table->json('deliverables')->nullable();      // berkas/artefak hasil kerja

            $table->json('confidence')->nullable();        // planning/execution/verification/overall
            $table->json('verification')->nullable();      // hasil pemeriksaan terakhir

            $table->boolean('approval_required')->default(false);
            $table->string('approval_status')->nullable(); // pending | approved | rejected

            $table->json('experience_ids')->nullable();    // pengalaman yang dipakai saat merencanakan
            $table->json('lesson_ids')->nullable();
            $table->text('last_error')->nullable();
            $table->text('failure_reason')->nullable();
            $table->string('idempotency_key')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('agent_id')->references('id')->on('agents')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();

            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'task_type']);
            $table->index(['user_id', 'created_at']);
            $table->unique(['organization_id', 'idempotency_key']);
        });

        Schema::create('agent_task_steps', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('task_id');
            $table->string('step_key');                 // step_1, step_2, ...
            $table->unsignedInteger('position')->default(0);
            $table->unsignedInteger('plan_version')->default(1);

            $table->text('objective');
            $table->string('tool')->nullable();
            $table->json('inputs')->nullable();
            $table->json('dependencies')->nullable();
            $table->json('success_criteria')->nullable();
            $table->string('risk_level')->default('low');

            $table->string('status')->default('pending'); // pending | waiting_approval | running | succeeded | failed | skipped
            $table->unsignedInteger('attempts')->default(0);
            $table->unsignedInteger('max_attempts')->default(3);

            $table->json('output')->nullable();
            $table->text('observation')->nullable();
            $table->text('error')->nullable();
            $table->string('error_class')->nullable();
            $table->json('verification')->nullable();
            $table->float('confidence')->nullable();
            $table->string('idempotency_key')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->foreign('task_id')->references('id')->on('agent_tasks')->cascadeOnDelete();
            $table->index(['task_id', 'position']);
            $table->index(['task_id', 'status']);
        });

        Schema::create('agent_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('task_id')->nullable();
            $table->uuid('agent_id')->nullable();
            $table->uuid('user_id')->nullable();
            $table->uuid('step_id')->nullable();
            $table->string('type');                 // TASK_CREATED, TOOL_CALLED, ...
            $table->string('message')->nullable();  // ringkasan yang aman ditampilkan
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('task_id')->references('id')->on('agent_tasks')->cascadeOnDelete();

            $table->index(['task_id', 'created_at']);
            $table->index(['organization_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_events');
        Schema::dropIfExists('agent_task_steps');
        Schema::dropIfExists('agent_tasks');
        Schema::dropIfExists('agents');
    }
};
