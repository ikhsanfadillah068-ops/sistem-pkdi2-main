@props(['id', 'title', 'description' => null, 'icon' => null, 'tone' => 'info', 'size' => 'lg'])
@php
    $width = match ($size) {
        'sm' => 'max-w-md',
        'xl' => 'max-w-2xl',
        default => 'max-w-lg',
    };
@endphp
<dialog id="{{ $id }}" aria-labelledby="{{ $id }}-title" @if ($description) aria-describedby="{{ $id }}-description" @endif {{ $attributes->class(['modal', $width]) }}>
    <div class="p-5 sm:p-6">
        <div class="flex items-start justify-between gap-4">
            <div @class(['flex items-start gap-3.5' => $icon])>
                @if ($icon)
                    <span class="avatar avatar-{{ $tone }} avatar-lg" aria-hidden="true"><x-ui.icon :name="$icon" class="h-5 w-5" /></span>
                @endif
                <div class="min-w-0">
                    <h2 id="{{ $id }}-title" class="text-lg font-semibold">{{ $title }}</h2>
                    @if ($description)
                        <p id="{{ $id }}-description" class="mt-1 text-sm text-muted-foreground">{{ $description }}</p>
                    @endif
                </div>
            </div>
            <button type="button" class="btn btn-ghost btn-icon btn-sm shrink-0" data-modal-close aria-label="Tutup dialog"><x-ui.icon name="x" /></button>
        </div>
        @if (trim($slot))
            <div class="mt-5">{{ $slot }}</div>
        @endif
        @isset($footer)
            <div class="mt-6 flex flex-wrap justify-end gap-2 border-t pt-4">{{ $footer }}</div>
        @endisset
    </div>
</dialog>
