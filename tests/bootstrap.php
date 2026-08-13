<?php

require __DIR__ . '/../vendor/autoload.php';

/*
|--------------------------------------------------------------------------
| Lingkungan pengujian
|--------------------------------------------------------------------------
|
| Nilai berikut ditetapkan paksa sebelum aplikasi dijalankan: pengujian selalu
| memakai basis data sementara di memori dan tidak pernah menyentuh layanan
| luar.
|
| Penetapannya mengisi $_ENV, $_SERVER, sekaligus putenv() karena Laravel
| membaca ketiganya. Menyetel <env> pada phpunit.xml saja tidak cukup —
| variabel yang sudah diekspor di shell tetap menang lewat $_SERVER, sehingga
| sebuah `export DB_CONNECTION=mysql` di terminal bisa membuat RefreshDatabase
| mengosongkan basis data pengembangan yang sesungguhnya.
|
*/

foreach ([
    'APP_ENV'            => 'testing',
    'DB_CONNECTION'      => 'sqlite',
    'DB_DATABASE'        => ':memory:',
    'QUEUE_CONNECTION'   => 'sync',
    'CACHE_STORE'        => 'array',
    'SESSION_DRIVER'     => 'array',
    'MAIL_MAILER'        => 'log',
    'MAIL_FROM_ADDRESS'  => 'asisten@pt-uji.test',
    'BCRYPT_ROUNDS'      => '4',
    'TELEGRAM_BOT_TOKEN' => '',
    'WHATSAPP_ENABLED'   => 'false',
    'AGENT_LLM_PROVIDER' => 'scripted',
] as $key => $value) {
    $_ENV[$key] = $_SERVER[$key] = $value;
    putenv("{$key}={$value}");
}
