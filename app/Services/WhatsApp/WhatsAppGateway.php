<?php

namespace App\Services\WhatsApp;

use App\Services\WhatsApp\Drivers\CloudApiDriver;
use App\Services\WhatsApp\Drivers\FonnteDriver;
use App\Services\WhatsApp\Drivers\GoWhatsAppDriver;
use App\Services\WhatsApp\Drivers\LogDriver;
use App\Services\WhatsApp\Drivers\WablasDriver;
use App\Services\WhatsApp\Drivers\WebhookDriver;
use App\Services\WhatsApp\Drivers\WhatsAppDriver;
use InvalidArgumentException;

/**
 * Pintu tunggal ke layanan WhatsApp: memilih driver sesuai konfigurasi dan
 * menegakkan saklar `whatsapp.enabled` supaya instalasi yang belum siap tidak
 * pernah menghubungi penyedia mana pun.
 */
class WhatsAppGateway
{
    private ?WhatsAppDriver $driver = null;

    public function driver(): WhatsAppDriver
    {
        return $this->driver ??= $this->resolve((string) config('whatsapp.driver', 'log'));
    }

    public function driverName(): string
    {
        return $this->driver()->name();
    }

    public function isEnabled(): bool
    {
        return (bool) config('whatsapp.enabled', false);
    }

    /** Siap kirim = saklar menyala dan kredensial driver lengkap. */
    public function isReady(): bool
    {
        return $this->isEnabled() && $this->driver()->isConfigured();
    }

    public function send(WhatsAppMessage $message): WhatsAppResult
    {
        if (! $this->isEnabled()) {
            return WhatsAppResult::skipped('Notifikasi WhatsApp dimatikan (WHATSAPP_ENABLED=false).');
        }

        if (! $this->driver()->isConfigured()) {
            return WhatsAppResult::failed("Driver '{$this->driverName()}' belum dikonfigurasi lengkap.");
        }

        return $this->driver()->send($message);
    }

    private function resolve(string $name): WhatsAppDriver
    {
        $config  = (array) config("whatsapp.drivers.{$name}", []);
        $timeout = (int) config('whatsapp.timeout', 20);

        return match ($name) {
            'log'          => new LogDriver(),
            'fonnte'       => new FonnteDriver($config, $timeout),
            'wablas'       => new WablasDriver($config, $timeout),
            'cloud_api'    => new CloudApiDriver($config, $timeout),
            'go_whatsapp'  => new GoWhatsAppDriver($config, $timeout),
            'webhook'      => new WebhookDriver($config, $timeout),
            default        => throw new InvalidArgumentException("Driver WhatsApp '{$name}' tidak dikenal."),
        };
    }
}
