@php
    $companyName = auth()->user()?->companyDisplay() ?? 'Perusahaan Client';
@endphp
<x-layouts.admin title="Pesanan Saya" group="Portal Client" role="client">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <x-ui.page-header title="Pesanan Saya" :description="'Daftar pengajuan layanan drum '.$companyName.'.'" />
        <a href="{{ route('client.orders.create') }}" class="btn btn-primary w-full sm:w-auto">
            <x-ui.icon name="plus" />
            Buat Pesanan Baru
        </a>
    </div>

    <div class="card">
        @if ($orders === [])
            <x-ui.empty icon="package" title="Belum ada pesanan" description="Ajukan pesanan layanan drum baru untuk mulai memantau proses di sini.">
                <a href="{{ route('client.orders.create') }}" class="btn btn-primary">
                    <x-ui.icon name="plus" />
                    Buat Pesanan Baru
                </a>
            </x-ui.empty>
        @else
            @php
                $availableStatuses = array_values(array_unique(array_column($orders, 'order_status')));
            @endphp
            <div data-table data-page-size="8">
                <div class="flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6">
                    <h2 class="card-title">Semua Pesanan ({{ count($orders) }})</h2>
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <input type="search" class="input sm:w-60" placeholder="Cari no. pesanan atau nopol..." aria-label="Cari pesanan" data-table-search>
                        <select class="select sm:w-48" aria-label="Filter status pesanan" data-table-filter>
                            <option value="">Semua Status</option>
                            @foreach ($availableStatuses as $statusCode)
                                @php
                                    $optMeta = \App\Support\PkdiDemoData::statusMeta($statusCode);
                                @endphp
                                <option value="{{ $statusCode }}">{{ $optMeta['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="overflow-x-auto border-t">
                    <table class="table w-full">
                        <thead>
                            <tr>
                                <th scope="col" data-sort>No. Pesanan</th>
                                <th scope="col" data-sort>Layanan & Muatan</th>
                                <th scope="col">Kendaraan</th>
                                <th scope="col">Status</th>
                                <th scope="col" class="text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($orders as $order)
                                @php
                                    $st = \App\Support\PkdiDemoData::statusMeta($order['order_status']);
                                    $stepNum = $order['progress']['active_step'] ?? 1;
                                    $totalSteps = $order['progress']['total_steps'] ?? 5;
                                @endphp
                                <tr data-status="{{ $order['order_status'] }}">
                                    <td class="py-3 align-middle font-medium tabular-nums whitespace-nowrap">
                                        <a href="{{ route('client.orders.show', \App\Support\PkdiDemoData::orderSlug($order['order_number'])) }}" class="font-semibold text-brand-accent hover:underline">{{ $order['order_number'] }}</a>
                                        <p class="mt-0.5 text-[11px] font-normal text-muted-foreground">{{ $order['submitted_at'] }}</p>
                                    </td>
                                    <td class="py-3 align-middle max-w-[16rem]">
                                        <p class="font-medium whitespace-nowrap">
                                            {{ $order['service_type'] }}
                                            <span class="font-normal text-muted-foreground tabular-nums">({{ $order['requested_qty'] }} {{ $order['unit'] }})</span>
                                        </p>
                                        <p class="mt-0.5 truncate text-xs text-muted-foreground" title="{{ $order['service_category'] }} · {{ $order['cargo_type'] }}">{{ $order['service_category'] }} · {{ $order['cargo_type'] }}</p>
                                    </td>
                                    <td class="py-3 align-middle whitespace-nowrap">
                                        <p class="font-medium tabular-nums">{{ $order['license_plate'] }}</p>
                                        <p class="mt-0.5 max-w-[11rem] truncate text-xs text-muted-foreground" title="{{ $order['vehicle_type'] }} · {{ $order['driver_name'] ?? 'Driver belum diisi' }}">{{ $order['vehicle_type'] }}</p>
                                    </td>
                                    <td class="py-3 align-middle whitespace-nowrap">
                                        <x-ui.badge :variant="$st['tone']" :dot="true" class="text-[11px] px-2 py-0.5">{{ $st['label'] }}</x-ui.badge>
                                        <p class="mt-1 text-[11px] text-muted-foreground tabular-nums">Tahap {{ $stepNum }}/{{ $totalSteps }}</p>
                                    </td>
                                    <td class="py-3 align-middle text-right whitespace-nowrap">
                                        <a href="{{ route('client.orders.show', \App\Support\PkdiDemoData::orderSlug($order['order_number'])) }}" class="btn btn-outline btn-sm min-h-11 px-3 text-xs sm:min-h-8">Lihat Detail</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div data-table-empty hidden>
                        <x-ui.table-state state="filtered">
                            <button type="button" class="btn btn-outline btn-sm" data-demo-reset-table>Atur Ulang Filter</button>
                        </x-ui.table-state>
                    </div>
                </div>
                <div class="flex flex-col items-center justify-between gap-3 border-t px-5 py-3 sm:flex-row sm:px-6">
                    <p data-table-info class="text-sm text-muted-foreground"></p>
                    <div data-table-pager class="flex items-center gap-1"></div>
                </div>
            </div>
        @endif
    </div>
</x-layouts.admin>
