@php
    $isPaid = $order['payment_status'] === 'PAID';
    $do = $order['delivery_order'];
    $orderSlug = \App\Support\PkdiDemoData::orderSlug($order['order_number']);
@endphp
<x-layouts.admin :title="'Surat Jalan '.$order['order_number']" group="Operasional Gudang" role="kepala_gudang">
    <div>
        <a href="{{ route('warehouse.delivery-orders.index') }}" class="mb-2 inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground">
            <x-ui.icon name="arrow-left" class="h-4 w-4" />
            Kembali ke Daftar Surat Jalan
        </a>
        <h1 class="text-2xl font-semibold tracking-tight">Penerbitan & Pratinjau Surat Jalan</h1>
        <p class="mt-1 text-sm text-muted-foreground">Pesanan {{ $order['order_number'] }} · {{ $order['client_company'] }}</p>
    </div>

    @if (! $isPaid)
        <x-ui.alert variant="danger" title="Penerbitan Surat Jalan Diblokir">
            Tagihan pesanan ini belum lunas (Status: <strong>{{ $order['payment_status'] }}</strong>). Kepala Gudang tidak dapat menerbitkan Surat Jalan sebelum Keuangan memverifikasi pelunasan penuh.
        </x-ui.alert>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        <x-ui.card title="Pembuatan Surat Jalan">
            <form action="{{ route('warehouse.delivery-orders.store', $orderSlug) }}" method="POST" class="space-y-4" novalidate data-validate-form data-prevent-double-submit>
                @csrf
                <x-ui.field label="Nomor Surat Jalan" name="do_number">
                    <x-ui.input name="do_number" :value="$do['do_number'] ?? 'SJ/PKDI/2026/11/AUTO'" disabled />
                </x-ui.field>
                <x-ui.field label="Rincian Barang yang Dikeluarkan" name="cargo_summary" :error="$errors->first('cargo_summary')" required>
                    <x-ui.textarea name="cargo_summary" rows="2" :disabled="! $isPaid" required>{{ old('cargo_summary', $do['cargo_summary'] ?? (($order['result']['completed_qty'] ?? $order['requested_qty']).' '.$order['unit'].' '.$order['service_category'].' ('.$order['service_type'].')')) }}</x-ui.textarea>
                </x-ui.field>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.field label="Nomor Polisi Kendaraan Keluar" name="vehicle_plate" :error="$errors->first('vehicle_plate')" required>
                        <x-ui.input name="vehicle_plate" :value="old('vehicle_plate', $do['license_plate'] ?? $order['license_plate'])" :disabled="! $isPaid" required />
                    </x-ui.field>
                    <x-ui.field label="Jenis Kendaraan Keluar" name="vehicle_type" :error="$errors->first('vehicle_type')" required>
                        <x-ui.input name="vehicle_type" :value="old('vehicle_type', $do['vehicle_type'] ?? $order['vehicle_type'])" :disabled="! $isPaid" required />
                    </x-ui.field>
                </div>
                <x-ui.field label="Nama Driver Penjemput (Opsional)" name="driver_name" :error="$errors->first('driver_name')">
                    <x-ui.input name="driver_name" :value="old('driver_name', $do['driver_name'] ?? ($order['driver_name'] ?? ''))" :disabled="! $isPaid" />
                </x-ui.field>
                <x-ui.button type="submit" class="w-full btn-lg" :disabled="! $isPaid">
                    <x-ui.icon name="truck" />
                    {{ !empty($do) ? 'Perbarui / Cetak Ulang Surat Jalan' : 'Terbitkan Surat Jalan Resmi' }}
                </x-ui.button>
            </form>
        </x-ui.card>

        <x-ui.card title="Pratinjau Dokumen Surat Jalan" description="Dokumen siap cetak untuk penyerahan barang dan arsip pengiriman PKDI.">
            <div class="rounded-lg border bg-background p-5 space-y-4 text-sm">
                <div class="flex items-center justify-between border-b pb-3">
                    <div class="flex items-center gap-2.5">
                        <img src="{{ asset('Assets/img/logo.png') }}" alt="Logo PKDI" class="h-9 w-9 shrink-0 rounded-full border border-zinc-200 bg-white object-contain p-1 shadow-xs" />
                        <div>
                            <p class="font-semibold">{{ config('pkdi.brand.company') }}</p>
                            <p class="text-xs text-muted-foreground">Surat Jalan Izin Keluar Barang</p>
                        </div>
                    </div>
                    <x-ui.badge :variant="$isPaid ? 'success' : 'danger'">{{ $isPaid ? 'LUNAS' : 'BELUM LUNAS' }}</x-ui.badge>
                </div>
                <dl class="grid grid-cols-2 gap-3 text-xs">
                    <div><dt class="text-muted-foreground">No. Surat Jalan</dt><dd class="mt-0.5 font-semibold tabular-nums">{{ $do['do_number'] ?? 'SJ/PKDI/2026/11/DRAFT' }}</dd></div>
                    <div><dt class="text-muted-foreground">Referensi Pesanan</dt><dd class="mt-0.5 font-semibold tabular-nums">{{ $order['order_number'] }}</dd></div>
                    <div><dt class="text-muted-foreground">Perusahaan Client</dt><dd class="mt-0.5 font-medium">{{ $order['client_company'] }}</dd></div>
                    <div><dt class="text-muted-foreground">Kendaraan & Nopol</dt><dd class="mt-0.5 font-medium tabular-nums">{{ $do['license_plate'] ?? $order['license_plate'] }}</dd></div>
                </dl>
                <div class="border-t pt-3">
                    <p class="text-xs text-muted-foreground">Rincian Muatan:</p>
                    <p class="mt-1 font-medium">{{ $do['cargo_summary'] ?? (($order['result']['completed_qty'] ?? $order['requested_qty']).' '.$order['unit'].' '.$order['service_category']) }}</p>
                </div>
            </div>
        </x-ui.card>
    </div>
</x-layouts.admin>
