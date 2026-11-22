<x-layouts.admin title="Pemeriksaan Kendaraan Masuk" group="Pos Gerbang" role="satpam">
    <x-ui.page-header title="Daftar Antrean Kendaraan Masuk" description="Pilih kendaraan yang tiba di gerbang untuk mencocokkan nomor polisi, jenis kendaraan, dan driver." />

    <div class="card" data-table data-page-size="8">
        <div class="flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6">
            <div>
                <h2 class="card-title">Kendaraan Diperkirakan Masuk</h2>
                <p class="card-desc mt-1">Klik Periksa untuk mencatat kecocokan fisik di pos gerbang.</p>
            </div>
            <input type="search" class="input sm:w-64" placeholder="Cari nopol, nomor pesanan, atau PT..." aria-label="Cari kendaraan masuk" data-table-search>
        </div>
        <div class="overflow-x-auto border-t">
            <table class="table min-w-[640px]">
                <thead>
                    <tr>
                        <th>Nomor Polisi</th>
                        <th>No. Pesanan</th>
                        <th>Perusahaan PT</th>
                        <th>Kendaraan & Driver</th>
                        <th>Status</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($orders as $order)
                        @php($st = \App\Support\PkdiDemoData::statusMeta($order['order_status']))
                        <tr>
                            <td class="font-bold tabular-nums whitespace-nowrap">{{ $order['license_plate'] }}</td>
                            <td class="tabular-nums whitespace-nowrap">{{ $order['order_number'] }}</td>
                            <td>{{ $order['client_company'] }}</td>
                            <td>
                                <p>{{ $order['vehicle_type'] }}</p>
                                <p class="text-xs text-muted-foreground">Driver: {{ $order['driver_name'] ?? 'Kosong (opsional)' }}</p>
                            </td>
                            <td><x-ui.badge :variant="$st['tone']" :dot="true">{{ $st['label'] }}</x-ui.badge></td>
                            <td class="text-right">
                                <a href="{{ route('gate.checkin.show', \App\Support\PkdiDemoData::orderSlug($order['order_number'])) }}" class="btn btn-primary btn-sm">Periksa Masuk</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div data-table-empty hidden><x-ui.table-state state="filtered" /></div>
        </div>
        <div class="flex flex-col items-center justify-between gap-3 border-t px-5 py-3 sm:flex-row sm:px-6">
            <p data-table-info class="text-sm text-muted-foreground"></p>
            <div data-table-pager class="flex items-center gap-1"></div>
        </div>
    </div>
</x-layouts.admin>
