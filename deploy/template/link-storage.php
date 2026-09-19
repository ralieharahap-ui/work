<?php

/**
 * Pembuat symlink  public_html/storage  ->  gep-erp/storage/app/public
 *
 * Dipakai bila hosting tidak menyediakan akses SSH untuk menjalankan
 * "php artisan storage:link". Berkas ini WAJIB DIHAPUS setelah dijalankan.
 *
 * Cara pakai:
 *   1. Unggah berkas ini ke dalam public_html/
 *   2. Buka https://domain-anda/link-storage.php melalui browser
 *   3. Hapus berkas ini dari public_html/
 */

$target = __DIR__ . '/../gep-erp/storage/app/public';
$link   = __DIR__ . '/storage';

header('Content-Type: text/plain; charset=utf-8');

if (! is_dir($target)) {
    exit("GAGAL: folder tujuan tidak ada -> {$target}\nPeriksa kembali nama folder aplikasi.\n");
}

if (file_exists($link) || is_link($link)) {
    exit("Symlink/folder 'storage' sudah ada. Tidak ada perubahan.\nJangan lupa hapus berkas link-storage.php ini.\n");
}

if (@symlink($target, $link)) {
    exit("BERHASIL: symlink 'storage' dibuat.\n\nSEGERA HAPUS berkas link-storage.php dari public_html.\n");
}

// Sebagian hosting mematikan symlink(). Alternatif: salin manual lewat File Manager.
exit(
    "GAGAL membuat symlink (kemungkinan dinonaktifkan hosting).\n\n" .
    "Alternatif: lewat cPanel File Manager, buat folder 'storage' di dalam public_html,\n" .
    "lalu salin isi dari gep-erp/storage/app/public ke dalamnya.\n" .
    "Unggahan berkas baru perlu disalin ulang secara berkala, atau minta hosting\n" .
    "mengaktifkan fungsi symlink().\n"
);
