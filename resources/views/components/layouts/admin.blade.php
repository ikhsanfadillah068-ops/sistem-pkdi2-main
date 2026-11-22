@props(['title', 'group' => 'Operasional', 'role' => 'client'])
@php
    /** @var \App\Models\User|null $authUser */
    $authUser = auth()->user();
    $activeRole = $authUser?->role ?? $role;
    $roleMeta = $authUser ? $authUser->toRoleMeta() : \App\Support\PkdiDemoData::roleMeta($activeRole);
    $navigationGroups = config('pkdi.navigation.'.$activeRole, []);
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <x-layouts.head :title="$title" />
</head>
<body data-sidebar="closed" class="font-sans text-sm antialiased" data-status-heartbeat="{{ route('status.heartbeat') }}" data-heartbeat-interval="15000">
<script>document.body.dataset.sidebar = window.innerWidth >= 1024 ? 'open' : 'closed';</script>
<a href="#page-root" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[80] focus:rounded-md focus:bg-primary focus:px-4 focus:py-2 focus:text-primary-foreground">Langsung ke konten utama</a>
<div id="overlay" class="fixed inset-0 z-40 bg-black/50" aria-hidden="true"></div>

<x-admin.sidebar :role="$activeRole" :roleMeta="$roleMeta" :groups="$navigationGroups" />

<div id="main" class="min-h-screen flex flex-col">
    <x-admin.header :title="$title" :group="$group" :role="$activeRole" :roleMeta="$roleMeta" />
    <main id="page-root" tabindex="-1" class="mx-auto w-full max-w-7xl flex-1 space-y-6 p-4 sm:p-6">
        @if (session('status'))
            <x-ui.alert variant="success" title="Berhasil">
                {{ session('status') }}
            </x-ui.alert>
        @endif

        @if ($errors->any())
            <x-ui.alert variant="danger" title="Periksa kembali isian atau aturan status">
                <ul class="list-disc pl-4 space-y-0.5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-ui.alert>
        @endif

        {{ $slot }}
    </main>
    <x-admin.footer />
</div>

<div id="toasts" class="pointer-events-none fixed bottom-4 right-4 z-[70] flex w-[calc(100%-2rem)] max-w-sm flex-col gap-2" aria-live="polite" aria-atomic="true"></div>
</body>
</html>
