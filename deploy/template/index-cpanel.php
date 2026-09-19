<?php

/**
 * Front controller untuk hosting cPanel.
 *
 * Struktur di server:
 *   /home/USERNAME/gep-erp/       <- inti aplikasi (app, config, vendor, .env, storage)
 *   /home/USERNAME/public_html/   <- folder ini (yang bisa diakses publik)
 *
 * Bila nama folder aplikasi diubah, sesuaikan $appRoot di bawah.
 */

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Lokasi inti aplikasi (satu tingkat di atas public_html).
$appRoot = __DIR__ . '/../gep-erp';

if (! is_dir($appRoot)) {
    http_response_code(500);
    exit('Folder aplikasi tidak ditemukan di: ' . $appRoot . ' — periksa kembali nama folder pada $appRoot.');
}

// Mode maintenance...
if (file_exists($maintenance = $appRoot . '/storage/framework/maintenance.php')) {
    require $maintenance;
}

// Composer autoloader...
require $appRoot . '/vendor/autoload.php';

// Jalankan aplikasi...
/** @var Application $app */
$app = require_once $appRoot . '/bootstrap/app.php';

// Folder publik berada di luar folder aplikasi (public_html), bukan di gep-erp/public.
// Tanpa baris ini Laravel mencari manifest Vite & berkas storage di lokasi yang salah.
$app->usePublicPath(__DIR__);

$app->handleRequest(Request::capture());
