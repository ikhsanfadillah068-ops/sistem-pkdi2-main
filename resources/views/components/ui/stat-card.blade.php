@props([
    'title', 'value', 'icon', 'tile' => 'bg-soft text-soft-foreground', 'tone' => 'soft',
    'direction' => null, 'delta' => null, 'note' => null, 'spark' => null,
])
<div {{ $attributes->class('card p-4 sm:p-5') }}>
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0 flex-1">
            <h2 class="text-xs font-medium text-muted-foreground">{{ $title }}</h2>
            <p class="mt-1.5 text-lg font-semibold tracking-tight tabular-nums whitespace-nowrap xl:text-[1.2rem]">{{ $value }}</p>
        </div>
        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg {{ $tile }}">
            <x-ui.icon :name="$icon" class="h-4 w-4" />
        </span>
    </div>
    @if ($delta || $note)
        <p class="mt-2.5 flex flex-wrap items-center gap-2 text-xs">
            @if ($delta)
                <span class="badge badge-{{ $tone }}">
                    @if ($direction)
                        <x-ui.icon :name="'trend-'.$direction" class="h-3.5 w-3.5" />
                    @endif
                    {{ $delta }}
                </span>
            @endif
            @if ($note)
                <span class="text-muted-foreground">{{ $note }}</span>
            @endif
        </p>
    @endif
    @if ($spark)
        <div class="mt-3" data-chart="spark" data-kind="area" data-color="{{ $tone }}" data-values="{{ is_array($spark) ? Illuminate\Support\Js::from($spark) : $spark }}" data-height="32"></div>
    @endif
</div>
