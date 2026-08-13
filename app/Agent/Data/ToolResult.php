<?php

namespace App\Agent\Data;

/**
 * Hasil satu pemanggilan tool. `data` memuat petunjuk pemulihan bila gagal —
 * misalnya daftar kolom yang tersedia ketika kolom yang diminta tidak ada —
 * sehingga agent dapat memperbaiki diri alih-alih mengulang secara buta.
 */
class ToolResult
{
    /**
     * @param  array<string, mixed>  $output
     * @param  array<string, mixed>  $data
     * @param  array<int, array{path: string, name: string, mime?: string}>  $artifacts
     */
    private function __construct(
        public readonly bool $ok,
        public readonly array $output = [],
        public readonly ?string $error = null,
        public readonly ?string $errorClass = null,
        public readonly array $data = [],
        public readonly string $observation = '',
        public readonly array $artifacts = [],
    ) {
    }

    /** @param array<string, mixed> $output */
    public static function success(array $output, string $observation = '', array $artifacts = []): self
    {
        return new self(true, $output, null, null, [], $observation, $artifacts);
    }

    /** @param array<string, mixed> $data */
    public static function failure(string $error, string $errorClass = 'unknown', array $data = []): self
    {
        return new self(false, [], mb_substr($error, 0, 800), $errorClass, $data, mb_substr($error, 0, 400));
    }
}
