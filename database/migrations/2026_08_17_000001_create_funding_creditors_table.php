<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('funding_creditors')) {
            return;
        }

        Schema::create('funding_creditors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('code');                // kode bantu kreditur (mis. INV-001, BANK-001)
            $table->string('name');
            $table->string('category');            // investor | bank
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->string('account_no')->nullable();       // no. rekening (khusus bank)
            $table->decimal('interest_rate', 6, 2)->nullable(); // bunga % per tahun
            $table->decimal('loan_ceiling', 18, 2)->nullable(); // plafon pinjaman
            $table->date('maturity_date')->nullable();          // jatuh tempo
            $table->decimal('payable_balance', 18, 2)->default(0); // saldo kewajiban awal
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('funding_creditors');
    }
};
