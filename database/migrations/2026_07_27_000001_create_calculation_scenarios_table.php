<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calculation_scenarios', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('user_id')->nullable();
            $table->string('name');

            // Parameter input
            $table->decimal('volume', 15, 2)->default(0);
            $table->decimal('price_cangkang', 15, 2)->default(0);
            $table->decimal('transport', 15, 2)->default(0);
            $table->boolean('via_water')->default(false);
            $table->decimal('tongkang', 15, 2)->default(0);
            $table->decimal('handling', 15, 2)->default(0);
            $table->decimal('muat', 15, 2)->default(0);
            $table->decimal('bongkar', 15, 2)->default(0);
            $table->decimal('price_customer', 15, 2)->default(0);
            $table->decimal('target_margin', 5, 2)->default(0);

            // Snapshot hasil
            $table->decimal('total_cost', 18, 2)->default(0);
            $table->decimal('total_revenue', 18, 2)->default(0);
            $table->decimal('total_margin', 18, 2)->default(0);
            $table->decimal('margin_pct', 6, 2)->default(0);

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['organization_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calculation_scenarios');
    }
};
