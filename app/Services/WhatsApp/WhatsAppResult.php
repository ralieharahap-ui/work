<?php

namespace App\Services\WhatsApp;

class WhatsAppResult
{
    private function __construct(
        public readonly string $status,      // sent | failed | skipped
        public readonly ?string $reference = null,
        public readonly ?string $error = null,
    ) {
    }

    public static function sent(?string $reference = null): self
    {
        return new self('sent', $reference);
    }

    public static function failed(string $error): self
    {
        return new self('failed', null, mb_substr($error, 0, 480));
    }

    /** Tidak dikirim karena konfigurasi mematikan pengiriman — bukan kegagalan. */
    public static function skipped(string $reason): self
    {
        return new self('skipped', null, mb_substr($reason, 0, 480));
    }

    public function ok(): bool
    {
        return $this->status === 'sent';
    }
}
