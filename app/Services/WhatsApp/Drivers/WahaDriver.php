<?php

namespace App\Services\WhatsApp\Drivers;

use App\Services\WhatsApp\WhatsAppMessage;
use App\Services\WhatsApp\WhatsAppResult;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Gateway swakelola WAHA — WhatsApp HTTP API (https://github.com/devlikeapro/waha).
 *
 * Sama seperti driver `go_whatsapp`: tersambung ke WhatsApp lewat pemindaian QR
 * memakai nomor perusahaan, jadi pengingat masuk ke chat pribadi tiap PIC tanpa
 * berlangganan penyedia pihak ketiga. Dipilih bila tim sudah menjalankan WAHA
 * (mis. karena integrasi Chatwoot-nya).
 *
 * Kontrak yang dipakai (diverifikasi pada kode WAHA terkini):
 *   POST {base}/api/sendText
 *   Header: X-Api-Key: <kunci>
 *   Body: {"session":"default","chatId":"628…@c.us","text":"…","mentions":["628…@c.us"]}
 *   Sukses: HTTP 2xx dengan objek pesan yang memuat "id".
 *
 * Berbeda dengan go-whatsapp-web-multidevice, WAHA memakai akhiran `@c.us`
 * untuk chat pribadi (bukan `@s.whatsapp.net`). Akhirannya ditambahkan di sini
 * secara eksplisit agar tidak bergantung pada normalisasi sisi server.
 */
class WahaDriver implements WhatsAppDriver
{
    private const USER_SUFFIX = '@c.us';

    public function __construct(private readonly array $config, private readonly int $timeout = 20)
    {
    }

    public function name(): string
    {
        return 'waha';
    }

    public function isConfigured(): bool
    {
        return ! empty($this->config['base_url']);
    }

    public function send(WhatsAppMessage $message): WhatsAppResult
    {
        if (! $this->isConfigured()) {
            return WhatsAppResult::failed('Alamat WAHA belum diatur (WAHA_BASE_URL).');
        }

        $payload = [
            'session'     => $this->config['session'] ?: 'default',
            'chatId'      => $this->chatId($message->to),
            'text'        => $message->body,
            'linkPreview' => false,
        ];

        if ($message->mentions !== []) {
            $payload['mentions'] = array_map(fn (string $n) => $this->chatId($n), $message->mentions);
        }

        $request = Http::timeout($this->timeout)->acceptJson();

        if (! empty($this->config['api_key'])) {
            $request = $request->withHeaders(['X-Api-Key' => $this->config['api_key']]);
        }

        try {
            $response = $request->post($this->endpoint(), $payload);
        } catch (Throwable $e) {
            return WhatsAppResult::failed('Gagal menghubungi WAHA: ' . $e->getMessage());
        }

        if ($response->failed()) {
            $reason = $response->json('message') ?? $response->json('error') ?? $response->body();

            return WhatsAppResult::failed(
                'WAHA HTTP ' . $response->status() . ' — ' . (is_string($reason) ? $reason : json_encode($reason)),
            );
        }

        return WhatsAppResult::sent($this->messageId($response->json()));
    }

    /**
     * ID pesan bentuknya berbeda antar engine WAHA: NOWEB mengembalikan string,
     * WEBJS mengembalikan objek `{"_serialized": "..."}`.
     */
    private function messageId(mixed $body): ?string
    {
        $id = is_array($body) ? ($body['id'] ?? null) : null;

        if (is_array($id)) {
            $id = $id['_serialized'] ?? null;
        }

        return is_scalar($id) ? (string) $id : null;
    }

    /**
     * WAHA menuntut chat ID lengkap: nomor pribadi berakhiran `@c.us`, ID grup
     * berakhiran `@g.us`. Nilai yang sudah memuat "@" diteruskan apa adanya.
     */
    private function chatId(string $target): string
    {
        return str_contains($target, '@') ? $target : $target . self::USER_SUFFIX;
    }

    private function endpoint(): string
    {
        return rtrim((string) $this->config['base_url'], '/') . '/api/sendText';
    }
}
