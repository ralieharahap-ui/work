<?php

namespace App\Services\WhatsApp\Drivers;

use App\Services\WhatsApp\WhatsAppMessage;
use App\Services\WhatsApp\WhatsAppResult;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * WhatsApp Business Cloud API resmi dari Meta.
 *
 * Catatan penting: pesan teks bebas hanya bisa dikirim bila pengguna sedang
 * dalam jendela percakapan 24 jam. Di luar itu Meta mewajibkan message
 * template yang sudah disetujui, dan API akan membalas error 131047 —
 * pesan errornya diteruskan apa adanya ke riwayat notifikasi.
 *
 * Cloud API juga tidak mengenal pengiriman ke grup, jadi salinan grup
 * dilewati saat driver ini dipakai.
 */
class CloudApiDriver implements WhatsAppDriver
{
    public function __construct(private readonly array $config, private readonly int $timeout = 20)
    {
    }

    public function name(): string
    {
        return 'cloud_api';
    }

    public function isConfigured(): bool
    {
        return ! empty($this->config['token']) && ! empty($this->config['phone_number_id']);
    }

    public function send(WhatsAppMessage $message): WhatsAppResult
    {
        if (! $this->isConfigured()) {
            return WhatsAppResult::failed('Kredensial Cloud API belum lengkap (WHATSAPP_CLOUD_TOKEN / WHATSAPP_CLOUD_PHONE_NUMBER_ID).');
        }

        if ($message->isGroup) {
            return WhatsAppResult::skipped('WhatsApp Cloud API tidak mendukung pengiriman ke grup.');
        }

        $version  = $this->config['version'] ?: 'v20.0';
        $endpoint = "https://graph.facebook.com/{$version}/{$this->config['phone_number_id']}/messages";

        try {
            $response = Http::timeout($this->timeout)
                ->withToken($this->config['token'])
                ->post($endpoint, [
                    'messaging_product' => 'whatsapp',
                    'recipient_type'    => 'individual',
                    'to'                => $message->to,
                    'type'              => 'text',
                    'text'              => ['preview_url' => false, 'body' => $message->body],
                ]);
        } catch (Throwable $e) {
            return WhatsAppResult::failed('Gagal menghubungi Cloud API: ' . $e->getMessage());
        }

        if ($response->failed()) {
            $error = $response->json('error.message') ?? $response->body();

            return WhatsAppResult::failed('Cloud API HTTP ' . $response->status() . ' — ' . $error);
        }

        return WhatsAppResult::sent($response->json('messages.0.id'));
    }
}
