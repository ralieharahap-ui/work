<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminUserController extends Controller
{
    public function index(Request $request): Response
    {
        $orgId = auth()->user()->organization_id;

        $users = User::with(['division', 'roles'])
            ->where('organization_id', $orgId)
            ->where('id', '!=', auth()->id())
            ->when($request->status === 'pending', fn ($q) => $q->where('is_active', false))
            ->when($request->status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($request->search, fn ($q) => $q->where('name', 'like', "%{$request->search}%")
                ->orWhere('email', 'like', "%{$request->search}%"))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Admin/Users/Index', [
            'users'         => $users,
            'filters'       => $request->only(['search', 'status']),
            'pendingCount'  => User::where('organization_id', $orgId)->where('is_active', false)->count(),
        ]);
    }

    public function activate(User $user)
    {
        abort_unless($user->organization_id === auth()->user()->organization_id, 403);

        $user->update(['is_active' => true]);

        return back()->with('success', "Akun {$user->name} berhasil diaktifkan.");
    }

    public function deactivate(User $user)
    {
        abort_unless($user->organization_id === auth()->user()->organization_id, 403);
        abort_if($user->id === auth()->id(), 403, 'Tidak bisa menonaktifkan akun sendiri.');

        $user->update(['is_active' => false]);

        return back()->with('success', "Akun {$user->name} dinonaktifkan.");
    }
}
