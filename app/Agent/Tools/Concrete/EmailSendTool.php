<?php

namespace App\Agent\Tools\Concrete;

use App\Agent\Data\ToolContext;
use App\Agent\Data\ToolDefinition;
use App\Agent\Data\ToolResult;
use App\Agent\Integrations\Connectors\MicrosoftGraphConnector;
use App\Agent\Integrations\IntegrationManager;
use App\Agent\Tools\BaseTool;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Mengirim email. Tindakan keluar yang tidak dapat ditarik kembali, sehingga
 * selalu melewati persetujuan manusia (lihat agent.policy.always_approve).
 */
class EmailSendTool extends BaseTool
{
    public function __construct(private readonly IntegrationManager $integrations)
    {
    }

    public function describe(): ToolDefinition
    {
        return new ToolDefinition(
            name: 'email.send',
            title: 'Kirim email',
            description: 'Mengirim email kepada penerima luar. Wajib disetujui manusia sebelum dijalankan.',
            inputSchema: [
                'source'  => ['type' => 'array', 'description' => 'Draf dari langkah email.draft'],
                'to'      => ['type' => 'array'],
                'subject' => ['type' => 'string'],
                'body'    => ['type' => 'string'],
            ],
            outputKeys: ['message_id', 'channel', 'to'],
            riskLevel: 'high',
            integration: 'microsoft365',
            readOnly: false,
            idempotent: false,
        );
    }

    public function execute(array $input, ToolContext $context): ToolResult
    {
        $draft   = (array) ($input['source'] ?? []);
        $to      = array_values(array_filter(array_map('strval', (array) ($input['to'] ?? $draft['to'] ?? []))));
        $subject = (string) ($input['subject'] ?? $draft['subject'] ?? '');
        $body    = (string) ($input['body'] ?? $draft['body'] ?? '');

        if ($to === [] || $subject === '' || trim($body) === '') {
            return ToolResult::failure('Draf email belum lengkap (penerima, subjek, atau isi kosong).', 'validation');
        }

        $organizationId = $context->organizationId();

        if ($this->integrations->isConnected($organizationId, 'microsoft365')) {
            return $this->sendViaGraph($organizationId, $to, $subject, $body);
        }

        if ($this->integrations->isConnected($organizationId, 'smtp_email')) {
            return $this->sendViaMailer($organizationId, $to, $subject, $body);
        }

        return ToolResult::failure(
            'Belum ada akses pengiriman email. Hubungkan Microsoft 365 atau aktifkan email SMTP aplikasi.',
            'missing_integration',
            ['integration' => 'microsoft365', 'alternatives' => ['smtp_email']],
        );
    }

    /** @param array<int, string> $to */
    private function sendViaGraph(string $organizationId, array $to, string $subject, string $body): ToolResult
    {
        $credentials = $this->integrations->credentials($organizationId, 'microsoft365');
        $mailbox     = (string) ($credentials['mailbox'] ?? '');

        try {
            $token = app(MicrosoftGraphConnector::class)->accessToken($credentials);

            $response = Http::withToken($token)->timeout(30)->post(
                'https://graph.microsoft.com/v1.0/users/' . urlencode($mailbox) . '/sendMail',
                [
                    'message' => [
                        'subject'      => $subject,
                        'body'         => ['contentType' => 'Text', 'content' => $body],
                        'toRecipients' => array_map(
                            static fn (string $address) => ['emailAddress' => ['address' => $address]],
                            $to,
                        ),
                    ],
                    'saveToSentItems' => true,
                ],
            );
        } catch (Throwable $e) {
            return ToolResult::failure('Gagal mengirim lewat Microsoft 365: ' . $e->getMessage(), 'network');
        }

        if (! $response->successful()) {
            return ToolResult::failure(
                'Microsoft 365 menolak pengiriman (HTTP ' . $response->status() . '): '
                . mb_substr((string) $response->json('error.message', ''), 0, 200),
                'provider_error',
            );
        }

        return ToolResult::success([
            'message_id' => 'graph-' . Str::uuid()->toString(),
            'channel'    => 'microsoft365',
            'to'         => $to,
            'mailbox'    => $mailbox,
        ], 'Email terkirim lewat Microsoft 365 kepada ' . implode(', ', $to) . '.');
    }

    /** @param array<int, string> $to */
    private function sendViaMailer(string $organizationId, array $to, string $subject, string $body): ToolResult
    {
        $from = $this->integrations->credentials($organizationId, 'smtp_email')['from_address']
            ?? config('mail.from.address');

        try {
            Mail::raw($body, static function ($message) use ($to, $subject, $from) {
                $message->to($to)->subject($subject);

                if ($from) {
                    $message->from($from, (string) config('mail.from.name'));
                }
            });
        } catch (Throwable $e) {
            return ToolResult::failure('Gagal mengirim email: ' . $e->getMessage(), 'provider_error');
        }

        return ToolResult::success([
            'message_id' => 'smtp-' . Str::uuid()->toString(),
            'channel'    => 'smtp',
            'to'         => $to,
        ], 'Email terkirim lewat kotak surat aplikasi kepada ' . implode(', ', $to) . '.');
    }
}
