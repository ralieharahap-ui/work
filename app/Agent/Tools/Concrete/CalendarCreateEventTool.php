<?php

namespace App\Agent\Tools\Concrete;

use App\Agent\Data\ToolContext;
use App\Agent\Data\ToolDefinition;
use App\Agent\Data\ToolResult;
use App\Agent\Integrations\Connectors\GoogleCalendarConnector;
use App\Agent\Integrations\Connectors\MicrosoftGraphConnector;
use App\Agent\Integrations\IntegrationManager;
use App\Agent\Tools\BaseTool;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Throwable;

/** Membuat agenda pada kalender kantor (Microsoft 365 atau Google Calendar). */
class CalendarCreateEventTool extends BaseTool
{
    public function __construct(private readonly IntegrationManager $integrations)
    {
    }

    public function describe(): ToolDefinition
    {
        return new ToolDefinition(
            name: 'calendar.create_event',
            title: 'Buat agenda',
            description: 'Membuat acara pada kalender kantor beserta undangan pesertanya.',
            inputSchema: [
                'title'     => ['type' => 'string', 'required' => true],
                'starts_at' => ['type' => 'string', 'required' => true, 'description' => 'Waktu mulai, format ISO8601'],
                'duration'  => ['type' => 'int', 'default' => 60, 'description' => 'Durasi dalam menit'],
                'attendees' => ['type' => 'array'],
                'location'  => ['type' => 'string'],
                'notes'     => ['type' => 'string'],
            ],
            outputKeys: ['event_id', 'starts_at', 'ends_at', 'channel'],
            riskLevel: 'high',
            integration: 'microsoft365',
            readOnly: false,
            idempotent: false,
        );
    }

    public function execute(array $input, ToolContext $context): ToolResult
    {
        try {
            $timezone = (string) config('app.timezone', 'UTC');
            $start    = Carbon::parse((string) $input['starts_at'], $timezone);
        } catch (Throwable) {
            return ToolResult::failure("Waktu mulai '{$input['starts_at']}' tidak dapat dibaca.", 'validation');
        }

        $end       = $start->copy()->addMinutes(max(15, (int) ($input['duration'] ?? 60)));
        $attendees = array_values(array_filter(
            array_map('strval', (array) ($input['attendees'] ?? [])),
            static fn (string $a) => filter_var($a, FILTER_VALIDATE_EMAIL) !== false,
        ));

        $organizationId = $context->organizationId();

        if ($this->integrations->isConnected($organizationId, 'microsoft365')) {
            return $this->createOnGraph($organizationId, $input, $start, $end, $attendees);
        }

        if ($this->integrations->isConnected($organizationId, 'google_calendar')) {
            return $this->createOnGoogle($organizationId, $input, $start, $end, $attendees);
        }

        return ToolResult::failure(
            'Belum ada akses kalender. Hubungkan Microsoft 365 atau Google Calendar lebih dulu.',
            'missing_integration',
            ['integration' => 'microsoft365', 'alternatives' => ['google_calendar']],
        );
    }

    /** @param array<int, string> $attendees */
    private function createOnGraph(string $organizationId, array $input, Carbon $start, Carbon $end, array $attendees): ToolResult
    {
        $credentials = $this->integrations->credentials($organizationId, 'microsoft365');
        $mailbox     = (string) ($credentials['mailbox'] ?? '');

        try {
            $token    = app(MicrosoftGraphConnector::class)->accessToken($credentials);
            $response = Http::withToken($token)->timeout(30)->post(
                'https://graph.microsoft.com/v1.0/users/' . urlencode($mailbox) . '/events',
                array_filter([
                    'subject'   => (string) $input['title'],
                    'body'      => ['contentType' => 'Text', 'content' => (string) ($input['notes'] ?? '')],
                    'start'     => ['dateTime' => $start->toIso8601String(), 'timeZone' => $start->timezoneName],
                    'end'       => ['dateTime' => $end->toIso8601String(), 'timeZone' => $end->timezoneName],
                    'location'  => $input['location'] ? ['displayName' => (string) $input['location']] : null,
                    'attendees' => array_map(static fn (string $a) => [
                        'emailAddress' => ['address' => $a], 'type' => 'required',
                    ], $attendees),
                ]),
            );
        } catch (Throwable $e) {
            return ToolResult::failure('Gagal membuat agenda di Microsoft 365: ' . $e->getMessage(), 'network');
        }

        if (! $response->successful()) {
            return ToolResult::failure(
                'Microsoft 365 menolak pembuatan agenda (HTTP ' . $response->status() . '): '
                . mb_substr((string) $response->json('error.message', ''), 0, 200),
                'provider_error',
            );
        }

        return ToolResult::success([
            'event_id'  => (string) $response->json('id'),
            'starts_at' => $start->toIso8601String(),
            'ends_at'   => $end->toIso8601String(),
            'channel'   => 'microsoft365',
            'attendees' => $attendees,
        ], 'Agenda "' . $input['title'] . '" dibuat pada ' . $start->locale('id')->translatedFormat('d F Y H:i') . '.');
    }

    /** @param array<int, string> $attendees */
    private function createOnGoogle(string $organizationId, array $input, Carbon $start, Carbon $end, array $attendees): ToolResult
    {
        $credentials = $this->integrations->credentials($organizationId, 'google_calendar');
        $calendarId  = (string) ($credentials['calendar_id'] ?? 'primary');

        try {
            $token    = app(GoogleCalendarConnector::class)->accessToken($credentials);
            $response = Http::withToken($token)->timeout(30)->post(
                'https://www.googleapis.com/calendar/v3/calendars/' . urlencode($calendarId) . '/events',
                array_filter([
                    'summary'     => (string) $input['title'],
                    'description' => (string) ($input['notes'] ?? ''),
                    'location'    => $input['location'] ?? null,
                    'start'       => ['dateTime' => $start->toIso8601String(), 'timeZone' => $start->timezoneName],
                    'end'         => ['dateTime' => $end->toIso8601String(), 'timeZone' => $end->timezoneName],
                    'attendees'   => array_map(static fn (string $a) => ['email' => $a], $attendees),
                ]),
            );
        } catch (Throwable $e) {
            return ToolResult::failure('Gagal membuat agenda di Google Calendar: ' . $e->getMessage(), 'network');
        }

        if (! $response->successful()) {
            return ToolResult::failure(
                'Google Calendar menolak pembuatan agenda (HTTP ' . $response->status() . '): '
                . mb_substr((string) $response->json('error.message', ''), 0, 200),
                'provider_error',
            );
        }

        return ToolResult::success([
            'event_id'  => (string) $response->json('id'),
            'starts_at' => $start->toIso8601String(),
            'ends_at'   => $end->toIso8601String(),
            'channel'   => 'google_calendar',
            'attendees' => $attendees,
        ], 'Agenda "' . $input['title'] . '" dibuat pada ' . $start->locale('id')->translatedFormat('d F Y H:i') . '.');
    }
}
