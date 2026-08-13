<?php

namespace App\Agent\Memory;

/**
 * Penyaring data sensitif.
 *
 * Aturan main: log eksekusi mentah boleh memuat detail operasional, tetapi
 * kredensial tidak boleh masuk ke mana pun, dan memori jangka panjang juga
 * dibersihkan dari data pribadi (email, nomor telepon) karena akan dipakai
 * ulang lintas task dan lintas orang.
 */
class Redactor
{
    private const SECRET_KEYS = [
        'token', 'secret', 'password', 'api_key', 'apikey', 'authorization', 'auth',
        'credential', 'credentials', 'private_key', 'refresh_token', 'access_token',
        'bot_token', 'client_secret', 'signature', 'session',
    ];

    public const MASK = '[dirahasiakan]';

    /**
     * Redaksi untuk log eksekusi: hanya kredensial yang disamarkan.
     *
     * @param  mixed  $value
     */
    public function forLogs(mixed $value): mixed
    {
        return $this->walk($value, false);
    }

    /**
     * Redaksi untuk memori jangka panjang: kredensial + data pribadi.
     *
     * @param  mixed  $value
     */
    public function forMemory(mixed $value): mixed
    {
        return $this->walk($value, true);
    }

    public function text(string $text, bool $maskPersonal = true): string
    {
        // Pola kunci yang dikenali umum (Anthropic, bearer, token bot Telegram).
        $text = preg_replace('/\bsk-[A-Za-z0-9\-_]{12,}/', self::MASK, $text) ?? $text;
        $text = preg_replace('/\bBearer\s+[A-Za-z0-9\.\-_]{16,}/i', 'Bearer ' . self::MASK, $text) ?? $text;
        // Token bot Telegram. Tanpa batas kata di depan: pada URL ia menempel
        // pada path ("…/bot8123456789:AA…/getMe") sehingga \b tidak berlaku.
        $text = preg_replace('/\d{8,10}:[A-Za-z0-9_\-]{30,}/', self::MASK, $text) ?? $text;

        if ($maskPersonal) {
            $text = preg_replace('/[\w\.\-\+]+@[\w\-]+\.[\w\.\-]+/', '[email]', $text) ?? $text;
            $text = preg_replace('/(?<!\d)(?:\+?62|0)8\d{7,12}(?!\d)/', '[nomor]', $text) ?? $text;
        }

        return $text;
    }

    private function walk(mixed $value, bool $maskPersonal, string $key = ''): mixed
    {
        if (is_array($value)) {
            $out = [];
            foreach ($value as $childKey => $childValue) {
                $out[$childKey] = $this->walk($childValue, $maskPersonal, (string) $childKey);
            }

            return $out;
        }

        if (is_string($value)) {
            return $this->isSecretKey($key) ? self::MASK : $this->text($value, $maskPersonal);
        }

        return $this->isSecretKey($key) ? self::MASK : $value;
    }

    private function isSecretKey(string $key): bool
    {
        $key = strtolower($key);

        foreach (self::SECRET_KEYS as $needle) {
            if ($key === $needle || str_contains($key, $needle)) {
                return true;
            }
        }

        return false;
    }
}
