<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $rawEmail = trim((string) $request->input('email', ''));
        $rawPassword = (string) $request->input('password', '');

        if ($rawEmail === '' && $rawPassword === '') {
            throw ValidationException::withMessages([
                'login' => 'Email dan password wajib diisi.',
                'email' => 'Email wajib diisi.',
                'password' => 'Password wajib diisi.',
            ]);
        }

        $request->merge(['email' => $rawEmail]);

        $validated = $request->validate([
            'email' => ['required', 'string', 'email:filter', 'max:150'],
            'password' => ['required', 'string', 'min:8'],
        ], [
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Masukkan alamat email yang valid.',
            'email.max' => 'Alamat email maksimal 150 karakter.',
            'password.required' => 'Password wajib diisi.',
            'password.min' => 'Password minimal 8 karakter.',
        ]);

        $email = Str::lower($validated['email']);
        $throttleKey = $email.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'email' => "Terlalu banyak percobaan masuk. Silakan coba kembali dalam {$seconds} detik.",
            ]);
        }

        if (! Auth::attempt(['email' => $email, 'password' => $validated['password']], $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'email' => 'Email atau password tidak sesuai.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        /** @var User $user */
        $user = $request->user();

        if (! $user->is_active || ! in_array($user->role, User::ROLES, true)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => ! in_array($user->role, User::ROLES, true)
                    ? 'Peran akun ini sudah tidak aktif pada alur sistem PKDI.'
                    : 'Akun Anda sedang dinonaktifkan. Hubungi manajemen PKDI.',
            ]);
        }

        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now()])->save();

        AuditLogger::record(
            action: 'USER_LOGIN',
            auditable: $user,
            newValues: ['email' => $user->email, 'role' => $user->role],
            actor: $user,
            request: $request,
        );

        return redirect()->intended(route($user->homeRoute()));
    }

    public function destroy(Request $request): RedirectResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user) {
            AuditLogger::record(
                action: 'USER_LOGOUT',
                auditable: $user,
                actor: $user,
                request: $request,
            );
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
