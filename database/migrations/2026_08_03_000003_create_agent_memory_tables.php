<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Empat lapis memori jangka panjang agent:
 *   episodic   → agent_experiences  ("apa yang terjadi pada task ini")
 *   semantic   → agent_memories     ("apa yang sekarang saya ketahui")
 *   procedural → agent_procedures   ("bagaimana cara mengerjakannya")
 *   performa   → agent_lessons + agent_tool_stats ("strategi mana yang berhasil")
 *
 * Vektor disimpan sebagai json agar penyimpanan vektor dapat diganti tanpa
 * mengubah skema (lihat App\Agent\Contracts\MemoryStore).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_experiences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('agent_id')->nullable();
            $table->uuid('task_id')->nullable();

            $table->string('task_type')->index();
            $table->text('objective');
            $table->json('context')->nullable();
            $table->json('plan')->nullable();
            $table->json('actions')->nullable();
            $table->text('result')->nullable();
            $table->string('outcome')->default('SUCCESS'); // SUCCESS | PARTIAL | FAILURE

            $table->json('errors')->nullable();
            $table->json('successful_patterns')->nullable();
            $table->json('failed_patterns')->nullable();
            $table->json('lessons')->nullable();
            $table->text('reusable_strategy')->nullable();
            $table->json('tools')->nullable();

            $table->float('confidence')->default(0.5);
            $table->unsignedInteger('use_count')->default(0);
            $table->unsignedInteger('success_count')->default(0);
            $table->unsignedInteger('failure_count')->default(0);
            $table->unsignedInteger('duration_seconds')->default(0);

            $table->json('keywords')->nullable();
            $table->json('embedding')->nullable();
            $table->boolean('is_obsolete')->default(false);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('task_id')->references('id')->on('agent_tasks')->nullOnDelete();

            $table->index(['organization_id', 'task_type', 'is_obsolete']);
        });

        Schema::create('agent_lessons', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('experience_id')->nullable();
            $table->string('task_type')->default('general');
            $table->string('scope')->default('process'); // data | tool | process | policy
            $table->string('subject')->nullable();       // sumber data / tool yang disinggung
            $table->text('trigger');                     // kapan pelajaran ini berlaku
            $table->text('lesson');
            $table->text('recommendation')->nullable();  // tindakan konkret yang disarankan
            $table->json('payload')->nullable();         // petunjuk terstruktur (mis. pemetaan kolom)
            $table->json('evidence')->nullable();

            $table->float('confidence')->default(0.5);
            $table->unsignedInteger('use_count')->default(0);
            $table->unsignedInteger('success_count')->default(0);
            $table->unsignedInteger('failure_count')->default(0);

            $table->json('keywords')->nullable();
            $table->json('embedding')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('experience_id')->references('id')->on('agent_experiences')->nullOnDelete();

            $table->index(['organization_id', 'task_type', 'is_active']);
        });

        Schema::create('agent_procedures', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->string('name');                 // MONTHLY_SALES_REPORT
            $table->string('task_type')->default('general');
            $table->unsignedInteger('version')->default(1);
            $table->text('trigger')->nullable();
            $table->json('preconditions')->nullable();
            $table->json('steps');                  // kerangka langkah yang dapat dipakai ulang
            $table->float('success_rate')->default(0.5);
            $table->unsignedInteger('use_count')->default(0);
            $table->unsignedInteger('success_count')->default(0);
            $table->unsignedInteger('failure_count')->default(0);
            $table->json('keywords')->nullable();
            $table->json('embedding')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->unique(['organization_id', 'name', 'version']);
            $table->index(['organization_id', 'task_type', 'is_active']);
        });

        Schema::create('agent_memories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->string('kind')->default('semantic'); // semantic | preference | fact
            $table->string('subject');
            $table->text('content');
            $table->json('keywords')->nullable();
            $table->json('embedding')->nullable();
            $table->float('confidence')->default(0.6);
            $table->string('source')->nullable();
            $table->unsignedInteger('use_count')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->index(['organization_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_memories');
        Schema::dropIfExists('agent_procedures');
        Schema::dropIfExists('agent_lessons');
        Schema::dropIfExists('agent_experiences');
    }
};
