<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('user_id')->nullable();     // penerima (PIC) — null bila pesan grup murni
            $table->uuid('triggered_by')->nullable(); // null = dikirim oleh penjadwal

            $table->string('channel')->default('personal'); // personal | group
            $table->string('type')->default('digest');      // digest | task | manual
            $table->string('driver')->nullable();
            $table->string('recipient')->nullable();        // nomor / ID grup tujuan

            $table->text('body');
            $table->json('task_ids')->nullable();

            $table->string('status')->default('pending');   // pending | sent | failed | skipped
            $table->text('error')->nullable();
            $table->string('reference')->nullable();        // ID pesan dari gateway
            $table->string('dedupe_key')->nullable();       // penjaga agar tidak dikirim dua kali
            $table->timestamp('sent_at')->nullable();

            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('triggered_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['organization_id', 'created_at']);
            $table->index(['user_id', 'dedupe_key']);
            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_notifications');
    }
};
