<?php

namespace App\Agent\Data;

/** Jawaban penyedia LLM beserta metadata penggunaan. */
class LlmResponse
{
    /** @param array<string, mixed> $structured */
    public function __construct(
        public readonly string $text,
        public readonly string $provider,
        public readonly ?string $model = null,
        public readonly array $structured = [],
        public readonly int $inputTokens = 0,
        public readonly int $outputTokens = 0,
        public readonly ?string $stopReason = null,
    ) {
    }
}
