@php
    $st = \App\Support\PkdiDemoData::statusMeta($order['order_status']);
    $invoice = $order['invoice'] ?? null;
    $hasPublishedInvoice = ! empty($invoice) && ($invoice['status'] ?? '') !== 'DRAFT';
    $defaultTab = ($hasPublishedInvoice || ! empty($order['delivery_order']))
        && in_array($order['order_status'], ['INVOICE_ISSUED', 'PAYMENT_PARTIAL', 'PAID', 'WAITING_EXIT_DOCUMENT', 'WAITING_GATE_OUT', 'GATE_OUT_HELD', 'COMPLETED'], true)
        ? 'billing'
        : 'warehouse';
@endphp
<x-layouts.admin :title="'Detail '.$order['order_number']" group="Portal Client" role="client">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <a href="{{ route('client.orders.index') }}" class="mb-2 inline-flex min-h-10 items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground sm:min-h-0">
                <x-ui.icon name="arrow-left" class="h-4 w-4" />
                Kembali ke daftar pesanan
            </a>
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="text-xl font-semibold tracking-tight tabular-nums sm:text-2xl">{{ $order['order_number'] }}</h1>
                <x-ui.badge :variant="$st['tone']" :dot="true">{{ $st['label'] }}</x-ui.badge>
            </div>
            <p class="mt-1 text-sm text-muted-foreground">Diajukan {{ $order['submitted_at'] }} oleh {{ $order['client_company'] }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <button type="button" class="btn btn-outline btn-sm min-h-11 sm:min-h-8" data-copy="{{ $order['order_number'] }}">
                <x-ui.icon name="copy" />
                Salin No. Pesanan
            </button>
        </div>
    </div>

    <x-ui.order-stepper :order="$order" />

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1.15fr)_minmax(0,1fr)]">
        <x-ui.detail-list title="Informasi Pengajuan & Kendaraan" :items="[
            ['label' => 'Kategori Layanan', 'value' => $order['service_category']],
            ['label' => 'Jenis Muatan Awal', 'value' => $order['cargo_type']],
            ['label' => 'Layanan & Estimasi', 'value' => $order['service_type'].' ('.$order['requested_qty'].' '.$order['unit'].')'],
            ['label' => 'Asal Perusahaan', 'value' => $order['origin_company']],
            ['label' => 'Jenis Kendaraan', 'value' => $order['vehicle_type']],
            ['label' => 'Nomor Polisi', 'value' => $order['license_plate']],
            ['label' => 'Nama Driver', 'value' => $order['driver_name'] ?: 'Tidak diisi'],
        ]">
            @if (! empty($order['client_notes']))
                <div class="min-w-0 sm:col-span-2">
                    <dt class="text-xs font-medium text-muted-foreground">Catatan Pengajuan</dt>
                    <dd class="mt-1 break-words text-sm font-medium">{{ $order['client_notes'] }}</dd>
                </div>
            @endif
        </x-ui.detail-list>

        <x-ui.card title="Hasil Gudang & Pembayaran" class="min-w-0">
            <x-ui.tabs id="client-order-detail-tabs" variant="pill" :active="$defaultTab" aria-label="Rincian gudang dan tagihan" :tabs="[
                'warehouse' => 'Hasil Gudang',
                'billing' => 'Tagihan & Pembayaran',
            ]">
                <x-slot:tab_warehouse>
                    @if (! empty($order['inspection']) || ! empty($order['processes']) || ! empty($order['result']))
                        <div class="space-y-4">
                            @if (! empty($order['inspection']))
                                <div class="rounded-lg border p-3.5">
                                    <p class="text-xs font-semibold text-foreground">Pemeriksaan Muatan Masuk</p>
                                    <dl class="mt-2.5 grid gap-x-4 gap-y-2 text-sm sm:grid-cols-2">
                                        <div>
                                            <dt class="text-xs text-muted-foreground">Muatan Aktual</dt>
                                            <dd class="font-medium">{{ $order['inspection']['actual_cargo_type'] }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs text-muted-foreground">Jumlah Diterima</dt>
                                            <dd class="font-medium tabular-nums">{{ $order['inspection']['actual_quantity'] }} {{ $order['inspection']['actual_unit'] }}</dd>
                                        </div>
                                        <div class="sm:col-span-2">
                                            <dt class="text-xs text-muted-foreground">Kondisi & Catatan</dt>
                                            <dd class="font-medium">{{ $order['inspection']['cargo_condition'] }} ({{ $order['inspection']['discrepancy_notes'] }})</dd>
                                        </div>
                                    </dl>
                                </div>
                            @endif

                            @if (! empty($order['processes']))
                                <div class="rounded-lg border p-3.5">
                                    <p class="text-xs font-semibold text-foreground">Tahap Pengerjaan Workshop</p>
                                    <ul class="mt-2.5 space-y-2 text-sm">
                                        @foreach ($order['processes'] as $proc)
                                            <li class="flex items-start justify-between gap-2 border-b pb-2 last:border-b-0 last:pb-0">
                                                <div class="min-w-0">
                                                    <p class="font-medium">{{ $proc['stage'] }}</p>
                                                    @if (! empty($proc['note']))
                                                        <p class="text-xs text-muted-foreground">{{ $proc['note'] }}</p>
                                                    @endif
                                                </div>
                                                <span class="shrink-0 text-xs text-muted-foreground tabular-nums">{{ $proc['qty'] }} {{ $order['unit'] }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            @if (! empty($order['result']))
                                <div class="rounded-lg border bg-muted/25 p-3.5">
                                    <p class="text-xs font-semibold text-foreground">Hasil Akhir Pekerjaan</p>
                                    <dl class="mt-2.5 grid gap-x-4 gap-y-2 text-sm sm:grid-cols-2">
                                        <div>
                                            <dt class="text-xs text-muted-foreground">Layanan Aktual</dt>
                                            <dd class="font-medium">{{ $order['result']['actual_service_type'] }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs text-muted-foreground">Kuantitas Selesai</dt>
                                            <dd class="font-semibold text-success tabular-nums">{{ $order['result']['completed_qty'] }} {{ $order['result']['unit'] }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs text-muted-foreground">Tidak Layak Proses</dt>
                                            <dd class="font-medium tabular-nums">{{ $order['result']['rejected_qty'] }} {{ $order['result']['unit'] }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs text-muted-foreground">Waktu Selesai</dt>
                                            <dd class="font-medium tabular-nums">{{ $order['result']['completed_at'] }}</dd>
                                        </div>
                                    </dl>
                                </div>
                            @endif
                        </div>
                    @else
                        <div class="rounded-lg border border-dashed p-5 text-center">
                            <p class="text-sm font-medium text-foreground">Belum ada hasil pemeriksaan gudang</p>
                            <p class="mt-1 text-xs text-muted-foreground">Rincian pemeriksaan, penyortiran, dan progres pengerjaan tampil setelah Kepala Gudang meninjau pesanan.</p>
                        </div>
                    @endif
                </x-slot:tab_warehouse>

                <x-slot:tab_billing>
                    @if ($hasPublishedInvoice || ! empty($order['delivery_order']))
                        <div class="space-y-4">
                            @if ($hasPublishedInvoice)
                                @php
                                    $invSlug = $invoice['invoice_slug'] ?? str_replace('/', '-', $invoice['invoice_number']);
                                @endphp
                                <div class="rounded-lg border p-3.5">
                                    <div class="flex items-center justify-between gap-2">
                                        <p class="text-sm font-semibold tabular-nums">{{ $invoice['invoice_number'] }}</p>
                                        <x-ui.badge :variant="$invoice['status'] === 'PAID' ? 'success' : 'warning'" :dot="true">
                                            {{ $invoice['status'] === 'PAID' ? 'Lunas' : ($invoice['status'] === 'PARTIALLY_PAID' ? 'Bayar Sebagian' : 'Menunggu Bayar') }}
                                        </x-ui.badge>
                                    </div>
                                    <dl class="mt-3 grid grid-cols-2 gap-3 text-sm">
                                        <div>
                                            <dt class="text-xs text-muted-foreground">Total Tagihan</dt>
                                            <dd class="font-medium tabular-nums">Rp {{ number_format($invoice['total'], 0, ',', '.') }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs text-muted-foreground">Sisa Tagihan</dt>
                                            <dd class="font-semibold text-brand-accent tabular-nums">Rp {{ number_format($invoice['balance'], 0, ',', '.') }}</dd>
                                        </div>
                                    </dl>
                                    <a href="{{ route('client.invoices.show', $invSlug) }}" class="btn btn-outline btn-sm mt-3 w-full">
                                        <x-ui.icon name="receipt" />
                                        {{ $invoice['balance'] > 0 ? 'Buka & Bayar Tagihan' : 'Lihat Detail Tagihan' }}
                                    </a>
                                </div>
                            @endif

                            @if (! empty($order['delivery_order']))
                                @php
                                    $do = $order['delivery_order'];
                                @endphp
                                <div class="rounded-lg border p-3.5">
                                    <p class="text-xs font-semibold text-foreground">Surat Jalan Keluar</p>
                                    <dl class="mt-2.5 grid grid-cols-2 gap-3 text-sm">
                                        <div>
                                            <dt class="text-xs text-muted-foreground">Nomor Surat Jalan</dt>
                                            <dd class="font-semibold tabular-nums">{{ $do['do_number'] }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs text-muted-foreground">Kuantitas Keluar</dt>
                                            <dd class="font-medium tabular-nums">{{ $do['quantity_released'] }} {{ $do['unit'] }}</dd>
                                        </div>
                                        <div class="col-span-2">
                                            <dt class="text-xs text-muted-foreground">Diterbitkan</dt>
                                            <dd class="font-medium tabular-nums">{{ $do['issued_at'] }}</dd>
                                        </div>
                                    </dl>
                                </div>
                            @endif
                        </div>
                    @else
                        <div class="rounded-lg border border-dashed p-5 text-center">
                            <p class="text-sm font-medium text-foreground">Tagihan belum diterbitkan</p>
                            <p class="mt-1 text-xs text-muted-foreground">Invoice dan instruksi transfer manual akan tampil di sini setelah pekerjaan Gudang masuk penagihan Keuangan.</p>
                        </div>
                    @endif
                </x-slot:tab_billing>
            </x-ui.tabs>
        </x-ui.card>
    </div>
</x-layouts.admin>
