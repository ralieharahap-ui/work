<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Daftar Aset (poin 1.9 spec) — penyusutan garis lurus bulanan.
 * Kolom mengikuti contoh tampilan: KETERANGAN, TGL BELI, BULAN/THN PENYUSUTAN,
 * QTY, HRG PEROLEHAN, TOTAL PEROLEHAN, NILAI SISA, UE (BLN).
 * Nilai penyusutan/bulan, akumulasi, dan nilai buku dihitung di aplikasi
 * (bukan disimpan) agar selalu konsisten terhadap periode berjalan.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fixed_assets')) {
            return;
        }

        Schema::create('fixed_assets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->string('description');                 // KETERANGAN
            $table->date('purchase_date');                 // TGL BELI (mulai penyusutan)
            $table->integer('qty')->default(1);            // QTY
            $table->decimal('unit_cost', 18, 2)->default(0);   // HRG PEROLEHAN (per unit)
            $table->decimal('residual_value', 18, 2)->default(0); // NILAI SISA
            $table->integer('useful_life_months')->default(0); // UE (BLN)
            $table->uuid('account_id')->nullable();        // akun aset tetap terkait (opsional)
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('account_id')->references('id')->on('accounts')->nullOnDelete();
            $table->index(['organization_id', 'purchase_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_assets');
    }
};
