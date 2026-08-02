<?php

namespace App\Services;

use App\Models\Task;
use App\Models\User;
use App\Models\WhatsappNotification;
use App\Services\WhatsApp\WhatsAppGateway;
use App\Services\WhatsApp\WhatsAppMessage;
use App\Services\WhatsApp\WhatsAppResult;
use App\Support\PhoneNumber;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Menyusun dan mengirim pengingat tugas lewat WhatsApp.
 *
 * Dua bentuk pengiriman:
 *   1. Digest harian per PIC — dijalankan penjadwal (`tasks:remind-whatsapp`).
 *   2. Pengingat satu tugas — dipicu manual dari antarmuka.
 *
 * Setiap pengiriman selalu tercatat di tabel `whatsapp_notifications`, termasuk
 * yang gagal atau dilewati, sehingga riwayatnya bisa ditelusuri dari aplikasi.
 */
class TaskWhatsAppReminder
{
    public function __construct(private readonly WhatsAppGateway $gateway)
    {
    }

    // ── Digest harian ────────────────────────────────────────────────

    /**
     * Kirim digest untuk seluruh PIC pada satu organisasi.
     *
     * @param  array{force?:bool, dryRun?:bool, only?:?string, actor?:?User}  $options
     * @return array{sent:int, failed:int, skipped:int, rows:array<int,array<string,mixed>>}
     */
    public function sendDigests(string $organizationId, array $options = []): array
    {
        $force  = (bool) ($options['force'] ?? false);
        $dryRun = (bool) ($options['dryRun'] ?? false);
        $only   = $options['only'] ?? null;
        $actor  = $options['actor'] ?? null;

        $today   = Carbon::today();
        $summary = ['sent' => 0, 'failed' => 0, 'skipped' => 0, 'rows' => []];

        foreach ($this->digestCandidates($organizationId, $today, $only) as $candidate) {
            /** @var User $user */
            $user    = $candidate['user'];
            $buckets = $candidate['buckets'];

            $dedupeKey = 'digest:' . $today->toDateString();
            $body      = $this->buildDigestBody($user, $buckets, $today, false);

            if ($dryRun) {
                $summary['rows'][] = $this->row($user, $buckets, 'dry-run', $body);
                continue;
            }

            if (! $force && $this->alreadySent($user->id, $dedupeKey)) {
                $summary['skipped']++;
                $summary['rows'][] = $this->row($user, $buckets, 'skipped', 'Digest hari ini sudah terkirim.');
                continue;
            }

            $log = $this->deliverToUser(
                user:      $user,
                body:      $body,
                groupBody: $this->buildDigestBody($user, $buckets, $today, true),
                taskIds:   $this->bucketTaskIds($buckets),
                type:      'digest',
                dedupeKey: $dedupeKey,
                actor:     $actor,
            );

            $summary[$log->status === 'sent' ? 'sent' : ($log->status === 'failed' ? 'failed' : 'skipped')]++;
            $summary['rows'][] = $this->row($user, $buckets, $log->status, $log->error);
        }

        return $summary;
    }

    /**
     * PIC yang layak menerima digest hari ini, beserta rincian tugasnya.
     *
     * @return array<int,array{user:User, buckets:array<string,Collection<int,Task>>}>
     */
    public function digestCandidates(string $organizationId, ?Carbon $today = null, ?string $onlyUserId = null): array
    {
        $today  = $today ? $today->copy()->startOfDay() : Carbon::today();
        $window = $this->reminderWindowDays();

        $tasks = Task::with(['project:id,title', 'pic'])
            ->where('organization_id', $organizationId)
            ->where('status', '!=', 'Done')
            ->whereNotNull('pic_id')
            ->whereNotNull('deadline')
            ->when($onlyUserId, fn ($q) => $q->where('pic_id', $onlyUserId))
            ->where('deadline', '<=', $today->copy()->addDays($window)->toDateString())
            ->orderBy('deadline')
            ->get();

        $candidates = [];

        foreach ($tasks->groupBy('pic_id') as $picTasks) {
            /** @var User|null $user */
            $user = $picTasks->first()->pic;

            if (! $user || ! $user->is_active) {
                continue;
            }

            $buckets = $this->bucketize($picTasks, $today);

            if (! $this->shouldNotify($buckets, $today)) {
                continue;
            }

            $candidates[] = ['user' => $user, 'buckets' => $buckets];
        }

        usort($candidates, fn ($a, $b) => strcmp($a['user']->name, $b['user']->name));

        return $candidates;
    }

    // ── Pengingat satu tugas ─────────────────────────────────────────

    /** Kirim pengingat untuk satu tugas kepada PIC-nya (tombol manual di aplikasi). */
    public function remindSingleTask(Task $task, ?User $actor = null): WhatsappNotification
    {
        $task->loadMissing(['pic', 'project:id,title']);
        $user = $task->pic;

        if (! $user) {
            return $this->log([
                'organization_id' => $task->organization_id,
                'user_id'         => null,
                'triggered_by'    => $actor?->id,
                'channel'         => 'personal',
                'type'            => 'task',
                'driver'          => $this->gateway->driverName(),
                'recipient'       => null,
                'body'            => "Pengingat untuk task \"{$task->title}\" tidak dapat dikirim.",
                'task_ids'        => [$task->id],
                'status'          => 'failed',
                'error'           => 'Task ini belum memiliki PIC.',
            ]);
        }

        $today = Carbon::today();

        return $this->deliverToUser(
            user:      $user,
            body:      $this->buildSingleTaskBody($task, $user, $today, $actor, false),
            groupBody: $this->buildSingleTaskBody($task, $user, $today, $actor, true),
            taskIds:   [$task->id],
            type:      'task',
            dedupeKey: null,
            actor:     $actor,
        );
    }

    // ── Pengiriman & pencatatan ──────────────────────────────────────

    /**
     * Kirim ke chat pribadi PIC, lalu (bila diaktifkan) salin ke grup tim
     * dengan mention nomor PIC agar tetap memanggil orangnya.
     *
     * @param  string[]  $taskIds
     */
    private function deliverToUser(
        User $user,
        string $body,
        string $groupBody,
        array $taskIds,
        string $type,
        ?string $dedupeKey,
        ?User $actor,
    ): WhatsappNotification {
        $number = $this->whatsappNumberFor($user);
        $driver = $this->gateway->driverName();

        $base = [
            'organization_id' => $user->organization_id,
            'user_id'         => $user->id,
            'triggered_by'    => $actor?->id,
            'channel'         => 'personal',
            'type'            => $type,
            'driver'          => $driver,
            'recipient'       => $number,
            'body'            => $body,
            'task_ids'        => $taskIds,
            'dedupe_key'      => $dedupeKey,
        ];

        if ($number === null) {
            $personal = $this->log($base + [
                'status' => 'failed',
                'error'  => "Nomor WhatsApp {$user->name} belum diisi atau tidak valid.",
            ]);
        } elseif (! $user->whatsapp_opt_in) {
            $personal = $this->log($base + [
                'status' => 'skipped',
                'error'  => "{$user->name} menonaktifkan notifikasi WhatsApp.",
            ]);
        } else {
            $result   = $this->gateway->send(new WhatsAppMessage(to: $number, body: $body));
            $personal = $this->log($base + $this->resultColumns($result));
        }

        $this->deliverToGroup($user, $groupBody, $taskIds, $type, $dedupeKey, $actor, $number);

        return $personal;
    }

    /** @param string[] $taskIds */
    private function deliverToGroup(
        User $user,
        string $groupBody,
        array $taskIds,
        string $type,
        ?string $dedupeKey,
        ?User $actor,
        ?string $mentionNumber,
    ): void {
        $groupId = config('whatsapp.group.id');

        if (! config('whatsapp.group.enabled') || ! $groupId) {
            return;
        }

        $result = $this->gateway->send(new WhatsAppMessage(
            to:       $groupId,
            body:     $groupBody,
            mentions: $mentionNumber ? [$mentionNumber] : [],
            isGroup:  true,
        ));

        $this->log([
            'organization_id' => $user->organization_id,
            'user_id'         => $user->id,
            'triggered_by'    => $actor?->id,
            'channel'         => 'group',
            'type'            => $type,
            'driver'          => $this->gateway->driverName(),
            'recipient'       => $groupId,
            'body'            => $groupBody,
            'task_ids'        => $taskIds,
            'dedupe_key'      => $dedupeKey ? $dedupeKey . ':group' : null,
        ] + $this->resultColumns($result));
    }

    private function resultColumns(WhatsAppResult $result): array
    {
        return [
            'status'    => $result->status,
            'error'     => $result->error,
            'reference' => $result->reference,
            'sent_at'   => $result->ok() ? now() : null,
        ];
    }

    private function log(array $attributes): WhatsappNotification
    {
        return WhatsappNotification::create($attributes);
    }

    private function alreadySent(string $userId, string $dedupeKey): bool
    {
        return WhatsappNotification::where('user_id', $userId)
            ->where('dedupe_key', $dedupeKey)
            ->where('status', 'sent')
            ->exists();
    }

    // ── Penyusunan pesan ─────────────────────────────────────────────

    /**
     * @param  array<string,Collection<int,Task>>  $buckets
     */
    private function buildDigestBody(User $user, array $buckets, Carbon $today, bool $forGroup): string
    {
        $total = collect($buckets)->sum(fn (Collection $c) => $c->count());
        $lines = [];

        $lines[] = '🔔 *PENGINGAT TUGAS — ' . $this->companyName() . '*';
        $lines[] = '';
        $lines[] = 'Halo ' . $this->mention($user, $forGroup) . ' 👋';
        $lines[] = "Ada *{$total} tugas* atas nama Anda yang perlu segera diselesaikan.";

        $sections = [
            ['key' => 'overdue',  'title' => '🔴 *TERLAMBAT*'],
            ['key' => 'dueToday', 'title' => '🟠 *JATUH TEMPO HARI INI*'],
            ['key' => 'dueSoon',  'title' => '🟡 *MENDEKATI TENGGAT*'],
        ];

        $max = max(1, (int) config('whatsapp.reminder.max_tasks_per_group', 8));

        foreach ($sections as $section) {
            $tasks = $buckets[$section['key']] ?? collect();

            if ($tasks->isEmpty()) {
                continue;
            }

            $lines[] = '';
            $lines[] = $section['title'] . ' (' . $tasks->count() . ')';

            foreach ($tasks->take($max)->values() as $i => $task) {
                $lines[] = ($i + 1) . '. ' . $task->title;
                $lines[] = '   ' . $this->taskMeta($task, $today);

                if ($task->project?->title) {
                    $lines[] = '   Proyek: ' . $task->project->title;
                }
            }

            if ($tasks->count() > $max) {
                $lines[] = '   … dan ' . ($tasks->count() - $max) . ' tugas lainnya.';
            }
        }

        $lines[] = '';
        $lines[] = 'Mohon perbarui status dan unggah *dokumen bukti (evidence)* sebagai syarat penutupan tugas:';
        $lines[] = $this->tasksUrl();
        $lines[] = '';
        $lines[] = '_Pesan otomatis dari Workspace Tugas ' . $this->companyName() . ' — mohon tidak dibalas._';

        return implode("\n", $lines);
    }

    private function buildSingleTaskBody(Task $task, User $user, Carbon $today, ?User $actor, bool $forGroup): string
    {
        $lines = [];

        $lines[] = '🔔 *PENGINGAT TUGAS — ' . $this->companyName() . '*';
        $lines[] = '';
        $lines[] = 'Halo ' . $this->mention($user, $forGroup) . ' 👋';
        $lines[] = $actor
            ? "*{$actor->name}* mengingatkan Anda untuk segera menyelesaikan tugas berikut:"
            : 'Mohon segera selesaikan tugas berikut:';
        $lines[] = '';
        $lines[] = '📌 *' . $task->title . '*';
        $lines[] = $this->taskMeta($task, $today);

        if ($task->project?->title) {
            $lines[] = 'Proyek: ' . $task->project->title;
        }

        if (filled($task->description)) {
            $lines[] = '';
            $lines[] = '_' . \Illuminate\Support\Str::limit(strip_tags((string) $task->description), 300) . '_';
        }

        $lines[] = '';
        $lines[] = 'Perbarui status dan lampirkan *dokumen bukti (evidence)* di:';
        $lines[] = $this->tasksUrl();
        $lines[] = '';
        $lines[] = '_Pesan otomatis dari Workspace Tugas ' . $this->companyName() . ' — mohon tidak dibalas._';

        return implode("\n", $lines);
    }

    /**
     * Sebutan nama PIC di dalam pesan.
     *
     * Di chat pribadi WhatsApp tidak ada mekanisme tag, jadi namanya ditulis
     * langsung sebagai "@Nama". Di grup, nomor PIC dipakai sebagai token tag
     * sungguhan sehingga notifikasi masuk ke ponsel yang bersangkutan.
     */
    private function mention(User $user, bool $forGroup): string
    {
        $number = $this->whatsappNumberFor($user);

        if ($forGroup && $number) {
            return '@' . $number . ' (*' . $user->name . '*)';
        }

        return '*@' . $user->name . '*';
    }

    private function taskMeta(Task $task, Carbon $today): string
    {
        $parts = [];

        if ($task->deadline) {
            $days = $this->daysUntil($today, $task->deadline);

            $parts[] = 'Tenggat ' . $task->deadline->copy()->locale('id')->translatedFormat('d M Y');
            $parts[] = match (true) {
                $days < 0   => 'terlambat ' . abs($days) . ' hari',
                $days === 0 => 'jatuh tempo hari ini',
                default     => 'sisa ' . $days . ' hari',
            };
        }

        $parts[] = 'Prioritas ' . $task->priority;
        $parts[] = 'Status ' . $task->status;

        return implode(' · ', $parts);
    }

    /**
     * Selisih hari kalender menuju tenggat: negatif bila sudah lewat, 0 bila
     * jatuh tempo hari ini. Dihitung dari tanggal murni supaya bebas dari
     * pengaruh jam dan pembulatan pecahan Carbon 3.
     */
    private function daysUntil(Carbon $today, Carbon $deadline): int
    {
        return (int) Carbon::parse($today->toDateString())
            ->startOfDay()
            ->diffInDays(Carbon::parse($deadline->toDateString())->startOfDay(), false);
    }

    // ── Pembantu ─────────────────────────────────────────────────────

    /**
     * @param  Collection<int,Task>  $tasks
     * @return array<string,Collection<int,Task>>
     */
    private function bucketize(Collection $tasks, Carbon $today): array
    {
        $window = $this->reminderWindowDays();
        $rank   = array_flip(['Urgent', 'High', 'Medium', 'Low']);

        return [
            'overdue'  => $tasks->filter(fn (Task $t) => $this->daysUntil($today, $t->deadline) < 0)->sortBy('deadline')->values(),
            'dueToday' => $tasks->filter(fn (Task $t) => $this->daysUntil($today, $t->deadline) === 0)
                ->sortBy(fn (Task $t) => $rank[$t->priority] ?? 99)->values(),
            'dueSoon'  => $tasks->filter(function (Task $t) use ($today, $window) {
                $days = $this->daysUntil($today, $t->deadline);

                return $days > 0 && $days <= $window;
            })->sortBy('deadline')->values(),
        ];
    }

    /**
     * Digest hanya dikirim bila ada tugas yang memang jatuh pada hari pemicu:
     * sisa hari tercantum di `WHATSAPP_REMINDER_DAYS`, atau keterlambatannya
     * kelipatan `WHATSAPP_OVERDUE_EVERY_DAYS`.
     *
     * @param  array<string,Collection<int,Task>>  $buckets
     */
    private function shouldNotify(array $buckets, Carbon $today): bool
    {
        $daysBefore = $this->reminderDays();
        $everyDays  = max(1, (int) config('whatsapp.reminder.overdue_every_days', 1));

        foreach ($buckets['overdue'] as $task) {
            $late = abs($this->daysUntil($today, $task->deadline));

            if ($late > 0 && $late % $everyDays === 0) {
                return true;
            }
        }

        foreach (['dueToday', 'dueSoon'] as $key) {
            foreach ($buckets[$key] as $task) {
                if (in_array($this->daysUntil($today, $task->deadline), $daysBefore, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @param array<string,Collection<int,Task>> $buckets @return string[] */
    private function bucketTaskIds(array $buckets): array
    {
        return collect($buckets)->flatten(1)->pluck('id')->all();
    }

    /** @param array<string,Collection<int,Task>> $buckets */
    private function row(User $user, array $buckets, string $status, ?string $note = null): array
    {
        return [
            'user'     => $user->name,
            'number'   => PhoneNumber::pretty($this->whatsappNumberFor($user)) ?? '—',
            'overdue'  => $buckets['overdue']->count(),
            'dueToday' => $buckets['dueToday']->count(),
            'dueSoon'  => $buckets['dueSoon']->count(),
            'status'   => $status,
            'note'     => $note,
        ];
    }

    /** @return int[] */
    private function reminderDays(): array
    {
        $days = (array) config('whatsapp.reminder.days_before', [3, 1, 0]);

        return $days === [] ? [0] : array_map('intval', $days);
    }

    private function reminderWindowDays(): int
    {
        return max($this->reminderDays());
    }

    public function whatsappNumberFor(User $user): ?string
    {
        return PhoneNumber::normalize($user->whatsapp_number)
            ?? PhoneNumber::normalize($user->phone);
    }

    private function companyName(): string
    {
        return (string) config('app.name', 'PT Geosys Energi Prima');
    }

    private function tasksUrl(): string
    {
        return rtrim((string) config('app.url'), '/') . '/tasks';
    }
}
