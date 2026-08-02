<?php

namespace App\Services\WhatsApp\Drivers;

use App\Services\WhatsApp\WhatsAppMessage;
use App\Services\WhatsApp\WhatsAppResult;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Gateway swakelola berbasis proyek go-whatsapp-web-multidevice
 * (https://github.com/aldinokemal/go-whatsapp-web-multidevice).
 *
 * Berjalan sebagai layanan terpisah yang tersambung ke WhatsApp lewat
 * pemindaian QR — nomor pengirimnya nomor WhatsApp biasa milik perusahaan,
 * tanpa berlangganan penyedia pihak ketiga dan tanpa batasan jendela 24 jam
 * seperti Cloud API resmi.
 *
 * Kontrak yang dipakai (diverifikasi pada rilis v9):
 *   POST {base}/send/message
 *   Auth: HTTP Basic (APP_BASIC_AUTH=user:pass di sisi gateway)
 *   Body: {"phone": "...", "message": "...", "mentions": ["..."]}
 *   Sukses: HTTP 200 {"code":"SUCCESS","results":{"message_id":"..."}}
 *
 * Nomor tujuan cukup ditulis sebagai digit; gateway menambahkan sendiri akhiran
 * `@s.whatsapp.net` (chat pribadi) atau `@g.us` (grup) — ID grup yang sudah
 * memuat "@" diteruskan apa adanya.
 */
class GoWhatsAppDriver implements WhatsAppDriver
{
    public function __construct(private readonly array $config, private readonly int $timeout = 20)
    {
    }

    public function name(): string
    {
        return 'go_whatsapp';
    }

    public function isConfigured(): bool
    {
        return ! empty($this->config['base_url']);
    }

    public function send(WhatsAppMessage $message): WhatsAppResult
    {
        if (! $this->isConfigured()) {
            return WhatsAppResult::failed('Alamat gateway belum diatur (GOWA_BASE_URL).');
        }

        $payload = [
            'phone'   => $message->to,
            'message' => $message->body,
        ];

        // "Ghost mention": penerima ditandai walaupun teksnya tidak memuat @nomor.
        if ($message->mentions !== []) {
            $payload['mentions'] = array_values($message->mentions);
        }

        $request = Http::timeout($this->timeout)->acceptJson();

        if (! empty($this->config['username'])) {
            $request = $request->withBasicAuth($this->config['username'], (string) ($this->config['password'] ?? ''));
        }

        // Hanya dibutuhkan bila gateway menjalankan lebih dari satu perangkat.
        if (! empty($this->config['device_id'])) {
            $request = $request->withHeaders(['X-Device-Id' => $this->config['device_id']]);
        }

        try {
            $response = $request->post($this->endpoint(), $payload);
        } catch (Throwable $e) {
            return WhatsAppResult::failed('Gagal menghubungi gateway WhatsApp: ' . $e->getMessage());
        }

        if ($response->failed()) {
            $reason = $response->json('message') ?: $response->body();

            return WhatsAppResult::failed('Gateway HTTP ' . $response->status() . ' — ' . $reason);
        }

        $code = $response->json('code');

        if ($code !== null && $code !== 'SUCCESS') {
            return WhatsAppResult::failed('Gateway menolak pesan (' . $code . '): ' . (string) $response->json('message'));
        }

        return WhatsAppResult::sent($response->json('results.message_id'));
    }

    private function endpoint(): string
    {
        return rtrim((string) $this->config['base_url'], '/') . '/send/message';
    }
}
