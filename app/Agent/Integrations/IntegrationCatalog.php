<?php

namespace App\Agent\Integrations;

/**
 * Daftar akses yang dibutuhkan agent untuk bekerja, lengkap dengan panduan
 * cara memperolehnya. Katalog inilah yang dibacakan agent saat pertama kali
 * dijalankan: "ini yang saya butuhkan, ini alasannya, ini cara memberikannya".
 */
class IntegrationCatalog
{
    /** @return array<string, array<string, mixed>> */
    public static function all(): array
    {
        return [
            'telegram' => [
                'label'    => 'Telegram Bot',
                'purpose'  => 'Kanal percakapan utama — Anda memberi perintah dan menyetujui tindakan lewat chat.',
                'essential'=> true,
                'scopes'   => ['kirim & terima pesan pada chat yang menautkan diri ke akun Anda'],
                'fields'   => [
                    'bot_token' => ['label' => 'Token Bot', 'secret' => true, 'required' => true, 'hint' => 'Format 123456:ABC-DEF...'],
                ],
                'guidance' => [
                    'Buka Telegram, cari @BotFather, kirim /newbot.',
                    'Beri nama bot (mis. "Asisten Kantor GEP") lalu username yang berakhiran "bot".',
                    'BotFather membalas dengan token — salin dan tempel ke kolom Token Bot.',
                    'Setelah tersimpan, buka bot Anda dan kirim /mulai untuk menautkan akun.',
                ],
                'revoke' => 'Cabut kapan saja lewat /revoke di @BotFather atau tombol "Cabut akses" di dasbor.',
            ],

            'anthropic' => [
                'label'    => 'Model Bahasa (Claude)',
                'purpose'  => 'Meningkatkan kualitas pemahaman instruksi bebas dan penyusunan rencana.',
                'essential'=> false,
                'scopes'   => ['mengirim teks tugas untuk dianalisis'],
                'fields'   => [
                    'api_key' => ['label' => 'API Key', 'secret' => true, 'required' => true, 'hint' => 'Diawali sk-ant-'],
                ],
                'guidance' => [
                    'Buka console.anthropic.com → API Keys → Create Key.',
                    'Salin kunci lalu tempel di sini.',
                    'Tanpa kunci ini agent tetap bekerja memakai perencana heuristik bawaan.',
                ],
                'revoke' => 'Hapus kunci di console.anthropic.com bila akses ingin dicabut.',
            ],

            'microsoft365' => [
                'label'    => 'Microsoft 365 (email & kalender)',
                'purpose'  => 'Membaca agenda, membuat undangan rapat, dan mengirim email atas nama kotak surat kantor.',
                'essential'=> false,
                'scopes'   => ['Mail.Send', 'Calendars.ReadWrite'],
                'fields'   => [
                    'tenant_id'     => ['label' => 'Tenant ID', 'secret' => false, 'required' => true],
                    'client_id'     => ['label' => 'Client ID (Application ID)', 'secret' => false, 'required' => true],
                    'client_secret' => ['label' => 'Client Secret', 'secret' => true, 'required' => true],
                    'mailbox'       => ['label' => 'Kotak surat yang dipakai', 'secret' => false, 'required' => true, 'hint' => 'mis. asisten@perusahaan.com'],
                ],
                'guidance' => [
                    'Masuk ke portal.azure.com → Microsoft Entra ID → App registrations → New registration.',
                    'Catat Application (client) ID dan Directory (tenant) ID.',
                    'Buka Certificates & secrets → New client secret, salin nilainya (hanya tampil sekali).',
                    'Buka API permissions → Add permission → Microsoft Graph → Application permissions → pilih Mail.Send dan Calendars.ReadWrite → Grant admin consent.',
                    'Isi kotak surat yang boleh dipakai agent; sebaiknya kotak surat khusus, bukan email pribadi.',
                ],
                'revoke' => 'Hapus client secret atau cabut izin aplikasi di portal Azure.',
            ],

            'google_calendar' => [
                'label'    => 'Google Calendar',
                'purpose'  => 'Alternatif kalender bila kantor memakai Google Workspace.',
                'essential'=> false,
                'scopes'   => ['https://www.googleapis.com/auth/calendar.events'],
                'fields'   => [
                    'client_id'     => ['label' => 'Client ID', 'secret' => false, 'required' => true],
                    'client_secret' => ['label' => 'Client Secret', 'secret' => true, 'required' => true],
                    'refresh_token' => ['label' => 'Refresh Token', 'secret' => true, 'required' => true],
                    'calendar_id'   => ['label' => 'ID Kalender', 'secret' => false, 'required' => false, 'hint' => 'Kosongkan untuk kalender utama'],
                ],
                'guidance' => [
                    'Buka console.cloud.google.com → APIs & Services → aktifkan Google Calendar API.',
                    'Buat OAuth client (tipe Desktop/Web) lalu catat Client ID & Client Secret.',
                    'Lakukan persetujuan OAuth sekali dengan cakupan calendar.events untuk memperoleh refresh token.',
                    'Tempel ketiga nilai tersebut di sini.',
                ],
                'revoke' => 'Cabut lewat myaccount.google.com → Keamanan → Akses aplikasi pihak ketiga.',
            ],

            'smtp_email' => [
                'label'    => 'Email SMTP (kotak surat aplikasi)',
                'purpose'  => 'Mengirim email memakai konfigurasi surat aplikasi yang sudah ada.',
                'essential'=> false,
                'scopes'   => ['mengirim email keluar'],
                'fields'   => [
                    'from_address' => ['label' => 'Alamat pengirim', 'secret' => false, 'required' => false, 'hint' => 'Kosongkan untuk memakai MAIL_FROM_ADDRESS'],
                ],
                'guidance' => [
                    'Pastikan MAIL_MAILER, MAIL_HOST, MAIL_USERNAME, dan MAIL_PASSWORD sudah terisi pada berkas .env server.',
                    'Aktifkan akses ini bila email cukup dikirim dari kotak surat aplikasi, bukan Microsoft 365.',
                ],
                'revoke' => 'Nonaktifkan dari dasbor kapan saja.',
            ],

            'whatsapp' => [
                'label'    => 'WhatsApp (gateway aplikasi)',
                'purpose'  => 'Mengirim pengingat WhatsApp memakai gateway yang sudah terpasang di aplikasi ini.',
                'essential'=> false,
                'scopes'   => ['mengirim pesan ke nomor karyawan yang terdaftar'],
                'fields'   => [],
                'guidance' => [
                    'Akses ini memakai konfigurasi WhatsApp aplikasi (WHATSAPP_ENABLED & WHATSAPP_DRIVER).',
                    'Aktifkan bila agent boleh mengirim pengingat lewat WhatsApp; tetap butuh persetujuan manusia tiap kali mengirim.',
                ],
                'revoke' => 'Nonaktifkan dari dasbor atau setel WHATSAPP_ENABLED=false.',
            ],
        ];
    }

    /** @return array<string, mixed>|null */
    public static function get(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

    /** @return array<int, string> */
    public static function keys(): array
    {
        return array_keys(self::all());
    }
}
