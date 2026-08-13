<?php

namespace App\Agent\Integrations;

/** Hasil uji koneksi ke layanan luar. */
class VerificationResult
{
    /** @param array<string, mixed> $meta Info non-rahasia (nama bot, kotak surat, dsb.) */
    private function __construct(
        public readonly bool $ok,
        public readonly array $meta = [],
        public readonly ?string $error = null,
    ) {
    }

    public static function ok(array $meta = []): self
    {
        return new self(true, $meta);
    }

    public static function failed(string $error): self
    {
        return new self(false, [], mb_substr($error, 0, 400));
    }
}
