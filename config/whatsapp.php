<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Saklar Utama
    |--------------------------------------------------------------------------
    |
    | Bila `enabled` bernilai false, seluruh pengiriman pesan hanya dicatat ke
    | dalam riwayat notifikasi dengan status "skipped" — tidak ada panggilan ke
    | penyedia layanan mana pun. Ini nilai bawaan supaya instalasi yang belum
    | dikonfigurasi tidak pernah menghubungi pihak ketiga.
    |
    */

    'enabled' => (bool) env('WHATSAPP_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Driver Pengirim
    |--------------------------------------------------------------------------
    |
    |   log         — tulis pesan ke log aplikasi (aman untuk uji coba)
    |   go_whatsapp — gateway swakelola go-whatsapp-web-multidevice (login via QR,
    |                 memakai nomor WhatsApp biasa milik perusahaan)
    |   fonnte      — https://fonnte.com (gateway lokal, paling umum di Indonesia)
    |   wablas      — https://wablas.com (gateway lokal)
    |   cloud_api   — WhatsApp Business Cloud API resmi dari Meta
    |   webhook     — POST JSON ke URL milik sendiri (mis. bot Baileys/whatsapp-web.js)
    |
    */

    'driver' => env('WHATSAPP_DRIVER', 'log'),

    /** Batas waktu (detik) satu panggilan HTTP ke gateway. */
    'timeout' => (int) env('WHATSAPP_TIMEOUT', 20),

    /** Kode negara bawaan untuk menormalkan nomor lokal (0812… → 62812…). */
    'country_code' => (string) env('WHATSAPP_COUNTRY_CODE', '62'),

    /*
    |--------------------------------------------------------------------------
    | Grup Tim (opsional)
    |--------------------------------------------------------------------------
    |
    | Bila diisi, salinan pengingat juga dikirim ke grup WhatsApp tim. Di grup,
    | nama PIC di-mention sungguhan (tag) memakai nomor WhatsApp-nya sehingga
    | notifikasi masuk ke ponsel yang bersangkutan.
    |
    | `group_id` diisi ID grup sesuai format gateway yang dipakai, contoh
    | Fonnte/Baileys: 120363012345678901@g.us
    |
    */

    'group' => [
        'enabled' => (bool) env('WHATSAPP_GROUP_ENABLED', false),
        'id'      => env('WHATSAPP_GROUP_ID'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Aturan Pengingat Tugas
    |--------------------------------------------------------------------------
    */

    'reminder' => [
        /** Kirim pengingat saat sisa waktu tinggal N hari (0 = hari-H). */
        'days_before' => array_values(array_filter(
            array_map('intval', explode(',', (string) env('WHATSAPP_REMINDER_DAYS', '3,1,0'))),
            fn ($d) => $d >= 0,
        )),

        /** Tugas yang sudah kadaluarsa diingatkan ulang setiap N hari. */
        'overdue_every_days' => max(1, (int) env('WHATSAPP_OVERDUE_EVERY_DAYS', 1)),

        /** Jam pengiriman terjadwal harian (zona waktu aplikasi). */
        'time' => env('WHATSAPP_REMINDER_TIME', '08:00'),

        /** Maksimal tugas yang dirinci per kategori dalam satu pesan. */
        'max_tasks_per_group' => (int) env('WHATSAPP_REMINDER_MAX_TASKS', 8),

        /** Lewati pengiriman terjadwal pada hari Sabtu & Minggu. */
        'skip_weekend' => (bool) env('WHATSAPP_REMINDER_SKIP_WEEKEND', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Kredensial per Driver
    |--------------------------------------------------------------------------
    */

    'drivers' => [

        /*
         | Gateway swakelola: https://github.com/aldinokemal/go-whatsapp-web-multidevice
         |
         | Dijalankan sebagai layanan terpisah (lihat docker-compose.prod.yml),
         | tersambung ke WhatsApp lewat pemindaian QR sekali di dasbornya.
         | `device_id` hanya perlu diisi bila gateway melayani lebih dari satu
         | perangkat/nomor.
         */
        'go_whatsapp' => [
            'base_url'  => env('GOWA_BASE_URL'),
            'username'  => env('GOWA_USERNAME'),
            'password'  => env('GOWA_PASSWORD'),
            'device_id' => env('GOWA_DEVICE_ID'),
        ],

        'fonnte' => [
            'endpoint' => env('FONNTE_ENDPOINT', 'https://api.fonnte.com/send'),
            'token'    => env('FONNTE_TOKEN'),
        ],

        'wablas' => [
            'endpoint' => env('WABLAS_ENDPOINT', 'https://console.wablas.com/api/send-message'),
            'token'    => env('WABLAS_TOKEN'),
            'secret'   => env('WABLAS_SECRET_KEY'),
        ],

        'cloud_api' => [
            'version'         => env('WHATSAPP_CLOUD_VERSION', 'v20.0'),
            'phone_number_id' => env('WHATSAPP_CLOUD_PHONE_NUMBER_ID'),
            'token'           => env('WHATSAPP_CLOUD_TOKEN'),
        ],

        'webhook' => [
            'url'    => env('WHATSAPP_WEBHOOK_URL'),
            'secret' => env('WHATSAPP_WEBHOOK_SECRET'),
        ],

    ],

];
