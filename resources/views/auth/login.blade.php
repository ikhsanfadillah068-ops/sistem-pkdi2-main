<x-layouts.guest title="Masuk">
<div class="relative flex min-h-screen flex-col bg-[#0B1120] bg-cover bg-center bg-no-repeat" style="background-image: url('{{ asset('Assets/img/background.jpeg') }}');">
    <div class="pointer-events-none absolute inset-0 bg-[#0B1120]/75"></div>

    <x-ui.news-ticker />

    <div class="relative z-10 flex flex-1 items-center justify-center px-4 py-10 sm:px-6">
        <div class="w-full max-w-[400px] rounded-2xl bg-white p-7 text-zinc-900 shadow-2xl ring-1 ring-black/5 sm:p-8">
            <div class="flex items-center gap-3">
                <img src="{{ asset('Assets/img/logo.png') }}" alt="Logo PT Pulo Kencana Drum Industri" class="h-11 w-11 shrink-0 rounded-full border border-zinc-200 bg-white object-contain p-1 shadow-xs" />
                <div class="min-w-0 leading-tight">
                    <p class="truncate text-sm font-bold text-zinc-900">Pulo Kencana - Drum Industri</p>
                    <p class="truncate text-xs text-zinc-500">{{ config('pkdi.brand.company') }}</p>
                </div>
            </div>

            <div class="mt-6">
                <h1 class="text-lg font-bold tracking-tight text-zinc-900">Masuk ke akun Anda</h1>
                <p class="mt-0.5 text-xs text-zinc-500">Sistem Administrasi Operasional Pabrik PT PKDI</p>
            </div>

            <form action="{{ route('login.store') }}" method="POST" class="mt-5 space-y-4" novalidate data-validate-form="login">
                @csrf
                <div class="space-y-1.5" data-field="email" data-field-label="Email">
                    <label class="block text-xs font-medium text-zinc-700" for="f-email">Username / Email <span class="sr-only text-danger" aria-hidden="true">*</span></label>
                    <input
                        id="f-email"
                        name="email"
                        type="email"
                        value="{{ old('email') }}"
                        placeholder="nama@pkdi.co.id"
                        autocomplete="email"
                        required
                        @if ($errors->has('email')) aria-invalid="true" @endif
                        class="w-full rounded-full border {{ $errors->has('email') ? 'border-red-600 bg-red-50/40' : 'border-zinc-300 bg-[#EEF2FF]/70' }} px-4 py-2.5 text-sm text-zinc-900 placeholder:text-zinc-400 transition focus:border-[#800000] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#800000]/20"
                    />
                    @if ($errors->has('email'))
                        <p class="field-error text-xs text-red-600" data-server-error role="alert">{{ $errors->first('email') }}</p>
                    @endif
                </div>

                <div class="space-y-1.5" data-field="password" data-field-label="Password">
                    <label class="block text-xs font-medium text-zinc-700" for="f-password">Kata Sandi <span class="sr-only text-danger" aria-hidden="true">*</span></label>
                    <div class="relative">
                        <input
                            id="f-password"
                            name="password"
                            type="password"
                            minlength="8"
                            placeholder="••••••••••••"
                            autocomplete="current-password"
                            required
                            @if ($errors->has('password') || $errors->first('email') === 'Email atau password tidak sesuai.') aria-invalid="true" @endif
                            class="w-full rounded-full border {{ ($errors->has('password') || $errors->first('email') === 'Email atau password tidak sesuai.') ? 'border-red-600 bg-red-50/40' : 'border-zinc-300 bg-[#EEF2FF]/70' }} py-2.5 pl-4 pr-10 text-sm text-zinc-900 placeholder:text-zinc-400 transition focus:border-[#800000] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#800000]/20"
                        />
                        <button
                            type="button"
                            data-toggle-password="#f-password"
                            aria-pressed="false"
                            aria-label="Tampilkan kata sandi"
                            class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-zinc-400 hover:text-zinc-700 focus:outline-none"
                        >
                            <svg data-eye-open xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                            <svg data-eye-closed xmlns="http://www.w3.org/2000/svg" class="hidden h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M10.733 5.076a10.744 10.744 0 0 1 11.205 6.575 1 1 0 0 1 0 .696 10.747 10.747 0 0 1-1.444 2.49"/>
                                <path d="M14.084 14.158a3 3 0 0 1-4.242-4.242"/>
                                <path d="M17.479 17.499a10.75 10.75 0 0 1-15.417-5.151 1 1 0 0 1 0-.696 10.75 10.75 0 0 1 4.446-5.143"/>
                                <path d="m2 2 20 20"/>
                            </svg>
                        </button>
                    </div>
                    @if ($errors->has('password'))
                        <p class="field-error text-xs text-red-600" data-server-error role="alert">{{ $errors->first('password') }}</p>
                    @endif
                </div>

                <div class="flex items-center justify-between gap-2 pt-0.5 text-xs">
                    <label class="inline-flex cursor-pointer items-center gap-2 text-zinc-600 select-none">
                        <input type="checkbox" name="remember" class="h-3.5 w-3.5 rounded border-zinc-300 text-[#800000] focus:ring-[#800000]" />
                        <span>Ingat saya</span>
                    </label>
                    <a href="{{ route('register') }}" class="font-semibold text-[#800000] hover:underline">Daftar Akun Client</a>
                </div>

                <button type="submit" class="mt-2 w-full cursor-pointer rounded-full bg-[#800000] px-5 py-2.5 text-sm font-semibold text-white shadow-md transition hover:bg-[#660000] active:bg-[#4d0000] focus:outline-none focus:ring-2 focus:ring-[#800000] focus:ring-offset-2">
                    Masuk Sistem
                </button>
            </form>

            <p class="mt-6 text-center text-[11px] text-zinc-400">
                &copy; {{ date('Y') }} {{ config('pkdi.brand.company') }}. All rights reserved.
            </p>
        </div>
    </div>
</div>
</x-layouts.guest>
