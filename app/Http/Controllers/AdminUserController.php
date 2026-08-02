<?php

namespace App\Http\Controllers;

use App\Models\Division;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

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
            'divisions'     => Division::where('organization_id', $orgId)->orderBy('name')->get(['id', 'name']),
            'roles'         => Role::orderBy('name')->pluck('name'),
        ]);
    }

    /** Super admin membuat akun pengguna baru secara langsung (langsung aktif). */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'            => 'required|string|max:255',
            'email'           => 'required|email|unique:users,email',
            'password'        => 'required|string|min:6',
            'division_id'     => 'nullable|uuid|exists:divisions,id',
            'role'            => 'required|string|exists:roles,name',
            'whatsapp_number' => 'nullable|string|max:32',
        ]);

        $user = User::create([
            'name'            => $data['name'],
            'email'           => $data['email'],
            'password'        => Hash::make($data['password']),
            'division_id'     => $data['division_id'] ?? null,
            'whatsapp_number' => $data['whatsapp_number'] ?: null,
            'organization_id' => auth()->user()->organization_id,
            'is_active'       => true,
        ]);
        $user->assignRole($data['role']);

        return back()->with('success', "Pengguna {$user->name} berhasil dibuat.");
    }

    /** Super admin mengedit data & hak akses (role) pengguna lain. */
    public function update(Request $request, User $user): RedirectResponse
    {
        abort_if($user->organization_id !== auth()->user()->organization_id, 403);

        $data = $request->validate([
            'name'            => 'required|string|max:255',
            'email'           => 'required|email|unique:users,email,' . $user->id,
            'password'        => 'nullable|string|min:6',
            'division_id'     => 'nullable|uuid|exists:divisions,id',
            'role'            => 'required|string|exists:roles,name',
            'whatsapp_number' => 'nullable|string|max:32',
        ]);

        if ($user->hasRole('super_admin') && $data['role'] !== 'super_admin' && $this->isLastSuperAdmin($user)) {
            return back()->with('error', 'Tidak bisa mengubah role — ini adalah Super Admin terakhir di organisasi.');
        }

        $user->update([
            'name'            => $data['name'],
            'email'           => $data['email'],
            'division_id'     => $data['division_id'] ?? null,
            'whatsapp_number' => $data['whatsapp_number'] ?: null,
            ...(!empty($data['password']) ? ['password' => Hash::make($data['password'])] : []),
        ]);
        $user->syncRoles([$data['role']]);

        return back()->with('success', "Data {$user->name} berhasil diperbarui.");
    }

    /** Super admin menghapus akses (akun) pengguna lain sepenuhnya. */
    public function destroy(User $user): RedirectResponse
    {
        abort_if($user->organization_id !== auth()->user()->organization_id, 403);
        abort_if($user->id === auth()->id(), 403, 'Tidak bisa menghapus akun sendiri.');

        if ($user->hasRole('super_admin') && $this->isLastSuperAdmin($user)) {
            return back()->with('error', 'Tidak bisa menghapus Super Admin terakhir di organisasi.');
        }

        $user->delete();

        return back()->with('success', "Akun {$user->name} dihapus.");
    }

    private function isLastSuperAdmin(User $user): bool
    {
        return User::role('super_admin')
            ->where('organization_id', $user->organization_id)
            ->where('id', '!=', $user->id)
            ->doesntExist();
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
