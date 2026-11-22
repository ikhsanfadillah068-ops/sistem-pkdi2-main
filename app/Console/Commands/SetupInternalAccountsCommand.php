<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class SetupInternalAccountsCommand extends Command
{
    protected $signature = 'pkdi:setup-internal-accounts {--password= : Kata sandi kustom untuk seluruh akun internal (opsional)}';

    protected $description = 'Menyiapkan 3 akun peran internal aktif PKDI (Kepala Gudang, Keuangan, Owner) sesuai PRD 2.0.';

    public function handle(): int
    {
        $customPassword = $this->option('password');

        $accounts = [
            [
                'role' => User::ROLE_KEPALA_GUDANG,
                'name' => 'Hendra Wijaya',
                'email' => 'kepalagudang@pkdi.co.id',
                'phone' => '081210000002',
                'default_password' => 'gudang123',
            ],
            [
                'role' => User::ROLE_KEUANGAN,
                'name' => 'Siti Lestari',
                'email' => 'keuangan@pkdi.co.id',
                'phone' => '081210000003',
                'default_password' => 'keuangan123',
            ],
            [
                'role' => User::ROLE_OWNER,
                'name' => 'Kencana Gunawan',
                'email' => 'owner@pkdi.co.id',
                'phone' => '081210000004',
                'default_password' => 'owner123',
            ],
        ];

        foreach ($accounts as $data) {
            $user = User::query()->firstOrNew(['email' => $data['email']]);
            $isNew = ! $user->exists;
            $plainPassword = is_string($customPassword) && $customPassword !== ''
                ? $customPassword
                : $data['default_password'];

            $user->fill([
                'name' => $data['name'],
                'phone' => $data['phone'],
                'password' => Hash::make($plainPassword),
            ]);
            $user->role = $data['role'];
            $user->is_active = true;
            $user->email_verified_at ??= now();
            $user->save();

            if ($isNew) {
                AuditLogger::record(
                    action: 'INTERNAL_ACCOUNT_SETUP',
                    auditable: $user,
                    newValues: ['email' => $user->email, 'role' => $user->role],
                    reason: 'Inisialisasi akun internal awal melalui pkdi:setup-internal-accounts',
                    actor: $user,
                );
            }
        }

        $this->info('Tiga akun internal aktif PKDI berhasil disiapkan.');

        return self::SUCCESS;
    }
}
