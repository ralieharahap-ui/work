<?php

namespace App\Agent\Data;

/** Permintaan ke penyedia LLM, bebas dari detail SDK mana pun. */
class LlmRequest
{
    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $metadata  Konteks non-rahasia untuk penyedia heuristik
     */
    public function __construct(
        public readonly string $system,
        public readonly array $messages,
        public readonly string $purpose = 'general', // plan | reflect | reply | classify
        public readonly array $metadata = [],
        public readonly ?int $maxTokens = null,
    ) {
    }

    public static function prompt(string $system, string $user, string $purpose = 'general', array $metadata = []): self
    {
        return new self($system, [['role' => 'user', 'content' => $user]], $purpose, $metadata);
    }

    public function lastUserMessage(): string
    {
        for ($i = count($this->messages) - 1; $i >= 0; $i--) {
            if (($this->messages[$i]['role'] ?? '') === 'user') {
                return (string) $this->messages[$i]['content'];
            }
        }

        return '';
    }
}
