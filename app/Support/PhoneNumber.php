<?php

namespace App\Support;

class PhoneNumber
{
    /**
     * Normalkan nomor telepon menjadi format internasional tanpa tanda "+"
     * (format yang dipakai hampir semua gateway WhatsApp), contoh:
     *
     *   0812-3456-7890  → 6281234567890
     *   +62 812 3456 78 → 62812345678
     *   00628123456789  → 628123456789
     *   8123456789      → 628123456789
     *
     * Mengembalikan null bila nomor kosong atau terlalu pendek untuk valid.
     */
    public static function normalize(?string $raw, ?string $countryCode = null): ?string
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        $cc     = ltrim((string) ($countryCode ?: config('whatsapp.country_code', '62')), '+');
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '00')) {
            // Awalan panggilan internasional gaya lama.
            $digits = substr($digits, 2);
        } elseif (str_starts_with($digits, '0')) {
            // Nomor lokal: ganti "0" dengan kode negara.
            $digits = $cc . ltrim($digits, '0');
        } elseif ($cc !== '' && ! str_starts_with($digits, $cc)) {
            // Nomor tanpa awalan apa pun (mis. 8123456789).
            $digits = $cc . $digits;
        }

        return strlen($digits) >= 9 && strlen($digits) <= 20 ? $digits : null;
    }

    /** Format ramah-baca untuk antarmuka: 6281234567890 → +62 812-3456-7890 */
    public static function pretty(?string $raw, ?string $countryCode = null): ?string
    {
        $n = self::normalize($raw, $countryCode);

        if ($n === null) {
            return null;
        }

        $cc = ltrim((string) ($countryCode ?: config('whatsapp.country_code', '62')), '+');

        if ($cc !== '' && str_starts_with($n, $cc)) {
            $rest = substr($n, strlen($cc));

            return '+' . $cc . ' ' . trim(chunk_split($rest, 4, '-'), '-');
        }

        return '+' . $n;
    }
}
