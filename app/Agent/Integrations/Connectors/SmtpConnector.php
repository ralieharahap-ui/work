<?php

namespace App\Agent\Integrations\Connectors;

use App\Agent\Integrations\VerificationResult;

/**
 * Memakai konfigurasi surat aplikasi. Verifikasi sengaja tidak mengirim email
 * percobaan ke pihak luar — cukup memastikan konfigurasinya lengkap.
 */
class SmtpConnector implements IntegrationConnector
{
    public function key(): string
    {
        return 'smtp_email';
    }

    public function verify(array $credentials): VerificationResult
    {
        $mailer = (string) config('mail.default');

        if ($mailer === '' || $mailer === 'array') {
            return VerificationResult::failed('MAIL_MAILER belum dikonfigurasi pada server.');
        }

        $from = (string) ($credentials['from_address'] ?? config('mail.from.address', ''));

        if ($from === '') {
            return VerificationResult::failed('Alamat pengirim belum ditentukan (MAIL_FROM_ADDRESS kosong).');
        }

        if ($mailer === 'smtp' && ! config('mail.mailers.smtp.host')) {
            return VerificationResult::failed('MAIL_HOST belum diisi.');
        }

        return VerificationResult::ok(['mailer' => $mailer, 'from' => $from]);
    }
}
