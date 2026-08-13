<?php

namespace App\Services\WhatsApp\Drivers;

use App\Services\WhatsApp\WhatsAppMessage;
use App\Services\WhatsApp\WhatsAppResult;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Gateway Fonnte (https://fonnte.com) — populer untuk nomor WhatsApp Indonesia.
 *
 * Mention di grup: teks memuat token "@62812…", dan daftar nomor yang sama
 * dikirim lewat parameter `mention` agar WhatsApp menandai orangnya sungguhan.
 */
class FonnteDriver implements WhatsAppDriver
{
    public function __construct(private readonly array $config, private readonly int $timeout = 20)
    {
    }

    public function name(): string
    {
        return 'fonnte';
    }

    public function isConfigured(): bool
    {
        return ! empty($this->config['token']) && ! empty($this->config['endpoint']);
    }

    public function send(WhatsAppMessage $message): WhatsAppResult
    {
        if (! $this->isConfigured()) {
            return WhatsAppResult::failed('Token Fonnte belum diatur (FONNTE_TOKEN).');
        }

        $payload = [
            'target'      => $message->to,
            'message'     => $message->body,
            'countryCode' => '',
        ];

        if ($message->isGroup && $message->mentions !== []) {
            $payload['mention'] = implode(',', $message->mentions);
        }

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders(['Authorization' => $this->config['token']])
                ->asForm()
                ->post($this->config['endpoint'], $payload);
        } catch (Throwable $e) {
            return WhatsAppResult::failed('Gagal menghubungi Fonnte: ' . $e->getMessage());
        }

        if ($response->failed()) {
            return WhatsAppResult::failed('Fonnte HTTP ' . $response->status() . ' — ' . $response->body());
        }

        $body = $response->json();

        // Fonnte tetap membalas 200 saat gagal, statusnya ada di body.
        if (is_array($body) && array_key_exists('status', $body) && $body['status'] === false) {
            return WhatsAppResult::failed((string) ($body['reason'] ?? 'Fonnte menolak pesan.'));
        }

        $reference = is_array($body) ? ($body['id'][0] ?? $body['id'] ?? null) : null;

        return WhatsAppResult::sent(is_scalar($reference) ? (string) $reference : null);
    }
}
