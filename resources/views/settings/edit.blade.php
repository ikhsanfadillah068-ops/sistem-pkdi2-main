<x-layouts.admin title="Pengaturan Akun" group="Akun Pengguna" :role="$role">
    <x-ui.page-header title="Pengaturan Akun" />

    <div class="grid gap-6 lg:grid-cols-2">
        <x-ui.card title="Informasi Profil Akun">
            <form action="{{ route('settings.profile.update') }}" method="POST" class="space-y-4" novalidate data-validate-form data-prevent-double-submit>
                @csrf
                @method('PUT')
                <x-ui.field label="Peran Pengguna" name="role_display">
                    <x-ui.input name="role_display" :value="$roleMeta['label'] . ' - ' . $roleMeta['company']" disabled />
                </x-ui.field>

                <x-ui.field label="Nama Lengkap" name="name" :error="$errors->first('name')" required>
                    <x-ui.input name="name" minlength="2" :value="old('name', $roleMeta['user_name'])" :invalid="$errors->has('name')" required />
                </x-ui.field>

                <x-ui.field label="Alamat Email" name="email" :error="$errors->first('email')" required>
                    <x-ui.input name="email" type="email" :value="old('email', $roleMeta['user_email'])" :invalid="$errors->has('email')" required />
                </x-ui.field>

                <div class="flex justify-end border-t pt-4">
                    <x-ui.button type="submit">
                        <x-ui.icon name="check" />
                        Simpan Perubahan Profil
                    </x-ui.button>
                </div>
            </form>
        </x-ui.card>

        <x-ui.card title="Ubah Kata Sandi">
            <form action="{{ route('settings.password.update') }}" method="POST" class="space-y-4" novalidate data-validate-form data-prevent-double-submit>
                @csrf
                @method('PUT')
                <x-ui.field label="Kata Sandi Saat Ini" name="current_password" :error="$errors->first('current_password')" required>
                    <x-ui.input name="current_password" type="password" placeholder="Masukkan kata sandi lama" :invalid="$errors->has('current_password')" required />
                </x-ui.field>

                <x-ui.field label="Kata Sandi Baru" name="new_password" help="Minimal 8 karakter." :error="$errors->first('new_password')" required>
                    <x-ui.input name="new_password" type="password" minlength="8" placeholder="Masukkan kata sandi baru" :invalid="$errors->has('new_password')" required />
                </x-ui.field>

                <x-ui.field label="Konfirmasi Kata Sandi Baru" name="new_password_confirmation" required>
                    <x-ui.input name="new_password_confirmation" type="password" minlength="8" data-match="new_password" placeholder="Ulangi kata sandi baru" :invalid="$errors->has('new_password')" required />
                </x-ui.field>

                <div class="flex justify-end border-t pt-4">
                    <x-ui.button type="submit" variant="outline">
                        <x-ui.icon name="lock" />
                        Perbarui Kata Sandi
                    </x-ui.button>
                </div>
            </form>
        </x-ui.card>
    </div>

    @if (($role ?? '') === 'client' && isset($company) && $company)
        <x-ui.card title="Alamat & Titik Lokasi Pabrik Perusahaan" description="Alamat ini digunakan secara otomatis sebagai lokasi penjemputan pesanan baru Anda.">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="space-y-1.5 text-sm">
                    <p class="font-semibold text-foreground">{{ $company->company_name }}</p>
                    <p class="text-muted-foreground leading-relaxed">{{ $company->address ?: 'Alamat perusahaan belum dilengkapi.' }}</p>
                    @if ($company->latitude !== null && $company->longitude !== null)
                        <p class="text-xs text-muted-foreground font-mono tabular-nums">
                            Koordinat: {{ number_format((float) $company->latitude, 6, '.', '') }}, {{ number_format((float) $company->longitude, 6, '.', '') }}
                        </p>
                    @endif
                </div>
                <div class="flex flex-wrap gap-2 shrink-0">
                    @if ($company->resolvedGoogleMapsUrl())
                        <a href="{{ $company->resolvedGoogleMapsUrl() }}" target="_blank" rel="noopener" class="btn btn-outline btn-sm">
                            <x-ui.icon name="map-pin" class="h-3.5 w-3.5" />
                            Lihat Titik Lokasi
                        </a>
                    @endif
                    <a href="{{ route('client.company') }}" class="btn btn-primary btn-sm">
                        <x-ui.icon name="edit" class="h-3.5 w-3.5" />
                        Ubah Lokasi & Peta Pabrik
                    </a>
                </div>
            </div>
        </x-ui.card>
    @endif
</x-layouts.admin>
