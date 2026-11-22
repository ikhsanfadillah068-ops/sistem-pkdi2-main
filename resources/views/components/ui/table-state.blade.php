@props(['state' => 'empty', 'title' => null, 'description' => null])
@if ($state === 'loading')
    <div role="status" aria-label="Memuat data" {{ $attributes->class('space-y-3 p-6') }}>
        <p class="text-xs font-medium text-muted-foreground">Memuat daftar data...</p>
        @for ($row = 0; $row < 4; $row++)
            <div class="flex items-center gap-4"><span class="skeleton h-9 w-9 shrink-0 rounded-full"></span><span class="skeleton h-4 flex-1"></span><span class="skeleton h-4 w-20"></span></div>
        @endfor
    </div>
@else
    @php
        $defaults = match ($state) {
            'filtered' => ['search', 'Tidak ada data yang cocok', 'Coba kata kunci lain atau atur ulang filter yang aktif.'],
            'error' => ['alert-circle', 'Data gagal dimuat', 'Terjadi gangguan sambungan ke server. Muat ulang halaman atau coba beberapa saat lagi.'],
            default => ['file-text', 'Belum ada data', 'Data akan muncul di sini setelah transaksi atau pengajuan dibuat.'],
        };
    @endphp
    <div @if ($state === 'error') role="alert" @else role="status" @endif {{ $attributes->class('flex flex-col items-center justify-center px-6 py-12 text-center') }}>
        <span @class(['flex h-12 w-12 items-center justify-center rounded-full', 'bg-danger-soft text-danger' => $state === 'error', 'bg-soft text-soft-foreground' => $state !== 'error']) aria-hidden="true"><x-ui.icon :name="$defaults[0]" class="h-5 w-5" /></span>
        <h3 class="mt-3 text-base font-semibold">{{ $title ?? $defaults[1] }}</h3>
        <p class="mt-1 max-w-sm text-sm text-muted-foreground">{{ $description ?? $defaults[2] }}</p>
        @if (trim($slot)) <div class="mt-4">{{ $slot }}</div> @endif
    </div>
@endif
