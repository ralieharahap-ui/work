<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Akses tool eksternal yang diminta agent saat awal dijalankan (Telegram,
 * email, kalender, Microsoft 365) serta tautan percakapan chat ke akun user.
 *
 * Kredensial disimpan terenkripsi dan tidak pernah masuk ke memori pengalaman.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_integrations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->string('key');                     // telegram | anthropic | microsoft365 | ...
            $table->string('status')->default('not_configured'); // not_configured | connected | error | denied
            $table->text('credentials')->nullable();   // terenkripsi (Crypt), tidak pernah dikirim ke klien
            $table->json('meta')->nullable();          // info non-rahasia hasil verifikasi
            $table->json('scopes')->nullable();
            $table->uuid('granted_by')->nullable();
            $table->timestamp('granted_at')->nullable();
            $table->timestamp('last_verified_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('granted_by')->references('id')->on('users')->nullOnDelete();
            $table->unique(['organization_id', 'key']);
        });

        Schema::create('agent_conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->nullable();
            $table->uuid('user_id')->nullable();
            $table->string('channel')->default('telegram');
            $table->string('chat_id');
            $table->string('chat_type')->nullable();
            $table->string('username')->nullable();
            $table->string('display_name')->nullable();
            $table->boolean('is_linked')->default(false);
            $table->string('link_code')->nullable();
            $table->timestamp('link_expires_at')->nullable();
            $table->json('state')->nullable();          // percakapan bertahap (mis. menunggu jawaban)
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->nullOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->unique(['channel', 'chat_id']);
            $table->index('link_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_conversations');
        Schema::dropIfExists('agent_integrations');
    }
};
