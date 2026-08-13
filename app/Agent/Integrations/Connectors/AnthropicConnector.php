<?php

namespace App\Agent\Integrations\Connectors;

use App\Agent\Integrations\VerificationResult;
use Illuminate\Support\Facades\Http;
use Throwable;

class AnthropicConnector implements IntegrationConnector
{
    public function key(): string
    {
        return 'anthropic';
    }

    public function verify(array $credentials): VerificationResult
    {
        $apiKey = trim((string) ($credentials['api_key'] ?? ''));

        if ($apiKey === '') {
            return VerificationResult::failed('API key belum diisi.');
        }

        $base = rtrim((string) config('agent.llm.providers.anthropic.base_url', 'https://api.anthropic.com'), '/');

        try {
            // Daftar model adalah panggilan paling murah untuk menguji kunci.
            $response = Http::withHeaders([
                    'x-api-key'         => $apiKey,
                    'anthropic-version' => (string) config('agent.llm.providers.anthropic.version', '2023-06-01'),
                ])
                ->timeout(20)
                ->get($base . '/v1/models');
        } catch (Throwable $e) {
            return VerificationResult::failed('Tidak dapat menghubungi Anthropic: ' . $e->getMessage());
        }

        if (! $response->successful()) {
            return VerificationResult::failed(
                'Kunci ditolak (HTTP ' . $response->status() . '): '
                . mb_substr((string) $response->json('error.message', ''), 0, 200)
            );
        }

        return VerificationResult::ok([
            'model'         => (string) config('agent.llm.providers.anthropic.model'),
            'models_listed' => count((array) $response->json('data', [])),
        ]);
    }
}
