<?php

namespace App\Services\WhatsApp\Drivers;

use App\Services\WhatsApp\WhatsAppMessage;
use App\Services\WhatsApp\WhatsAppResult;

interface WhatsAppDriver
{
    /** Nama pendek driver, dipakai di riwayat notifikasi. */
    public function name(): string;

    /** True bila kredensial yang dibutuhkan driver ini sudah lengkap. */
    public function isConfigured(): bool;

    public function send(WhatsAppMessage $message): WhatsAppResult;
}
