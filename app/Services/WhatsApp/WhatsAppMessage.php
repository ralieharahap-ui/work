<?php

namespace App\Services\WhatsApp;

class WhatsAppMessage
{
    /**
     * @param string   $to       Nomor tujuan (format internasional tanpa "+") atau ID grup.
     * @param string   $body     Isi pesan (mendukung format tebal/miring khas WhatsApp).
     * @param string[] $mentions Nomor yang di-tag di dalam pesan (hanya berlaku di grup).
     * @param bool     $isGroup  True bila `$to` adalah ID grup.
     */
    public function __construct(
        public readonly string $to,
        public readonly string $body,
        public readonly array $mentions = [],
        public readonly bool $isGroup = false,
    ) {
    }
}
