<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegisterUserRequest;
use App\Models\Organization;
use App\Models\Division;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    public function showLogin(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (!$user || !$user->is_active) {
            return back()->withErrors(['email' => 'Akun tidak aktif atau tidak ditemukan.']);
        }

        if (!Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Email atau password salah.']);
        }

        $user->update(['last_login_at' => now()]);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }

    public function showRegister(): Response
    {
        $divisions = Division::with('parent')->orderBy('name')->get();
        return Inertia::render('Auth/Register', compact('divisions'));
    }

    public function register(RegisterUserRequest $request): RedirectResponse
    {
        $user = User::create([
            ...$request->safe()->except('password'),
            'password'        => Hash::make($request->password),
            'organization_id' => Organization::where('slug', 'pt-gep')->value('id'),
            'is_active'       => false, // menunggu aktivasi admin
        ]);

        $roleMap = [
            'staff'       => 'drafter',
            'manager'     => 'reviewer',
            'director'    => 'approval',
            'stakeholder' => 'external',
        ];
        $user->assignRole($roleMap[$user->hierarchy] ?? 'external');

        return redirect()->route('login')->with('success', 'Akun dibuat. Menunggu aktivasi administrator.');
    }
}
