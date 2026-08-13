<?php

namespace App\Agent\Integrations\Connectors;

use App\Agent\Integrations\VerificationResult;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/** Google Calendar memakai refresh token yang sudah disetujui pemilik akun. */
class GoogleCalendarConnector implements IntegrationConnector
{
    public function key(): string
    {
        return 'google_calendar';
    }

    public function verify(array $credentials): VerificationResult
    {
        foreach (['client_id', 'client_secret', 'refresh_token'] as $field) {
            if (empty($credentials[$field])) {
                return VerificationResult::failed("Kolom '{$field}' belum diisi.");
            }
        }

        try {
            $token = $this->accessToken($credentials);
        } catch (Throwable $e) {
            return VerificationResult::failed($e->getMessage());
        }

        $calendarId = (string) ($credentials['calendar_id'] ?? 'primary');

        try {
            $response = Http::withToken($token)->timeout(20)
                ->get('https://www.googleapis.com/calendar/v3/calendars/' . urlencode($calendarId));
        } catch (Throwable $e) {
            return VerificationResult::failed('Google Calendar tidak dapat dihubungi: ' . $e->getMessage());
        }

        if (! $response->successful()) {
            return VerificationResult::failed(
                'Kalender tidak dapat diakses (HTTP ' . $response->status() . '): '
                . mb_substr((string) $response->json('error.message', ''), 0, 200)
            );
        }

        return VerificationResult::ok([
            'calendar'  => $response->json('summary'),
            'time_zone' => $response->json('timeZone'),
        ]);
    }

    /** @param array<string, string> $credentials */
    public function accessToken(array $credentials): string
    {
        $response = Http::asForm()->timeout(20)->post('https://oauth2.googleapis.com/token', [
            'client_id'     => (string) $credentials['client_id'],
            'client_secret' => (string) $credentials['client_secret'],
            'refresh_token' => (string) $credentials['refresh_token'],
            'grant_type'    => 'refresh_token',
        ]);

        if (! $response->successful() || ! $response->json('access_token')) {
            throw new RuntimeException(
                'Gagal menyegarkan token Google: '
                . mb_substr((string) $response->json('error_description', 'HTTP ' . $response->status()), 0, 200)
            );
        }

        return (string) $response->json('access_token');
    }
}
