<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pawm_pltus', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('province');
            $table->string('city');
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->decimal('capacity', 8, 2)->nullable()->comment('MW');
            $table->string('fuel_type')->nullable()->default('biomassa')->comment('Jenis bahan bakar');
            $table->string('operator')->nullable();
            $table->string('status')->default('operational');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['province', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pawm_pltus');
    }
};
