<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        return view('settings.edit', [
            'user' => $user,
            'role' => $user->role,
            'roleMeta' => $user->toRoleMeta(),
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'email' => [
                'required',
                'string',
                'email:filter',
                'max:150',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'phone' => ['nullable', 'string', 'max:30'],
        ], [
            'name.required' => 'Nama lengkap wajib diisi.',
            'name.min' => 'Nama lengkap minimal 2 karakter.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Masukkan alamat email yang valid.',
            'email.unique' => 'Alamat email sudah digunakan oleh akun lain.',
        ]);

        $oldValues = [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
        ];

        $user->fill([
            'name' => trim($validated['name']),
            'email' => Str::lower(trim($validated['email'])),
            'phone' => isset($validated['phone']) ? trim($validated['phone']) : $user->phone,
        ]);
        $user->save();

        AuditLogger::record(
            action: 'USER_PROFILE_UPDATED',
            auditable: $user,
            oldValues: $oldValues,
            newValues: [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
            ],
            actor: $user,
            request: $request,
        );

        return back()->with('status', 'Informasi profil akun berhasil diperbarui.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => ['required', 'string', 'current_password'],
            'new_password' => ['required', 'string', 'confirmed', Password::min(8)],
        ], [
            'current_password.required' => 'Kata sandi saat ini wajib diisi.',
            'current_password.current_password' => 'Kata sandi saat ini tidak sesuai.',
            'new_password.required' => 'Kata sandi baru wajib diisi.',
            'new_password.confirmed' => 'Konfirmasi kata sandi baru tidak cocok.',
            'new_password.min' => 'Password minimal 8 karakter.',
        ]);

        $user->forceFill([
            'password' => Hash::make($validated['new_password']),
        ])->save();

        AuditLogger::record(
            action: 'USER_PASSWORD_UPDATED',
            auditable: $user,
            reason: 'Pembaruan kata sandi mandiri melalui Pengaturan Akun',
            actor: $user,
            request: $request,
        );

        return back()->with('status', 'Kata sandi akun berhasil diperbarui.');
    }
}
