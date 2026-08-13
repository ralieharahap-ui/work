<?php

namespace App\Agent\Llm\Providers;

use App\Agent\Contracts\LlmProvider;
use App\Agent\Data\LlmRequest;
use App\Agent\Data\LlmResponse;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Penyedia Claude (Messages API). Penalaran internal model tidak pernah
 * disimpan maupun ditampilkan — hanya teks jawaban dan ringkasan terstruktur
 * yang dipakai agent, sesuai aturan "jangan expose chain-of-thought".
 */
class AnthropicProvider implements LlmProvider
{
    /** @param array<string, mixed> $config */
    public function __construct(
        private readonly array $config,
        private readonly int $timeout = 60,
    ) {
    }

    public function name(): string
    {
        return 'anthropic';
    }

    public function isConfigured(): bool
    {
        return ! empty($this->config['api_key']);
    }

    public function generate(LlmRequest $request): LlmResponse
    {
        return $this->send($this->body($request));
    }

    public function structured(LlmRequest $request, array $schema): array
    {
        $body = $this->body($request);
        $body['system'] .= "\n\nJawab HANYA dengan satu objek JSON valid sesuai skema berikut, tanpa teks lain:\n"
            . json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $response = $this->send($body);
        $decoded  = $this->extractJson($response->text);

        if ($decoded === null) {
            throw new RuntimeException('Jawaban model bukan JSON yang dapat dibaca.');
        }

        return $decoded;
    }

    public function stream(LlmRequest $request, callable $onChunk): LlmResponse
    {
        // Aliran token belum dibutuhkan runtime; kirim sekaligus agar kontrak
        // tetap terpenuhi tanpa menambah kompleksitas transport.
        $response = $this->generate($request);
        $onChunk($response->text);

        return $response;
    }

    /** @return array<string, mixed> */
    private function body(LlmRequest $request): array
    {
        $body = [
            'model'      => (string) ($this->config['model'] ?? 'claude-opus-5'),
            'max_tokens' => $request->maxTokens ?? (int) ($this->config['max_tokens'] ?? 4096),
            'system'     => $request->system,
            'messages'   => array_map(static fn (array $m) => [
                'role'    => $m['role'] === 'assistant' ? 'assistant' : 'user',
                'content' => (string) $m['content'],
            ], $request->messages),
        ];

        if (! empty($this->config['thinking'])) {
            $body['thinking'] = ['type' => 'adaptive'];
        }

        if (! empty($this->config['effort'])) {
            $body['output_config'] = ['effort' => (string) $this->config['effort']];
        }

        return $body;
    }

    /** @param array<string, mixed> $body */
    private function send(array $body): LlmResponse
    {
        $response = Http::withHeaders([
                'x-api-key'         => (string) $this->config['api_key'],
                'anthropic-version' => (string) ($this->config['version'] ?? '2023-06-01'),
                'content-type'      => 'application/json',
            ])
            ->timeout($this->timeout)
            ->post(rtrim((string) ($this->config['base_url'] ?? 'https://api.anthropic.com'), '/') . '/v1/messages', $body);

        if ($response->failed()) {
            throw new RuntimeException(
                'Anthropic API gagal (HTTP ' . $response->status() . '): '
                . mb_substr((string) $response->json('error.message', $response->body()), 0, 300)
            );
        }

        // Hanya blok bertipe "text" yang diambil; blok penalaran diabaikan.
        $text = collect((array) $response->json('content', []))
            ->filter(fn ($block) => ($block['type'] ?? '') === 'text')
            ->map(fn ($block) => (string) ($block['text'] ?? ''))
            ->implode("\n");

        return new LlmResponse(
            text: trim($text),
            provider: $this->name(),
            model: (string) $response->json('model'),
            inputTokens: (int) $response->json('usage.input_tokens', 0),
            outputTokens: (int) $response->json('usage.output_tokens', 0),
            stopReason: $response->json('stop_reason'),
        );
    }

    /** @return array<string, mixed>|null */
    private function extractJson(string $text): ?array
    {
        $text = trim($text);

        // Model kadang membungkus JSON dalam pagar kode markdown.
        if (str_starts_with($text, '```')) {
            $text = trim(preg_replace('/^```[a-z]*\s*|\s*```$/i', '', $text) ?? $text);
        }

        $decoded = json_decode($text, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        $start = strpos($text, '{');
        $end   = strrpos($text, '}');

        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        $decoded = json_decode(substr($text, $start, $end - $start + 1), true);

        return is_array($decoded) ? $decoded : null;
    }
}
