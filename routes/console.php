<?php

use Illuminate\Support\Facades\Schedule;

// Pengingat tugas via WhatsApp untuk PIC yang tenggatnya sudah dekat atau lewat.
// Jam pengiriman diatur lewat WHATSAPP_REMINDER_TIME (bawaan 08:00).
Schedule::command('tasks:remind-whatsapp')
    ->dailyAt((string) config('whatsapp.reminder.time', '08:00'))
    ->withoutOverlapping();

// Denyut kerja asisten AI: melanjutkan pekerjaan yang masih berjalan, menutup
// permintaan persetujuan yang kedaluwarsa, dan meneruskan pekerjaan yang
// tertahan begitu akses yang dibutuhkannya diberikan.
Schedule::command('agent:tick')
    ->everyMinute()
    ->withoutOverlapping();

// Catatan: penjadwalan lama untuk `invoices:mark-overdue` dan `billing:process`
// dihapus — kedua perintah tersebut tidak pernah ada di dalam kode aplikasi ini,
// sehingga penjadwal akan gagal setiap kali dijalankan.
