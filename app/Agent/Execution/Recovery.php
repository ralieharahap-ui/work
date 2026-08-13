<?php

namespace App\Agent\Execution;

/** Rencana pemulihan setelah satu langkah gagal. */
class Recovery
{
    /**
     * @param  array<string, mixed>  $inputs      Input pengganti untuk percobaan berikutnya
     * @param  array<string, mixed>|null  $lesson  Pelajaran yang layak disimpan bila pemulihan berhasil
     */
    private function __construct(
        public readonly string $strategy,   // retry | adjust_inputs | request_access | replan | escalate | abort
        public readonly string $message,
        public readonly array $inputs = [],
        public readonly ?array $lesson = null,
        public readonly ?string $blockedOn = null,
        /** @var array<int, string> Akses lain yang sama-sama dapat membuka jalan */
        public readonly array $alternatives = [],
    ) {
    }

    public static function retry(string $message): self
    {
        return new self('retry', $message);
    }

    /** @param array<string, mixed> $inputs */
    public static function adjustInputs(string $message, array $inputs, ?array $lesson = null): self
    {
        return new self('adjust_inputs', $message, $inputs, $lesson);
    }

    /** @param array<int, string> $alternatives */
    public static function requestAccess(string $message, string $integration, array $alternatives = []): self
    {
        return new self('request_access', $message, [], null, $integration, $alternatives);
    }

    public static function replan(string $message): self
    {
        return new self('replan', $message);
    }

    public static function escalate(string $message): self
    {
        return new self('escalate', $message);
    }

    /** Berhenti dan minta manusia memeriksa — tanpa mengulang tindakannya. */
    public static function humanReview(string $message): self
    {
        return new self('human_review', $message);
    }

    public static function abort(string $message): self
    {
        return new self('abort', $message);
    }
}
