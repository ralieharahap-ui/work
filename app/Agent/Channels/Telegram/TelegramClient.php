<?php

namespace App\Agent\Channels\Telegram;

use App\Agent\Integrations\IntegrationManager;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Klien Bot API Telegram. Token diambil dari akses yang diberikan user
 * (terenkripsi di basis data) atau dari .env sebagai cadangan.
 */
class TelegramClient
{
    /** Batas panjang satu pesan Telegram. */
    private const MAX_LENGTH = 4000;

    public function __construct(private readonly IntegrationManager $integrations)
    {
    }

    public function tokenFor(?string $organizationId): ?string
    {
        if ($organizationId) {
            $token = $this->integrations->credentials($organizationId, 'telegram')['bot_token'] ?? null;

            if ($token) {
                return (string) $token;
            }
        }

        $fallback = config('agent.telegram.bot_token');

        return $fallback ? (string) $fallback : null;
    }

    public function isReady(?string $organizationId): bool
    {
        return (bool) config('agent.telegram.enabled', true) && $this->tokenFor($organizationId) !== null;
    }

    /** @param array<string, mixed> $options */
    public function sendMessage(?string $organizationId, string|int $chatId, string $text, array $options = []): bool
    {
        $token = $this->tokenFor($organizationId);

        if (! $token) {
            Log::warning('agent.telegram.no_token', ['organization_id' => $organizationId]);

            return false;
        }

        $sent = true;

        // Pesan panjang dipecah agar tidak ditolak Telegram.
        foreach ($this->chunk($text) as $part) {
            $response = $this->call($token, 'sendMessage', array_merge([
                'chat_id'                  => $chatId,
                'text'                     => $part,
                'disable_web_page_preview' => true,
            ], $options));

            $sent = $sent && $response !== null && (bool) $response->json('ok');
        }

        return $sent;
    }

    /** @return array<int, array<string, mixed>> */
    public function getUpdates(?string $organizationId, int $offset = 0, int $timeout = 25): array
    {
        $token = $this->tokenFor($organizationId);

        if (! $token) {
            return [];
        }

        $response = $this->call($token, 'getUpdates', [
            'offset'  => $offset,
            'timeout' => $timeout,
        ], $timeout + 10);

        return (array) ($response?->json('result') ?? []);
    }

    public function setWebhook(?string $organizationId, string $url, ?string $secret = null): bool
    {
        $token = $this->tokenFor($organizationId);

        if (! $token) {
            return false;
        }

        $response = $this->call($token, 'setWebhook', array_filter([
            'url'             => $url,
            'secret_token'    => $secret,
            'allowed_updates' => json_encode(['message', 'callback_query']),
        ]));

        return $response !== null && (bool) $response->json('ok');
    }

    public function deleteWebhook(?string $organizationId): bool
    {
        $token = $this->tokenFor($organizationId);

        if (! $token) {
            return false;
        }

        $response = $this->call($token, 'deleteWebhook', []);

        return $response !== null && (bool) $response->json('ok');
    }

    /** @param array<string, mixed> $payload */
    private function call(string $token, string $method, array $payload, ?int $timeout = null): ?Response
    {
        $url = rtrim((string) config('agent.telegram.api_url'), '/') . '/bot' . $token . '/' . $method;

        try {
            $response = Http::timeout($timeout ?? (int) config('agent.telegram.timeout', 20))->post($url, $payload);
        } catch (Throwable $e) {
            Log::warning('agent.telegram.failed', ['method' => $method, 'error' => $e->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('agent.telegram.rejected', [
                'method' => $method,
                'status' => $response->status(),
                'error'  => Str::limit((string) $response->json('description'), 200),
            ]);
        }

        return $response;
    }

    /** @return array<int, string> */
    private function chunk(string $text): array
    {
        if (mb_strlen($text) <= self::MAX_LENGTH) {
            return [$text];
        }

        $parts   = [];
        $current = '';

        foreach (explode("\n", $text) as $line) {
            if (mb_strlen($current) + mb_strlen($line) + 1 > self::MAX_LENGTH) {
                $parts[] = $current;
                $current = '';
            }

            $current .= ($current === '' ? '' : "\n") . mb_substr($line, 0, self::MAX_LENGTH);
        }

        if ($current !== '') {
            $parts[] = $current;
        }

        return $parts;
    }
}
