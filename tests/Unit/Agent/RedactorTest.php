<?php

namespace Tests\Unit\Agent;

use App\Agent\Memory\Redactor;
use PHPUnit\Framework\TestCase;

/** Kredensial tidak boleh bocor ke log; data pribadi tidak boleh masuk memori. */
class RedactorTest extends TestCase
{
    private Redactor $redactor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->redactor = new Redactor();
    }

    public function test_kunci_rahasia_disamarkan_pada_log(): void
    {
        $clean = $this->redactor->forLogs([
            'to'            => 'klien@example.com',
            'api_key'       => 'sk-ant-abc123456789012345',
            'client_secret' => 'sangat-rahasia',
            'nested'        => ['bot_token' => '123456789:AAH-abcdefghijklmnopqrstuvwxyz012345', 'judul' => 'Laporan'],
        ]);

        $this->assertSame(Redactor::MASK, $clean['api_key']);
        $this->assertSame(Redactor::MASK, $clean['client_secret']);
        $this->assertSame(Redactor::MASK, $clean['nested']['bot_token']);

        // Data operasional tetap utuh supaya jejak audit tetap berguna.
        $this->assertSame('klien@example.com', $clean['to']);
        $this->assertSame('Laporan', $clean['nested']['judul']);
    }

    public function test_pola_kunci_di_dalam_teks_bebas_ikut_disamarkan(): void
    {
        $text = 'Gunakan sk-ant-api03-abcdefghijklmnop dan header Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9';

        $clean = $this->redactor->text($text, false);

        $this->assertStringNotContainsString('sk-ant-api03', $clean);
        $this->assertStringNotContainsString('eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9', $clean);
    }

    public function test_token_bot_yang_menempel_pada_url_ikut_disamarkan(): void
    {
        // Galat jaringan Telegram memuat token di dalam path URL; pesan itu
        // tersimpan di basis data dan tampil di layar Akses Tools.
        $clean = $this->redactor->text(
            'cURL error 56 for https://api.telegram.org/bot8123456789:AAEGGhwVEurCgpXwpmTncWtdFAXJosUTxpQ/getMe',
            false,
        );

        $this->assertStringNotContainsString('AAEGGhwVEurCgpXwpmTncWtdFAXJosUTxpQ', $clean);
        $this->assertStringContainsString('api.telegram.org', $clean, 'Konteks galatnya harus tetap terbaca.');
    }

    public function test_angka_biasa_tidak_ikut_tersamarkan(): void
    {
        $clean = $this->redactor->text('Faktur 2026081234 senilai Rp 12.500.000 sudah dibayar', false);

        $this->assertSame('Faktur 2026081234 senilai Rp 12.500.000 sudah dibayar', $clean);
    }

    public function test_data_pribadi_disaring_sebelum_masuk_memori_jangka_panjang(): void
    {
        $memory = $this->redactor->forMemory([
            'ringkasan' => 'Kirim laporan ke budi@pelanggan.co.id dan hubungi 081234567890.',
        ]);

        $this->assertStringContainsString('[email]', $memory['ringkasan']);
        $this->assertStringContainsString('[nomor]', $memory['ringkasan']);
        $this->assertStringNotContainsString('budi@pelanggan.co.id', $memory['ringkasan']);
        $this->assertStringNotContainsString('081234567890', $memory['ringkasan']);
    }
}
