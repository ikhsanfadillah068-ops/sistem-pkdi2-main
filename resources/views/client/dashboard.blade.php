@php
    $companyName = auth()->user()?->companyDisplay() ?? 'Perusahaan Client';
    $activeCount = count(array_filter($orders, static fn (array $o): bool => ! in_array($o['order_status'], ['COMPLETED', 'CANCELLED'], true)));
    $inProgressOrders = array_values(array_filter($orders, static fn (array $o): bool => in_array($o['order_status'], ['PREPARING', 'IN_PROGRESS'], true)));
    $readyDeliveryOrders = array_values(array_filter($orders, static fn (array $o): bool => in_array($o['order_status'], ['READY', 'IN_DELIVERY'], true)));
    $totalUnpaid = array_reduce($invoices, static fn (float $carry, array $o): float => $carry + (float) ($o['invoice']['balance'] ?? 0), 0.0);
    $unpaidInvoiceCount = count(array_filter($invoices, static fn (array $o): bool => (float) ($o['invoice']['balance'] ?? 0) > 0));
@endphp
<x-layouts.admin title="Ringkasan Portal" group="Portal Client" role="client">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <x-ui.page-header :title="'Portal '.$companyName" description="Ringkasan pesanan layanan drum, status pengerjaan, dan tagihan aktif." />
        <a href="{{ route('client.orders.create') }}" class="btn btn-primary w-full sm:w-auto">
            <x-ui.icon name="plus" />
            Buat Pesanan Baru
        </a>
    </div>

    <section aria-label="Ringkasan pesanan Client" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.stat-card title="Pesanan Aktif" :value="(string) $activeCount" icon="package" note="Dalam proses operasional" />
        <x-ui.stat-card title="Sedang Diproses Gudang" :value="(string) count($inProgressOrders)" icon="sliders" tile="bg-info-soft text-info" :note="$inProgressOrders[0]['order_number'] ?? 'Tidak ada antrean'" />
        <x-ui.stat-card title="Tagihan Belum Lunas" :value="'Rp '.number_format($totalUnpaid, 0, ',', '.')" icon="receipt" tile="bg-warning-soft text-warning" :note="$unpaidInvoiceCount.' invoice menunggu pelunasan'" />
        <x-ui.stat-card title="Siap Diserahkan / Dikirim" :value="(string) count($readyDeliveryOrders)" icon="truck" tile="bg-success-soft text-success" :note="count($readyDeliveryOrders) > 0 ? 'Barang siap serah terima' : 'Belum ada pesanan siap kirim'" />
    </section>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_17.5rem]">
        <div class="card min-w-0">
            <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 sm:px-6">
                <div>
                    <h2 class="card-title">Pesanan Terbaru Perusahaan Anda</h2>
                </div>
                <a href="{{ route('client.orders.index') }}" class="btn btn-outline btn-sm">Lihat Semua Pesanan</a>
            </div>
            <div class="overflow-x-auto border-t">
                <table class="table w-full">
                    <thead>
                        <tr>
                            <th scope="col" class="pl-5 pr-3 sm:pl-6">No. Pesanan</th>
                            <th scope="col" class="px-3">Layanan & Muatan</th>
                            <th scope="col" class="px-3">Nopol</th>
                            <th scope="col" class="px-3">Status Saat Ini</th>
                            <th scope="col" class="pl-3 pr-5 text-right sm:pr-6">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($orders as $order)
                            @php($st = \App\Support\PkdiDemoData::statusMeta($order['order_status']))
                            <tr>
                                <td class="pl-5 pr-3 py-3 align-middle whitespace-nowrap sm:pl-6">
                                    <a href="{{ route('client.orders.show', \App\Support\PkdiDemoData::orderSlug($order['order_number'])) }}" class="font-semibold text-brand-accent hover:underline tabular-nums">{{ $order['order_number'] }}</a>
                                    <p class="mt-0.5 text-[11px] font-normal text-muted-foreground tabular-nums">{{ $order['submitted_at'] }}</p>
                                </td>
                                <td class="px-3 py-3 align-middle max-w-[11rem] sm:max-w-[13rem]">
                                    <p class="font-medium text-foreground whitespace-nowrap">
                                        {{ $order['service_type'] }}
                                        <span class="font-normal text-muted-foreground tabular-nums">({{ $order['requested_qty'] }} {{ $order['unit'] }})</span>
                                    </p>
                                    <p class="mt-0.5 truncate text-xs text-muted-foreground" title="{{ $order['cargo_type'] }} ({{ $order['service_category'] }})">{{ $order['cargo_type'] }}</p>
                                </td>
                                <td class="px-3 py-3 align-middle whitespace-nowrap">
                                    <span class="inline-flex items-center rounded border bg-muted/40 px-2 py-0.5 text-xs font-medium tabular-nums">{{ $order['license_plate'] }}</span>
                                </td>
                                <td class="px-3 py-3 align-middle whitespace-nowrap">
                                    <x-ui.badge :variant="$st['tone']" :dot="true" class="text-[11px] px-2 py-0.5">{{ $st['label'] }}</x-ui.badge>
                                </td>
                                <td class="pl-3 pr-5 py-3 align-middle text-right whitespace-nowrap sm:pr-6">
                                    <a href="{{ route('client.orders.show', \App\Support\PkdiDemoData::orderSlug($order['order_number'])) }}" class="btn btn-outline btn-sm h-8 px-2.5 text-xs">Detail</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-8 text-center text-muted-foreground">Belum ada pesanan yang diajukan.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <x-ui.card title="Tagihan Terbaru">
            <div class="space-y-3">
                @forelse ($invoices as $invOrder)
                    @php($inv = $invOrder['invoice'])
                    <div class="rounded-lg border p-3.5">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="truncate font-semibold tabular-nums">{{ $inv['invoice_number'] }}</p>
                                <p class="mt-0.5 truncate text-xs text-muted-foreground">Pesanan {{ $invOrder['order_number'] }}</p>
                            </div>
                            <x-ui.badge :variant="$inv['status'] === 'PAID' ? 'success' : 'warning'">
                                {{ $inv['status'] === 'PAID' ? 'Lunas' : 'Belum Lunas' }}
                            </x-ui.badge>
                        </div>
                        <div class="mt-3 flex items-center justify-between border-t pt-2.5 text-xs">
                            <span class="text-muted-foreground">Sisa: <strong class="text-foreground tabular-nums">Rp {{ number_format($inv['balance'], 0, ',', '.') }}</strong></span>
                            <a href="{{ route('client.invoices.show', str_replace('/', '-', $inv['invoice_number'])) }}" class="font-medium text-brand-accent hover:underline">Buka Tagihan</a>
                        </div>
                    </div>
                @empty
                    <p class="py-6 text-center text-xs text-muted-foreground">Belum ada tagihan diterbitkan.</p>
                @endforelse
            </div>
        </x-ui.card>
    </div>
</x-layouts.admin>
