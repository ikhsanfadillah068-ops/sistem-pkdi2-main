<?php

namespace Database\Seeders;

use App\Models\ClientCompany;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(MasterDataSeeder::class);

        // Nonaktifkan akun role warisan Satpam secara non-destruktif agar riwayat lama tetap utuh.
        User::query()->where('role', User::ROLE_SATPAM)->update(['is_active' => false]);

        $this->upsertInternalUser(
            'Hendra Wijaya',
            'kepalagudang@pkdi.co.id',
            User::ROLE_KEPALA_GUDANG,
            '081210000002',
            Hash::make('gudang123')
        );
        $this->upsertInternalUser(
            'Siti Lestari',
            'keuangan@pkdi.co.id',
            User::ROLE_KEUANGAN,
            '081210000003',
            Hash::make('keuangan123')
        );
        $this->upsertInternalUser(
            'Kencana Gunawan',
            'owner@pkdi.co.id',
            User::ROLE_OWNER,
            '081210000004',
            Hash::make('owner123')
        );

        $company = ClientCompany::query()->updateOrCreate(
            ['company_code' => 'CLI-PKDI-0001'],
            [
                'company_name' => 'PT Maju Jaya Kimia',
                'pic_name' => 'Budi Santoso',
                'email' => 'client@pkdi.co.id',
                'phone' => '021-89104421',
                'address' => 'Kawasan Industri Jababeka Tahap II Blok C-14, Cikarang, Bekasi',
                'verification_status' => 'ACTIVE',
            ]
        );

        $this->upsertClientUser(
            'Budi Santoso',
            'client@pkdi.co.id',
            $company->id,
            '081280000001',
            Hash::make('client123')
        );
    }

    private function upsertInternalUser(
        string $name,
        string $email,
        string $role,
        string $phone,
        string $hashedPassword,
    ): User {
        $user = User::query()->firstOrNew(['email' => $email]);
        $user->fill([
            'client_company_id' => null,
            'name' => $name,
            'phone' => $phone,
            'password' => $hashedPassword,
        ]);
        $user->role = $role;
        $user->is_active = true;
        $user->email_verified_at = now();
        $user->save();

        return $user;
    }

    private function upsertClientUser(
        string $name,
        string $email,
        int $companyId,
        string $phone,
        string $hashedPassword,
    ): User {
        $user = User::query()->firstOrNew(['email' => $email]);
        $user->fill([
            'client_company_id' => $companyId,
            'name' => $name,
            'phone' => $phone,
            'password' => $hashedPassword,
        ]);
        $user->role = User::ROLE_CLIENT;
        $user->is_active = true;
        $user->email_verified_at = now();
        $user->save();

        return $user;
    }
}
