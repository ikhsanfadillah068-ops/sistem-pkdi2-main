<x-layouts.admin title="Surat Jalan Keluar" group="Operasional Gudang" role="kepala_gudang">
    <x-ui.page-header title="Daftar Penerbitan Surat Jalan" description="Surat Jalan hanya dapat diterbitkan setelah sistem memastikan tagihan berstatus Lunas." />

    <div class="card" data-table data-page-size="8">
        <div class="overflow-x-auto">
            <table class="table min-w-[680px]">
                <thead>
                    <tr><th>No. Pesanan</th><th>Perusahaan PT</th><th>Status Pembayaran</th><th>No. Surat Jalan</th><th class="text-right">Aksi</th></tr>
                </thead>
                <tbody>
                    @foreach ($orders as $order)
                        @php($isPaid = $order['payment_status'] === 'PAID')
                        <tr>
                            <td class="font-semibold tabular-nums whitespace-nowrap">{{ $order['order_number'] }}</td>
                            <td>{{ $order['client_company'] }}</td>
                            <td>
                                <x-ui.badge :variant="$isPaid ? 'success' : 'danger'" :dot="true">
                                    {{ $isPaid ? 'Lunas (Siap Terbit)' : 'Belum Lunas (Terkunci)' }}
                                </x-ui.badge>
                            </td>
                            <td class="tabular-nums">{{ $order['delivery_order']['do_number'] ?? '-' }}</td>
                            <td class="text-right">
                                <a href="{{ route('warehouse.delivery-orders.show', \App\Support\PkdiDemoData::orderSlug($order['order_number'])) }}" class="btn {{ $isPaid ? 'btn-primary' : 'btn-outline' }} btn-sm">
                                    {{ !empty($order['delivery_order']) ? 'Pratinjau Surat Jalan' : ($isPaid ? 'Terbitkan Surat Jalan' : 'Lihat Status Terkunci') }}
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.admin>
