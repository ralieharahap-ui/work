<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('journal_entries')) {
            Schema::table('journal_entries', function (Blueprint $table) {
                if (! Schema::hasColumn('journal_entries', 'status')) {
                    $table->string('status')->default('draft')->after('is_posted'); // draft|pending|posted|rejected
                }
                if (! Schema::hasColumn('journal_entries', 'current_level')) {
                    $table->unsignedTinyInteger('current_level')->default(0)->after('status');
                }
                if (! Schema::hasColumn('journal_entries', 'created_by')) {
                    $table->foreignUuid('created_by')->nullable()->after('current_level')->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('journal_entries', 'reject_reason')) {
                    $table->string('reject_reason')->nullable()->after('created_by');
                }
            });
        }

        if (! Schema::hasTable('journal_approvals')) {
            Schema::create('journal_approvals', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('journal_entry_id')->constrained()->cascadeOnDelete();
                $table->unsignedTinyInteger('level');
                $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('action');            // approved|rejected|submitted
                $table->string('notes')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_approvals');

        Schema::table('journal_entries', function (Blueprint $table) {
            foreach (['reject_reason', 'created_by', 'current_level', 'status'] as $col) {
                if (Schema::hasColumn('journal_entries', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
