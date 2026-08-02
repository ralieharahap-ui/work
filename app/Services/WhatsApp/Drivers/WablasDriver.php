<?php

namespace App\Services\WhatsApp\Drivers;

use App\Services\WhatsApp\WhatsAppMessage;
use App\Services\WhatsApp\WhatsAppResult;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Gateway Wablas (https://wablas.com). Otorisasi memakai "token.secret"
 * pada header Authorization sesuai dokumentasi API v2 mereka.
 */
class WablasDriver implements WhatsAppDriver
{
    public function __construct(private readonly array $config, private readonly int $timeout = 20)
    {
    }

    public function name(): string
    {
        return 'wablas';
    }

    public function isConfigured(): bool
    {
        return ! empty($this->config['token']) && ! empty($this->config['endpoint']);
    }

    public function send(WhatsAppMessage $message): WhatsAppResult
    {
        if (! $this->isConfigured()) {
            return WhatsAppResult::failed('Token Wablas belum diatur (WABLAS_TOKEN).');
        }

        $authorization = $this->config['token'] . (empty($this->config['secret']) ? '' : '.' . $this->config['secret']);

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders(['Authorization' => $authorization])
                ->asForm()
                ->post($this->config['endpoint'], [
                    'phone'   => $message->to,
                    'message' => $message->body,
                ]);
        } catch (Throwable $e) {
            return WhatsAppResult::failed('Gagal menghubungi Wablas: ' . $e->getMessage());
        }

        if ($response->failed()) {
            return WhatsAppResult::failed('Wablas HTTP ' . $response->status() . ' — ' . $response->body());
        }

        $body = $response->json();

        if (is_array($body) && array_key_exists('status', $body) && $body['status'] === false) {
            return WhatsAppResult::failed((string) ($body['message'] ?? 'Wablas menolak pesan.'));
        }

        $reference = is_array($body) ? ($body['data']['messages'][0]['id'] ?? null) : null;

        return WhatsAppResult::sent(is_scalar($reference) ? (string) $reference : null);
    }
}
