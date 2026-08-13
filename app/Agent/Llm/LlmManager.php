<?php

namespace App\Agent\Llm;

use App\Agent\Contracts\LlmProvider;
use App\Agent\Llm\Providers\AnthropicProvider;
use App\Agent\Llm\Providers\ScriptedProvider;
use App\Models\AgentIntegration;
use InvalidArgumentException;

/**
 * Pemilih penyedia LLM. Bila penyedia utama belum berkredensial, sistem jatuh
 * ke penyedia 'scripted' yang deterministik supaya agent tetap dapat bekerja
 * (merencanakan, mengeksekusi, memverifikasi) tanpa layanan luar.
 */
class LlmManager
{
    /** @var array<string, LlmProvider> */
    private array $resolved = [];

    public function provider(?string $name = null): LlmProvider
    {
        $name ??= (string) config('agent.llm.provider', 'scripted');

        $provider = $this->resolved[$name] ??= $this->make($name);

        if (! $provider->isConfigured()) {
            $fallback = (string) config('agent.llm.fallback', 'scripted');

            return $this->resolved[$fallback] ??= $this->make($fallback);
        }

        return $provider;
    }

    /** Nama penyedia yang benar-benar akan dipakai saat ini. */
    public function activeName(): string
    {
        return $this->provider()->name();
    }

    private function make(string $name): LlmProvider
    {
        $config = (array) config("agent.llm.providers.{$name}", []);

        return match ($name) {
            'scripted'  => new ScriptedProvider(),
            'anthropic' => new AnthropicProvider(
                $this->withStoredCredentials('anthropic', $config),
                (int) config('agent.llm.timeout', 60),
            ),
            default => throw new InvalidArgumentException("Penyedia LLM '{$name}' tidak dikenal."),
        };
    }

    /**
     * Kunci API boleh berasal dari .env atau dari akses yang diberikan user
     * lewat layar onboarding (tersimpan terenkripsi di agent_integrations).
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function withStoredCredentials(string $key, array $config): array
    {
        if (! empty($config['api_key'])) {
            return $config;
        }

        try {
            $integration = AgentIntegration::where('key', $key)->where('status', 'connected')->first();
        } catch (\Throwable) {
            return $config; // basis data belum siap (mis. saat migrasi)
        }

        if ($integration && $apiKey = $integration->credential('api_key')) {
            $config['api_key'] = $apiKey;
        }

        return $config;
    }
}
