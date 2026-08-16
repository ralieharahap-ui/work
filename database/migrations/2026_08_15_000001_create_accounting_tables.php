<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fondasi modul akuntansi (buku besar berbasis jurnal).
 * Aman dijalankan pada DB kosong maupun DB yang sudah punya tabel ini:
 * setiap Schema::create dibungkus pengecekan Schema::hasTable sehingga
 * tidak menimpa / menghapus data yang sudah ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('accounts')) {
            Schema::create('accounts', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('organization_id');
                $table->string('code');                       // KODE Akun, format X-XXXX
                $table->string('name');                       // NAMA AKUN
                $table->string('type');                       // TYPE Akun (asset/liability/equity/revenue/expense)
                $table->uuid('parent_id')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
                $table->foreign('parent_id')->references('id')->on('accounts')->nullOnDelete();
                $table->unique(['organization_id', 'code']);
                $table->index(['organization_id', 'type']);
            });
        }

        if (! Schema::hasTable('journal_entries')) {
            Schema::create('journal_entries', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('organization_id');
                $table->string('entry_no');                   // JE-YYYYMM-0001
                $table->date('entry_date');
                $table->string('description');
                $table->string('ref_type')->nullable();       // invoice/payment/ssp/manual/dst
                $table->uuid('ref_id')->nullable();
                $table->boolean('is_posted')->default(false);
                $table->timestamps();

                $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
                $table->unique(['organization_id', 'entry_no']);
                $table->index(['organization_id', 'entry_date']);
                $table->index(['organization_id', 'is_posted']);
            });
        }

        if (! Schema::hasTable('journal_lines')) {
            Schema::create('journal_lines', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('journal_entry_id');
                $table->uuid('account_id');
                $table->decimal('debit', 18, 2)->default(0);
                $table->decimal('credit', 18, 2)->default(0);
                $table->string('memo')->nullable();
                // Sengaja tanpa timestamps() — mengikuti Model JournalLine ($timestamps = false)

                $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->cascadeOnDelete();
                $table->foreign('account_id')->references('id')->on('accounts')->cascadeOnDelete();
                $table->index('journal_entry_id');
                $table->index('account_id');
            });
        }
    }

    public function down(): void
    {
        // Urutan drop: child dulu, baru parent (hormati foreign key).
        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('accounts');
    }
};
