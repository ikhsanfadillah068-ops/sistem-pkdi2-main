@props(['role', 'roleMeta', 'groups' => null])
@php
    $resolvedGroups = is_array($groups) ? $groups : config('pkdi.navigation.'.$role, []);
@endphp
<aside id="sidebar" class="fixed inset-y-0 left-0 z-50 flex w-64 flex-col overflow-hidden border-r bg-sidebar" aria-label="Navigasi utama">
    <div class="sb-brand-wrap border-b p-3.5">
        <a href="{{ route($roleMeta['home_route']) }}" title="{{ config('pkdi.brand.company') }}" class="sb-brand-link flex items-center gap-3 rounded-md p-1.5 hover:bg-accent focus-ring">
            <img src="{{ asset('Assets/img/logo.png') }}" alt="Logo {{ config('pkdi.brand.company') }}" class="h-10 w-10 shrink-0 rounded-full border border-zinc-200 bg-white object-contain p-1 shadow-sm" />
            <span class="sb-label min-w-0 flex-1 leading-tight">
                <span class="block truncate text-sm font-semibold text-foreground">Pulo Kencana - Drum Industri</span>
                <span class="mt-0.5 block truncate text-xs font-medium text-brand-accent">{{ config('pkdi.brand.company') }}</span>
            </span>
        </a>
    </div>

    <nav class="sb-nav flex-1 space-y-5 overflow-y-auto overflow-x-hidden px-3 py-4">
        @foreach ($resolvedGroups as $group)
            <div>
                <p class="sb-label mb-1.5 px-2.5 text-xs font-medium text-muted-foreground">{{ $group['label'] }}</p>
                <ul class="space-y-1">
                    @foreach ($group['items'] as $item)
                        <li><x-admin.nav-link :item="$item" /></li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </nav>

    <div class="sb-footer border-t p-3 space-y-1.5">
        <a href="{{ route('settings.edit') }}" title="{{ $roleMeta['user_name'] }} (Pengaturan Akun)" class="sb-profile-link flex items-center gap-2.5 rounded-md p-2 hover:bg-accent focus-ring">
            <span class="avatar avatar-sm">{{ $roleMeta['user_initials'] }}</span>
            <span class="sb-label min-w-0 flex-1 leading-tight">
                <span class="block truncate text-sm font-medium">{{ $roleMeta['user_name'] }}</span>
                <span class="block truncate text-xs text-muted-foreground">{{ $roleMeta['user_email'] }}</span>
            </span>
            <x-ui.icon name="settings" class="sb-label h-4 w-4 shrink-0 text-muted-foreground" />
        </a>
        <form action="{{ route('logout') }}" method="POST">
            @csrf
            <button type="submit" title="Keluar dari Akun" aria-label="Keluar dari Akun" class="nav-link w-full text-left text-xs">
                <x-ui.icon name="logout" class="h-4 w-4 shrink-0" />
                <span class="sb-label">Keluar dari Akun</span>
            </button>
        </form>
    </div>
</aside>
