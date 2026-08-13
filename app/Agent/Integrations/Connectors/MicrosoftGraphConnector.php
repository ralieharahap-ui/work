<?php

namespace App\Agent\Integrations\Connectors;

use App\Agent\Integrations\VerificationResult;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Microsoft 365 lewat Graph API dengan alur client credentials (izin aplikasi),
 * sehingga agent dapat bekerja tanpa sesi interaktif pengguna.
 */
class MicrosoftGraphConnector implements IntegrationConnector
{
    public function key(): string
    {
        return 'microsoft365';
    }

    public function verify(array $credentials): VerificationResult
    {
        foreach (['tenant_id', 'client_id', 'client_secret', 'mailbox'] as $field) {
            if (empty($credentials[$field])) {
                return VerificationResult::failed("Kolom '{$field}' belum diisi.");
            }
        }

        try {
            $token = $this->accessToken($credentials);
        } catch (Throwable $e) {
            return VerificationResult::failed($e->getMessage());
        }

        try {
            $mailbox  = (string) $credentials['mailbox'];
            $response = Http::withToken($token)->timeout(20)
                ->get('https://graph.microsoft.com/v1.0/users/' . urlencode($mailbox));
        } catch (Throwable $e) {
            return VerificationResult::failed('Graph tidak dapat dihubungi: ' . $e->getMessage());
        }

        if (! $response->successful()) {
            return VerificationResult::failed(
                'Kotak surat tidak dapat diakses (HTTP ' . $response->status() . '): '
                . mb_substr((string) $response->json('error.message', ''), 0, 200)
            );
        }

        return VerificationResult::ok([
            'mailbox'      => $response->json('userPrincipalName'),
            'display_name' => $response->json('displayName'),
        ]);
    }

    /** @param array<string, string> $credentials */
    public function accessToken(array $credentials): string
    {
        $response = Http::asForm()->timeout(20)->post(
            'https://login.microsoftonline.com/' . urlencode((string) $credentials['tenant_id']) . '/oauth2/v2.0/token',
            [
                'client_id'     => (string) $credentials['client_id'],
                'client_secret' => (string) $credentials['client_secret'],
                'scope'         => 'https://graph.microsoft.com/.default',
                'grant_type'    => 'client_credentials',
            ],
        );

        if (! $response->successful() || ! $response->json('access_token')) {
            throw new RuntimeException(
                'Gagal memperoleh token Microsoft: '
                . mb_substr((string) $response->json('error_description', 'HTTP ' . $response->status()), 0, 200)
            );
        }

        return (string) $response->json('access_token');
    }
}
