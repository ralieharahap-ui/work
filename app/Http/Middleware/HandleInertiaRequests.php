<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\TaskAlertService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            // 'full' = seluruh modul, 'tasks' = khusus manajemen tugas
            'appMode' => config('app.mode'),
            'appName' => config('app.name'),
            'auth' => [
                'user' => $user
                    ? $user->load('division')->only([
                        'id', 'name', 'email', 'organization_id', 'division_id', 'hierarchy',
                    ]) + ['division' => $user->division ? ['name' => $user->division->name] : null]
                    : null,
                'roles'       => $user ? $user->getRoleNames() : [],
                'permissions' => $user ? $user->getAllPermissions()->pluck('name') : [],
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error'   => fn () => $request->session()->get('error'),
            ],
            'pendingUsersCount' => fn () => ($user && $user->hasRole('super_admin'))
                ? User::where('organization_id', $user->organization_id)->where('is_active', false)->count()
                : 0,
            'taskAlertsCount' => fn () => $user
                ? app(TaskAlertService::class)->forUser($user)['counts']['total']
                : 0,
        ];
    }
}
