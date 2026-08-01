<?php

namespace App\Services;

use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Carbon;

class TaskAlertService
{
    /** Jumlah hari sebelum deadline untuk dianggap "mendekati tenggat". */
    public const DUE_SOON_DAYS = 3;

    /**
     * Hitung peringatan tugas milik seorang user: kadaluarsa, mendekati deadline,
     * dan prioritas tinggi/mendesak yang masih terbuka.
     */
    public function forUser(User $user): array
    {
        $today  = Carbon::today();
        $soonAt = $today->copy()->addDays(self::DUE_SOON_DAYS);

        $openTasks = Task::with(['project:id,title'])
            ->where('organization_id', $user->organization_id)
            ->where('pic_id', $user->id)
            ->where('status', '!=', 'Done')
            ->get();

        $overdue = $openTasks->filter(fn (Task $t) => $t->deadline && $t->deadline->lt($today))
            ->sortBy('deadline')->values();

        $dueSoon = $openTasks->filter(fn (Task $t) => $t->deadline && $t->deadline->gte($today) && $t->deadline->lte($soonAt))
            ->sortBy('deadline')->values();

        $urgent = $openTasks->filter(fn (Task $t) => in_array($t->priority, ['High', 'Urgent'], true)
                && !$overdue->contains('id', $t->id) && !$dueSoon->contains('id', $t->id))
            ->sortByDesc('priority')->values();

        return [
            'overdue'  => $overdue->take(10)->values(),
            'dueSoon'  => $dueSoon->take(10)->values(),
            'urgent'   => $urgent->take(10)->values(),
            'counts'   => [
                'overdue' => $overdue->count(),
                'dueSoon' => $dueSoon->count(),
                'urgent'  => $urgent->count(),
                'total'   => $overdue->count() + $dueSoon->count() + $urgent->count(),
            ],
        ];
    }
}
