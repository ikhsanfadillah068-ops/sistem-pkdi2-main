@props(['id', 'label' => 'Rentang tanggal', 'fromName' => null, 'toName' => null, 'from' => null, 'to' => null])
<fieldset data-date-range {{ $attributes->class('space-y-2.5') }}>
    <legend class="sr-only">{{ $label }}</legend>
    <div class="grid gap-4 sm:grid-cols-2">
        <div class="space-y-1.5">
            <label class="label" for="{{ $id }}-from">Dari Tanggal</label>
            <input id="{{ $id }}-from" @if($fromName) name="{{ $fromName }}" @endif type="text" class="input" value="{{ $fromName ? old($fromName, $from) : $from }}" placeholder="Pilih tanggal awal" autocomplete="off" data-date-picker data-range-from>
        </div>
        <div class="space-y-1.5">
            <label class="label" for="{{ $id }}-to">Sampai Tanggal</label>
            <input id="{{ $id }}-to" @if($toName) name="{{ $toName }}" @endif type="text" class="input" value="{{ $toName ? old($toName, $to) : $to }}" placeholder="Pilih tanggal akhir" autocomplete="off" data-date-picker data-range-to>
        </div>
    </div>
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div class="flex flex-wrap items-center gap-1.5">
            <button type="button" class="btn btn-outline btn-sm h-7 px-2.5 text-xs" data-range-days="7">7 hari terakhir</button>
            <button type="button" class="btn btn-outline btn-sm h-7 px-2.5 text-xs" data-range-days="30">30 hari terakhir</button>
        </div>
        <p class="field-help m-0 text-xs" role="status" aria-live="polite" data-range-status>Pilih tanggal atau gunakan tombol cepat.</p>
    </div>
</fieldset>
