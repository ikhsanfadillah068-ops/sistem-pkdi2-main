<x-layouts.guest title="Registrasi Akun Client">
<div class="relative flex min-h-screen flex-col bg-[#0B1120] bg-cover bg-center bg-no-repeat" style="background-image: url('{{ asset('Assets/img/background.jpeg') }}');">
    <div class="pointer-events-none absolute inset-0 bg-[#0B1120]/75"></div>

    <x-ui.news-ticker />

    <div class="relative z-10 flex flex-1 items-center justify-center px-4 py-8 sm:px-6 sm:py-12">
        <div class="w-full max-w-xl rounded-2xl bg-white p-6 text-zinc-900 shadow-2xl ring-1 ring-black/5 sm:p-8">
            <div class="flex items-center justify-between gap-4 border-b border-zinc-200 pb-5">
                <div class="flex items-center gap-3">
                    <img src="{{ asset('Assets/img/logo.png') }}" alt="Logo PT Pulo Kencana Drum Industri" class="h-11 w-11 shrink-0 rounded-full border border-zinc-200 bg-white object-contain p-1 shadow-xs" />
                    <div>
                        <h1 class="text-lg font-bold tracking-tight text-zinc-900">Registrasi Akun Client</h1>
                        <p class="text-xs font-medium text-[#800000]">{{ config('pkdi.brand.company') }}</p>
                    </div>
                </div>
                <a href="{{ route('login') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-zinc-600 hover:text-zinc-900">
                    <x-ui.icon name="arrow-left" class="h-3.5 w-3.5" />
                    Halaman Masuk
                </a>
            </div>

            <form action="{{ route('register.store') }}" method="POST" class="mt-5 space-y-4" novalidate data-validate-form data-prevent-double-submit>
                @csrf
                <x-ui.field label="Nama Perusahaan (PT / CV)" name="company_name" :error="$errors->first('company_name')" required>
                    <x-ui.input name="company_name" minlength="3" :value="old('company_name')" placeholder="Nama perusahaan" :invalid="$errors->has('company_name')" required />
                </x-ui.field>

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.field label="Nama Penanggung Jawab (PIC)" name="pic_name" :error="$errors->first('pic_name')" required>
                        <x-ui.input name="pic_name" minlength="2" :value="old('pic_name')" placeholder="Nama lengkap PIC" :invalid="$errors->has('pic_name')" required />
                    </x-ui.field>
                    <x-ui.field label="Nomor Telepon / WhatsApp" name="phone" :error="$errors->first('phone')" required>
                        <x-ui.input name="phone" type="tel" minlength="8" :value="old('phone')" placeholder="0812xxxxxxxx" :invalid="$errors->has('phone')" required />
                    </x-ui.field>
                </div>

                <x-ui.field label="Alamat Lengkap Perusahaan" name="address" :error="$errors->first('address')" required>
                    <x-ui.textarea name="address" rows="2" minlength="10" placeholder="Alamat pabrik atau kantor perusahaan" :invalid="$errors->has('address')" required>{{ old('address') }}</x-ui.textarea>
                </x-ui.field>

                <x-ui.field label="Email Login" name="email" :error="$errors->first('email')" required>
                    <x-ui.input name="email" type="email" :value="old('email')" placeholder="pic@perusahaan.co.id" autocomplete="email" :invalid="$errors->has('email')" required />
                </x-ui.field>

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.field label="Kata Sandi" name="password" help="Minimal 8 karakter." :error="$errors->first('password')" required>
                        <x-ui.input name="password" type="password" minlength="8" autocomplete="new-password" :invalid="$errors->has('password')" required />
                    </x-ui.field>
                    <x-ui.field label="Konfirmasi Kata Sandi" name="password_confirmation" required>
                        <x-ui.input name="password_confirmation" type="password" minlength="8" autocomplete="new-password" data-match="password" :invalid="$errors->has('password')" required />
                    </x-ui.field>
                </div>

                <div class="flex flex-col-reverse gap-3 border-t border-zinc-200 pt-4 sm:flex-row sm:items-center sm:justify-between">
                    <a href="{{ route('login') }}" class="text-center text-xs font-medium text-zinc-600 hover:text-zinc-900">Sudah punya akun? Masuk Sistem</a>
                    <button type="submit" class="inline-flex cursor-pointer items-center justify-center gap-2 rounded-full bg-[#800000] px-6 py-2.5 text-sm font-semibold text-white shadow-md transition hover:bg-[#660000]">
                        <x-ui.icon name="userplus" />
                        Daftar Akun Client
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
</x-layouts.guest>
