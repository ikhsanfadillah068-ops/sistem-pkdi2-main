@props(['title', 'description' => null])
<div {{ $attributes }}>
    <h1 class="text-lg sm:text-xl font-semibold tracking-tight">{{ $title }}</h1>
    @if ($description)
        <p class="mt-0.5 text-sm text-muted-foreground">{{ $description }}</p>
    @endif
</div>
