@props(['title', 'group', 'role', 'roleMeta'])
@php
    $notifications = \App\Support\PkdiDemoData::notificationsForRole($role);
@endphp
<header class="app-header sticky z-30 flex h-14 items-center gap-2 border-b bg-card px-4">
    <button id="sidebarToggle" type="button" aria-label="Buka atau tutup menu navigasi" class="btn btn-ghost btn-icon btn-sm text-muted-foreground">
        <x-ui.icon name="panel" />
    </button>
    <div class="mx-1 hidden h-4 w-px bg-border sm:block"></div>
    <nav aria-label="Breadcrumb" class="hidden min-w-0 items-center gap-1.5 text-sm sm:flex">
        <span class="text-muted-foreground">{{ $group }}</span>
        <x-ui.icon name="chevron-right" class="h-3.5 w-3.5 text-muted-foreground" />
        <span class="truncate font-medium">{{ $title }}</span>
    </nav>

    <div class="ml-auto flex items-center gap-2">
        <div class="relative" data-dropdown>
            <button type="button" data-dropdown-toggle aria-expanded="false" aria-label="Notifikasi" class="btn btn-outline btn-icon relative">
                <x-ui.icon name="bell" />
                <span class="absolute right-2 top-2 h-2 w-2 rounded-full bg-primary" aria-label="{{ count($notifications) }} notifikasi belum dibaca"></span>
            </button>
            <div class="menu menu-right hidden w-80" role="menu">
                <div class="menu-label">Notifikasi {{ $roleMeta['label'] }}</div>
                <div class="menu-sep"></div>
                @foreach ($notifications as $notif)
                    <a href="{{ $notif['url'] }}" class="menu-item items-start py-2" role="menuitem">
                        <span class="avatar avatar-{{ $notif['tone'] }} avatar-sm mt-0.5"><x-ui.icon :name="$notif['icon']" /></span>
                        <span class="min-w-0 flex-1">
                            <span class="block font-medium leading-snug">{{ $notif['title'] }}</span>
                            <span class="mt-0.5 block text-xs text-muted-foreground">{{ $notif['meta'] }}</span>
                        </span>
                    </a>
                @endforeach
            </div>
        </div>

        <button type="button" data-theme-toggle aria-label="Ganti tema terang atau gelap" class="btn btn-outline btn-icon">
            <svg class="h-4 w-4" aria-hidden="true"><use class="js-theme-icon" href="{{ asset('icons.svg') }}#i-moon"/></svg>
        </button>
    </div>
</header>
