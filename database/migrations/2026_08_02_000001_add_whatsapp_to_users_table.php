<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Nomor khusus WhatsApp — dipisah dari `phone` karena nomor kantor
            // sering berbeda dengan nomor WhatsApp pribadi PIC.
            $table->string('whatsapp_number')->nullable()->after('phone');
            $table->boolean('whatsapp_opt_in')->default(true)->after('whatsapp_number');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['whatsapp_number', 'whatsapp_opt_in']);
        });
    }
};
