@props(['label' => null, 'name' => null, 'help' => null, 'required' => false, 'error' => null, 'class' => ''])
@php
    $id = $attributes->get('id', $name ? 'f-' . str_replace(['[', ']'], '-', $name) : null);
    $serverError = ($name && $errors->has($name)) ? $errors->first($name) : $error;
    $hasError = filled($serverError);
@endphp
<div {{ $attributes->except(['label', 'name', 'help', 'required', 'error'])->class(['space-y-1.5', $class]) }}
    @if ($name) data-field="{{ $name }}" @endif
    @if ($label && ! $attributes->has('data-field-label')) data-field-label="{{ $label }}" @endif>
    @if ($label)
        <label @if ($id) for="{{ $id }}" @endif class="label">
            {{ $label }}
            @if ($required)
                <span class="text-danger font-semibold" aria-hidden="true">*</span>
            @endif
        </label>
    @endif
    {{ $slot }}
    @if ($hasError)
        <p class="field-error" data-server-error role="alert">{{ $serverError }}</p>
    @endif
    @if ($help)
        <p class="field-help" @if ($hasError) hidden @endif>{{ $help }}</p>
    @endif
</div>
