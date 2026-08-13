<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\User;
use App\Services\TaskWhatsAppReminder;
use App\Services\WhatsApp\WhatsAppGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class WhatsAppReminderController extends Controller
{
    public function __construct(
        private readonly TaskWhatsAppReminder $reminder,
        private readonly WhatsAppGateway $gateway,
    ) {
    }

    /** Kirim pengingat untuk satu task kepada PIC-nya (tombol manual). */
    public function remindTask(Task $task): RedirectResponse
    {
        abort_if($task->organization_id !== auth()->user()->organization_id, 403);
        abort_unless(auth()->user()->can('tasks.edit') || auth()->user()->can('tasks.create'), 403);

        if ($task->status === 'Done') {
            return back()->with('error', 'Task ini sudah selesai — tidak perlu diingatkan.');
        }

        // Cegah pengiriman beruntun ke orang yang sama.
        $key = "wa-remind:{$task->id}";

        if (RateLimiter::tooManyAttempts($key, 2)) {
            $wait = RateLimiter::availableIn($key);

            return back()->with('error', "Pengingat untuk task ini baru saja dikirim. Coba lagi dalam {$wait} detik.");
        }

        RateLimiter::hit($key, 600);

        $log = $this->reminder->remindSingleTask($task, auth()->user());

        return match ($log->status) {
            'sent'   => back()->with('success', "Pengingat WhatsApp terkirim ke {$task->pic?->name}."),
            'failed' => back()->with('error', 'Pengingat gagal dikirim: ' . $log->error),
            default  => back()->with('error', 'Pengingat tidak dikirim: ' . $log->error),
        };
    }

    /** Jalankan digest harian untuk seluruh PIC di organisasi ini, sekarang juga. */
    public function runDigest(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->hasAnyRole(['super_admin', 'approval']), 403,
            'Hanya Super Admin atau Manajer yang dapat menjalankan pengingat massal.');

        $summary = $this->reminder->sendDigests(auth()->user()->organization_id, [
            'force' => $request->boolean('force'),
            'actor' => auth()->user(),
        ]);

        if ($summary['rows'] === []) {
            return back()->with('success', 'Tidak ada PIC yang perlu diingatkan saat ini.');
        }

        return back()->with(
            $summary['sent'] > 0 ? 'success' : 'error',
            "Pengingat diproses — terkirim: {$summary['sent']}, gagal: {$summary['failed']}, dilewati: {$summary['skipped']}.",
        );
    }

    /** Pengguna mengatur sendiri nomor WhatsApp & preferensi notifikasinya. */
    public function updateContact(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'whatsapp_number'  => 'nullable|string|max:32',
            'whatsapp_opt_in'  => 'boolean',
        ]);

        /** @var User $user */
        $user = auth()->user();

        $user->update([
            'whatsapp_number' => $data['whatsapp_number'] ?: null,
            'whatsapp_opt_in' => $request->boolean('whatsapp_opt_in'),
        ]);

        return back()->with('success', 'Pengaturan notifikasi WhatsApp Anda tersimpan.');
    }

    /** Kirim pesan uji ke nomor pengguna yang sedang login. */
    public function sendTest(): RedirectResponse
    {
        /** @var User $user */
        $user   = auth()->user();
        $number = $this->reminder->whatsappNumberFor($user);

        if (! $number) {
            return back()->with('error', 'Isi nomor WhatsApp Anda terlebih dahulu.');
        }

        $key = 'wa-test:' . $user->id;

        if (RateLimiter::tooManyAttempts($key, 3)) {
            return back()->with('error', 'Terlalu banyak percobaan. Coba lagi beberapa menit lagi.');
        }

        RateLimiter::hit($key, 300);

        $result = $this->gateway->send(new \App\Services\WhatsApp\WhatsAppMessage(
            to:   $number,
            body: "🔔 *Uji Coba Notifikasi*\n\nHalo *@{$user->name}*, notifikasi WhatsApp dari Workspace Tugas "
                . config('app.name') . " sudah aktif.\n\n_Pesan otomatis — mohon tidak dibalas._",
        ));

        return $result->ok()
            ? back()->with('success', 'Pesan uji terkirim ke ' . $number . '.')
            : back()->with('error', 'Pesan uji gagal: ' . $result->error);
    }
}
