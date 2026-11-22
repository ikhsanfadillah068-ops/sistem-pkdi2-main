<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ClientCompany;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class RegisteredClientController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'company_name' => ['required', 'string', 'min:3', 'max:200'],
            'pic_name' => ['required', 'string', 'min:2', 'max:150'],
            'phone' => ['required', 'string', 'min:8', 'max:30'],
            'address' => ['required', 'string', 'min:10', 'max:1000'],
            'npwp' => ['nullable', 'string', 'max:50'],
            'email' => ['required', 'string', 'email:filter', 'max:150', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [
            'company_name.required' => 'Nama perusahaan (PT / CV) wajib diisi.',
            'company_name.min' => 'Nama perusahaan minimal 3 karakter.',
            'pic_name.required' => 'Nama penanggung jawab (PIC) wajib diisi.',
            'pic_name.min' => 'Nama penanggung jawab minimal 2 karakter.',
            'phone.required' => 'Nomor telepon / WhatsApp wajib diisi.',
            'phone.min' => 'Nomor telepon minimal 8 digit.',
            'address.required' => 'Alamat lengkap perusahaan wajib diisi.',
            'address.min' => 'Alamat perusahaan minimal 10 karakter.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Masukkan alamat email yang valid.',
            'email.unique' => 'Email ini sudah terdaftar pada sistem PKDI.',
            'password.required' => 'Password wajib diisi.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
            'password.min' => 'Password minimal 8 karakter.',
        ]);

        $email = Str::lower(trim($validated['email']));
        $companyName = trim($validated['company_name']);
        $picName = trim($validated['pic_name']);
        $phone = trim($validated['phone']);
        $address = trim($validated['address']);

        $user = DB::transaction(function () use ($request, $validated, $email, $companyName, $picName, $phone, $address): User {
            $nextNumber = ClientCompany::query()->count() + 1;
            $companyCode = sprintf('CLI-%s-%04d', now()->format('ym'), $nextNumber);

            while (ClientCompany::query()->where('company_code', $companyCode)->exists()) {
                $nextNumber++;
                $companyCode = sprintf('CLI-%s-%04d', now()->format('ym'), $nextNumber);
            }

            $company = ClientCompany::query()->create([
                'company_code' => $companyCode,
                'company_name' => $companyName,
                'pic_name' => $picName,
                'email' => $email,
                'phone' => $phone,
                'address' => $address,
                'npwp' => isset($validated['npwp']) ? trim($validated['npwp']) : null,
                'verification_status' => 'ACTIVE',
            ]);

            $newUser = new User([
                'client_company_id' => $company->id,
                'name' => $picName,
                'email' => $email,
                'phone' => $phone,
                'password' => Hash::make($validated['password']),
                'last_login_at' => now(),
            ]);

            // Paksa role = client di sisi server; jangan pernah percaya input role dari browser.
            $newUser->role = User::ROLE_CLIENT;
            $newUser->is_active = true;
            $newUser->email_verified_at = now();
            $newUser->save();

            AuditLogger::record(
                action: 'CLIENT_REGISTERED',
                auditable: $newUser,
                newValues: [
                    'company_code' => $company->company_code,
                    'company_name' => $company->company_name,
                    'email' => $newUser->email,
                    'role' => $newUser->role,
                ],
                reason: 'Registrasi mandiri akun perusahaan Client baru',
                actor: $newUser,
                request: $request,
            );

            return $newUser;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()
            ->route('client.dashboard')
            ->with('status', 'Registrasi akun perusahaan berhasil. Selamat datang di Portal Client PKDI.');
    }
}
