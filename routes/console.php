<?php

use Illuminate\Support\Facades\Schedule;

// Pengingat tugas via WhatsApp untuk PIC yang tenggatnya sudah dekat atau lewat.
// Jam pengiriman diatur lewat WHATSAPP_REMINDER_TIME (bawaan 08:00).
Schedule::command('tasks:remind-whatsapp')
    ->dailyAt((string) config('whatsapp.reminder.time', '08:00'))
    ->withoutOverlapping();

// Catatan: penjadwalan lama untuk `invoices:mark-overdue` dan `billing:process`
// dihapus — kedua perintah tersebut tidak pernah ada di dalam kode aplikasi ini,
// sehingga penjadwal akan gagal setiap kali dijalankan.
