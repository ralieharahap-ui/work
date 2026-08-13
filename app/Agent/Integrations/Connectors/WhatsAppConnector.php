<?php

namespace App\Agent\Integrations\Connectors;

use App\Agent\Integrations\VerificationResult;
use App\Services\WhatsApp\WhatsAppGateway;

/** Memakai gateway WhatsApp yang sudah terpasang pada aplikasi ini. */
class WhatsAppConnector implements IntegrationConnector
{
    public function __construct(private readonly WhatsAppGateway $gateway)
    {
    }

    public function key(): string
    {
        return 'whatsapp';
    }

    public function verify(array $credentials): VerificationResult
    {
        if (! $this->gateway->isEnabled()) {
            return VerificationResult::failed('WHATSAPP_ENABLED masih false pada konfigurasi server.');
        }

        if (! $this->gateway->isReady()) {
            return VerificationResult::failed("Driver '{$this->gateway->driverName()}' belum lengkap kredensialnya.");
        }

        return VerificationResult::ok(['driver' => $this->gateway->driverName()]);
    }
}
