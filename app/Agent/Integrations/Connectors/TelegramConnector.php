<?php

namespace App\Agent\Integrations\Connectors;

use App\Agent\Integrations\VerificationResult;
use Illuminate\Support\Facades\Http;
use Throwable;

class TelegramConnector implements IntegrationConnector
{
    public function key(): string
    {
        return 'telegram';
    }

    public function verify(array $credentials): VerificationResult
    {
        $token = trim((string) ($credentials['bot_token'] ?? ''));

        if ($token === '') {
            return VerificationResult::failed('Token bot belum diisi.');
        }

        try {
            $response = Http::timeout((int) config('agent.telegram.timeout', 20))
                ->get(rtrim((string) config('agent.telegram.api_url'), '/') . '/bot' . $token . '/getMe');
        } catch (Throwable $e) {
            return VerificationResult::failed('Tidak dapat menghubungi Telegram: ' . $e->getMessage());
        }

        if (! $response->successful() || ! $response->json('ok')) {
            return VerificationResult::failed(
                'Telegram menolak token: ' . (string) $response->json('description', 'HTTP ' . $response->status())
            );
        }

        return VerificationResult::ok([
            'bot_username' => $response->json('result.username'),
            'bot_name'     => $response->json('result.first_name'),
            'bot_id'       => $response->json('result.id'),
        ]);
    }
}
