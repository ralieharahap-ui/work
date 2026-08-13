<?php

namespace App\Services\WhatsApp\Drivers;

use App\Services\WhatsApp\WhatsAppMessage;
use App\Services\WhatsApp\WhatsAppResult;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Driver bawaan: pesan tidak benar-benar dikirim, hanya ditulis ke log aplikasi.
 * Berguna untuk menguji format pesan sebelum menyambungkan gateway sungguhan.
 */
class LogDriver implements WhatsAppDriver
{
    public function name(): string
    {
        return 'log';
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function send(WhatsAppMessage $message): WhatsAppResult
    {
        Log::channel(config('logging.default'))->info('[WhatsApp] pesan pengingat tugas', [
            'to'       => $message->to,
            'is_group' => $message->isGroup,
            'mentions' => $message->mentions,
            'body'     => $message->body,
        ]);

        return WhatsAppResult::sent('log-' . Str::uuid());
    }
}
