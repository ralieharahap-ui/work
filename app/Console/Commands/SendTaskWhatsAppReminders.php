<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Services\TaskWhatsAppReminder;
use App\Services\WhatsApp\WhatsAppGateway;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SendTaskWhatsAppReminders extends Command
{
    protected $signature = 'tasks:remind-whatsapp
        {--org= : Batasi ke satu organisasi (UUID)}
        {--user= : Batasi ke satu PIC (UUID)}
        {--force : Kirim ulang walaupun digest hari ini sudah terkirim}
        {--dry-run : Tampilkan pesan yang akan dikirim tanpa mengirimnya}';

    protected $description = 'Kirim pengingat WhatsApp kepada PIC untuk tugas yang mendekati tenggat atau sudah kadaluarsa.';

    public function handle(TaskWhatsAppReminder $reminder, WhatsAppGateway $gateway): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if (config('whatsapp.reminder.skip_weekend') && Carbon::today()->isWeekend() && ! $this->option('force')) {
            $this->info('Akhir pekan — pengiriman dilewati (WHATSAPP_REMINDER_SKIP_WEEKEND=true).');

            return self::SUCCESS;
        }

        if (! $dryRun && ! $gateway->isEnabled()) {
            $this->warn('WHATSAPP_ENABLED=false — pesan tidak dikirim, hanya dicatat sebagai "skipped".');
        }

        $this->line("Driver: <info>{$gateway->driverName()}</info> · siap kirim: <info>" . ($gateway->isReady() ? 'ya' : 'tidak') . '</info>');

        $organizations = Organization::query()
            ->when($this->option('org'), fn ($q) => $q->whereKey($this->option('org')))
            ->get();

        if ($organizations->isEmpty()) {
            $this->error('Tidak ada organisasi yang cocok.');

            return self::FAILURE;
        }

        $totals = ['sent' => 0, 'failed' => 0, 'skipped' => 0];

        foreach ($organizations as $organization) {
            $summary = $reminder->sendDigests($organization->id, [
                'force'  => (bool) $this->option('force'),
                'dryRun' => $dryRun,
                'only'   => $this->option('user'),
            ]);

            $this->newLine();
            $this->line("<comment>{$organization->name}</comment>");

            if ($summary['rows'] === []) {
                $this->line('  Tidak ada PIC yang perlu diingatkan hari ini.');
                continue;
            }

            if ($dryRun) {
                foreach ($summary['rows'] as $row) {
                    $this->newLine();
                    $this->line("  ── {$row['user']} ({$row['number']}) ──");
                    $this->line('  ' . str_replace("\n", "\n  ", (string) $row['note']));
                }
            } else {
                $this->table(
                    ['PIC', 'Nomor', 'Telat', 'Hari ini', 'Segera', 'Status', 'Catatan'],
                    array_map(fn ($r) => [
                        $r['user'], $r['number'], $r['overdue'], $r['dueToday'], $r['dueSoon'], $r['status'],
                        \Illuminate\Support\Str::limit((string) $r['note'], 60),
                    ], $summary['rows']),
                );
            }

            foreach (['sent', 'failed', 'skipped'] as $key) {
                $totals[$key] += $summary[$key];
            }
        }

        $this->newLine();
        $this->info("Selesai — terkirim: {$totals['sent']}, gagal: {$totals['failed']}, dilewati: {$totals['skipped']}.");

        return self::SUCCESS;
    }
}
