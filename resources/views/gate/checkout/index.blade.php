<x-layouts.admin title="Pemeriksaan Kendaraan Keluar" group="Pos Gerbang" role="satpam">
    <x-ui.page-header title="Antrean Pemeriksaan Surat Jalan dan Keluar" description="Kendaraan hanya diizinkan keluar apabila tagihan Lunas dan memiliki Surat Jalan sah yang belum pernah dipakai." />

    <div class="card" data-table data-page-size="8">
        <div class="flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6">
            <div>
                <h2 class="card-title">Daftar Kendaraan Keluar & Syarat Pelunasan</h2>
                <p class="card-desc mt-1">Cari nomor Surat Jalan, nomor polisi, atau nomor pesanan.</p>
            </div>
            <input type="search" class="input sm:w-64" placeholder="Cari Surat Jalan atau nopol..." aria-label="Cari kendaraan keluar" data-table-search>
        </div>
        <div class="overflow-x-auto border-t">
            <table class="table min-w-[680px]">
                <thead>
                    <tr>
                        <th>No. Surat Jalan</th>
                        <th>No. Pesanan & PT</th>
                        <th>Nopol</th>
                        <th>Status Tagihan</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($orders as $order)
                        @php($isPaid = $order['payment_status'] === 'PAID')
                        @php($hasDo = !empty($order['delivery_order']))
                        <tr>
                            <td class="font-semibold tabular-nums">{{ $order['delivery_order']['do_number'] ?? 'Belum Terbit' }}</td>
                            <td>
                                <p class="font-medium tabular-nums whitespace-nowrap">{{ $order['order_number'] }}</p>
                                <p class="text-xs text-muted-foreground">{{ $order['client_company'] }}</p>
                            </td>
                            <td class="font-medium tabular-nums whitespace-nowrap">{{ $order['delivery_order']['license_plate'] ?? $order['license_plate'] }}</td>
                            <td>
                                <x-ui.badge :variant="$isPaid ? 'success' : 'danger'" :dot="true">
                                    {{ $isPaid ? 'Lunas' : 'Belum Lunas (Diblokir)' }}
                                </x-ui.badge>
                            </td>
                            <td class="text-right">
                                <a href="{{ route('gate.checkout.show', \App\Support\PkdiDemoData::orderSlug($order['order_number'])) }}" class="btn {{ ($isPaid && $hasDo) ? 'btn-success' : 'btn-outline' }} btn-sm">
                                    {{ ($isPaid && $hasDo) ? 'Periksa Keluar' : 'Lihat Status Blokir' }}
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div data-table-empty hidden><x-ui.table-state state="filtered" /></div>
        </div>
    </div>
</x-layouts.admin>
