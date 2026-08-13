<?php

namespace App\Services\WhatsApp\Drivers;

use App\Services\WhatsApp\WhatsAppMessage;
use App\Services\WhatsApp\WhatsAppResult;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Driver generik: mengirim JSON ke URL milik sendiri (mis. bot Baileys atau
 * whatsapp-web.js yang dijalankan terpisah). Bentuk payload:
 *
 *   { "to": "6281…", "isGroup": false, "mentions": ["6281…"], "message": "…" }
 *
 * Bila WHATSAPP_WEBHOOK_SECRET diisi, nilainya dikirim sebagai header
 * X-Webhook-Secret untuk diverifikasi di sisi penerima.
 */
class WebhookDriver implements WhatsAppDriver
{
    public function __construct(private readonly array $config, private readonly int $timeout = 20)
    {
    }

    public function name(): string
    {
        return 'webhook';
    }

    public function isConfigured(): bool
    {
        return ! empty($this->config['url']);
    }

    public function send(WhatsAppMessage $message): WhatsAppResult
    {
        if (! $this->isConfigured()) {
            return WhatsAppResult::failed('URL webhook belum diatur (WHATSAPP_WEBHOOK_URL).');
        }

        $headers = ['Accept' => 'application/json'];

        if (! empty($this->config['secret'])) {
            $headers['X-Webhook-Secret'] = $this->config['secret'];
        }

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders($headers)
                ->asJson()
                ->post($this->config['url'], [
                    'to'       => $message->to,
                    'isGroup'  => $message->isGroup,
                    'mentions' => $message->mentions,
                    'message'  => $message->body,
                ]);
        } catch (Throwable $e) {
            return WhatsAppResult::failed('Gagal menghubungi webhook: ' . $e->getMessage());
        }

        if ($response->failed()) {
            return WhatsAppResult::failed('Webhook HTTP ' . $response->status() . ' — ' . $response->body());
        }

        $reference = $response->json('id') ?? $response->json('messageId');

        return WhatsAppResult::sent(is_scalar($reference) ? (string) $reference : null);
    }
}
