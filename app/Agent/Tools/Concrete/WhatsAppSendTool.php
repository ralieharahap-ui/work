<?php

namespace App\Agent\Tools\Concrete;

use App\Agent\Data\ToolContext;
use App\Agent\Data\ToolDefinition;
use App\Agent\Data\ToolResult;
use App\Agent\Integrations\IntegrationManager;
use App\Agent\Tools\BaseTool;
use App\Models\User;
use App\Models\WhatsappNotification;
use App\Services\WhatsApp\WhatsAppGateway;
use App\Services\WhatsApp\WhatsAppMessage;
use App\Support\PhoneNumber;

/**
 * Mengirim pesan WhatsApp memakai gateway aplikasi yang sudah ada.
 * Termasuk tindakan berisiko tinggi: selalu lewat persetujuan manusia.
 */
class WhatsAppSendTool extends BaseTool
{
    public function __construct(
        private readonly WhatsAppGateway $gateway,
        private readonly IntegrationManager $integrations,
    ) {
    }

    public function describe(): ToolDefinition
    {
        return new ToolDefinition(
            name: 'whatsapp.send',
            title: 'Kirim WhatsApp',
            description: 'Mengirim pesan WhatsApp kepada karyawan yang nomornya terdaftar di aplikasi.',
            inputSchema: [
                'user'    => ['type' => 'string', 'description' => 'Nama atau UUID karyawan tujuan'],
                'phone'   => ['type' => 'string', 'description' => 'Nomor tujuan bila bukan karyawan terdaftar'],
                'message' => ['type' => 'string', 'required' => true],
            ],
            outputKeys: ['status', 'recipient', 'reference'],
            riskLevel: 'high',
            integration: 'whatsapp',
            readOnly: false,
            idempotent: false,
        );
    }

    public function execute(array $input, ToolContext $context): ToolResult
    {
        if (! $this->integrations->isConnected($context->organizationId(), 'whatsapp')) {
            return ToolResult::failure(
                'Akses WhatsApp belum diaktifkan untuk agent.',
                'missing_integration',
                ['integration' => 'whatsapp'],
            );
        }

        $recipient = null;
        $user      = null;

        if (! empty($input['user'])) {
            $user = User::where('organization_id', $context->organizationId())
                ->where(fn ($q) => $q->where('id', $input['user'])->orWhere('name', 'like', '%' . $input['user'] . '%'))
                ->first();

            if (! $user) {
                return ToolResult::failure("Karyawan '{$input['user']}' tidak ditemukan.", 'not_found');
            }

            $recipient = PhoneNumber::normalize($user->whatsapp_number ?: $user->phone);

            if (! $recipient) {
                return ToolResult::failure(
                    "Nomor WhatsApp {$user->name} belum terdaftar.",
                    'missing_data',
                    ['user_id' => $user->id],
                );
            }
        } elseif (! empty($input['phone'])) {
            $recipient = PhoneNumber::normalize((string) $input['phone']);
        }

        if (! $recipient) {
            return ToolResult::failure('Tujuan pesan belum jelas (isi user atau phone).', 'validation');
        }

        $result = $this->gateway->send(new WhatsAppMessage(
            to: $recipient,
            body: (string) $input['message'],
        ));

        WhatsappNotification::create([
            'organization_id' => $context->organizationId(),
            'user_id'         => $user?->id,
            'triggered_by'    => $context->user?->id,
            'channel'         => 'personal',
            'type'            => 'agent',
            'driver'          => $this->gateway->driverName(),
            'recipient'       => $recipient,
            'body'            => (string) $input['message'],
            'task_ids'        => [],
            'status'          => $result->status,
            'error'           => $result->error,
            'reference'       => $result->reference,
            'sent_at'         => $result->ok() ? now() : null,
        ]);

        if (! $result->ok()) {
            return ToolResult::failure(
                'Pesan WhatsApp tidak terkirim: ' . ($result->error ?? 'alasan tidak diketahui'),
                $result->status === 'skipped' ? 'disabled' : 'provider_error',
            );
        }

        return ToolResult::success([
            'status'    => $result->status,
            'recipient' => PhoneNumber::pretty($recipient),
            'reference' => $result->reference,
        ], 'Pesan WhatsApp terkirim ke ' . PhoneNumber::pretty($recipient) . '.');
    }
}
