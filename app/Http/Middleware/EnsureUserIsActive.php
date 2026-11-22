<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && (! $user->is_active || ! in_array($user->role, User::ROLES, true))) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => ! in_array($user->role, User::ROLES, true)
                    ? 'Peran akun ini sudah tidak aktif pada alur sistem PKDI.'
                    : 'Akun Anda sedang dinonaktifkan. Hubungi manajemen PKDI.',
            ]);
        }

        return $next($request);
    }
}
