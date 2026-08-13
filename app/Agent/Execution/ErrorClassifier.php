<?php

namespace App\Agent\Execution;

use App\Agent\Data\ToolResult;

/**
 * Menggolongkan kegagalan agar pemulihan bisa spesifik — bukan sekadar
 * "coba lagi". Golongan inilah yang menentukan strategi pemulihan dan menjadi
 * bahan pelajaran yang disimpan ke memori.
 */
class ErrorClassifier
{
    public const RETRYABLE = ['network', 'timeout', 'rate_limited', 'provider_error'];

    public function classify(ToolResult $result): string
    {
        if ($result->errorClass && $result->errorClass !== 'unknown') {
            return $result->errorClass;
        }

        $message = mb_strtolower((string) $result->error);

        return match (true) {
            str_contains($message, 'timeout') || str_contains($message, 'timed out') => 'timeout',
            str_contains($message, 'kolom') || str_contains($message, 'column')       => 'missing_column',
            str_contains($message, 'tidak ditemukan') || str_contains($message, 'not found') => 'not_found',
            str_contains($message, 'izin') || str_contains($message, 'permission') || str_contains($message, 'forbidden') => 'permission',
            str_contains($message, 'akses') || str_contains($message, 'kredensial')   => 'missing_integration',
            str_contains($message, 'wajib diisi') || str_contains($message, 'tidak sah') => 'validation',
            str_contains($message, 'koneksi') || str_contains($message, 'network') || str_contains($message, 'curl') => 'network',
            default => 'unknown',
        };
    }

    public function isRetryable(string $class): bool
    {
        return in_array($class, self::RETRYABLE, true);
    }
}
