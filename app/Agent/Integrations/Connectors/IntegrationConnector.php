<?php

namespace App\Agent\Integrations\Connectors;

use App\Agent\Integrations\VerificationResult;

/** Uji koneksi satu layanan luar berdasarkan kredensial yang diberikan user. */
interface IntegrationConnector
{
    public function key(): string;

    /** @param array<string, string> $credentials */
    public function verify(array $credentials): VerificationResult;
}
