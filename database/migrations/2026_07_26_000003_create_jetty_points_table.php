<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jetty_points', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->string('name');                 // Nama dermaga
            $table->string('operator')->nullable(); // Pengelola dermaga
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->string('province');
            $table->string('city');
            $table->string('district')->nullable();
            $table->text('address')->nullable();
            $table->decimal('capacity', 10, 2)->nullable();   // kapasitas sandar tongkang (ton)
            $table->string('unit')->default('ton');
            $table->decimal('price', 15, 2)->default(0);       // biaya/harga sandar per satuan (sebelum PPN)
            $table->string('draft')->nullable();               // draft/kedalaman dermaga
            $table->string('pic_name')->nullable();
            $table->string('pic_phone')->nullable();
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jetty_points');
    }
};
