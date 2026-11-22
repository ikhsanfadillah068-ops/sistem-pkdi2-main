@props(['id', 'title', 'description', 'action' => '', 'method' => 'POST', 'label' => 'Konfirmasi', 'tone' => 'primary'])
@php
    $buttonVariant = in_array($tone, ['destructive', 'primary', 'success'], true) ? $tone : 'primary';
    $verb = strtoupper($method);
@endphp
<x-ui.modal :id="$id" :title="$title" :description="$description" :icon="$buttonVariant === 'destructive' ? 'alert-triangle' : 'info'" :tone="$buttonVariant === 'destructive' ? 'danger' : 'info'" size="sm">
    {{ $slot }}
    <x-slot:footer>
        <x-ui.button variant="outline" data-modal-close>Batal</x-ui.button>
        <form action="{{ $action }}" method="POST" data-prevent-double-submit>
            @csrf
            @if($verb !== 'POST') @method($verb) @endif
            <x-ui.button type="submit" :variant="$buttonVariant">{{ $label }}</x-ui.button>
        </form>
    </x-slot:footer>
</x-ui.modal>
