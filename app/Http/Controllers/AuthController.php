<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    public function showLogin(): Response|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Auth/Login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            AuditService::log('login', 'auth', 'User', Auth::id(), null, ['email' => Auth::user()->email]);

            return redirect()->intended(route('dashboard'))
                ->with('success', 'Selamat datang kembali, ' . Auth::user()->name . '!');
        }

        return back()->withErrors([
            'email' => 'Kredensial yang diberikan tidak cocok dengan data kami.',
        ])->onlyInput('email');
    }

    public function quickLogin(Request $request): RedirectResponse
    {
        $request->validate(['role' => 'required|in:owner,admin_keuangan,admin_jaringan']);
        $user = User::where('role', $request->role)->where('is_active', true)->first();

        if ($user) {
            Auth::login($user);
            $request->session()->regenerate();

            AuditService::log('quick_login', 'auth', 'User', $user->id, null, ['role' => $user->role]);

            return redirect()->route('dashboard')
                ->with('success', "Masuk sebagai {$user->name} ({$user->role}).");
        }

        return back()->with('error', 'User demo tidak ditemukan.');
    }

    public function logout(Request $request): RedirectResponse
    {
        if (Auth::check()) {
            AuditService::log('logout', 'auth', 'User', Auth::id());
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('info', 'Anda telah keluar dari sistem.');
    }
}
