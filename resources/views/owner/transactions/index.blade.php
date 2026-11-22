<x-layouts.admin title="Transaksi Lintas Bagian" group="Pemantauan PKDI" role="owner">
    <x-ui.page-header title="Daftar Transaksi Operasional Lintas Bagian" description="Pantau status operasional Gudang, penagihan Keuangan, dan pembayaran Client secara terpisah (Read-Only)." />

    <div class="card p-5 sm:p-6">
        <form method="GET" action="{{ route('owner.transactions.index') }}" class="grid gap-3 md:grid-cols-12 md:items-end">
            <div class="md:col-span-4">
                <label for="own-tx-q" class="label mb-1.5">Cari Pesanan / Produk</label>
                <input id="own-tx-q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="input" placeholder="Nomor pesanan, nama PT, produk, atau layanan">
            </div>
            <div class="md:col-span-3">
                <label for="own-tx-client" class="label mb-1.5">Perusahaan Client</label>
                <select id="own-tx-client" name="client_company_id" class="select">
                    <option value="">Semua Client</option>
                    @foreach ($clients as $client)
                        <option value="{{ $client->id }}" @selected((string) ($filters['client_company_id'] ?? '') === (string) $client->id)>{{ $client->company_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="md:col-span-3">
                <label for="own-tx-type" class="label mb-1.5">Jenis Transaksi</label>
                <select id="own-tx-type" name="transaction_type" class="select">
                    <option value="">Semua Jenis Transaksi</option>
                    <option value="RECONDITIONING" @selected(($filters['transaction_type'] ?? '') === 'RECONDITIONING')>Rekondisi Drum</option>
                    <option value="WASTE_TRANSPORT" @selected(in_array($filters['transaction_type'] ?? '', ['WASTE_TRANSPORT', 'DRUM_SALE'], true))>Pengangkutan Limbah</option>
                </select>
            </div>
            <div class="md:col-span-2">
                <button type="submit" class="btn btn-primary w-full">Terapkan Filter</button>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="flex items-center justify-between gap-3 p-5 sm:p-6">
            <div>
                <h2 class="card-title">Seluruh Transaksi PKDI</h2>
                <p class="card-desc mt-1">Menampilkan {{ count($orders) }} pesanan lintas divisi.</p>
            </div>
        </div>
        <div class="overflow-x-auto border-t">
            <table class="table min-w-[820px]">
                <thead>
                    <tr>
                        <th>No. Pesanan</th>
                        <th>Perusahaan Client</th>
                        <th>Transaksi & Produk</th>
                        <th>Status Operasional</th>
                        <th>Status Penagihan</th>
                        <th>Status Pembayaran</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($orders as $order)
                        @php($st = \App\Support\PkdiDemoData::statusMeta($order['order_status']))
                        @php($bst = $order['billing_status_meta'])
                        @php($pst = $order['payment_status_meta'])
                        <tr>
                            <td class="whitespace-nowrap">
                                <p class="font-semibold tabular-nums">{{ $order['order_number'] }}</p>
                                <p class="text-xs text-muted-foreground">{{ $order['submitted_at'] }}</p>
                            </td>
                            <td>{{ $order['client_company'] }}</td>
                            <td>
                                <p class="font-medium">{{ $order['product_name'] }} · {{ $order['service_type'] }}</p>
                                <p class="text-xs text-muted-foreground">{{ $order['transaction_type_label'] }} ({{ $order['requested_qty'] }} {{ $order['unit'] }})</p>
                            </td>
                            <td><x-ui.badge :variant="$st['tone']" :dot="true">{{ $st['label'] }}</x-ui.badge></td>
                            <td><x-ui.badge :variant="$bst['tone']">{{ $bst['label'] }}</x-ui.badge></td>
                            <td><x-ui.badge :variant="$pst['tone']">{{ $pst['label'] }}</x-ui.badge></td>
                            <td class="text-right whitespace-nowrap">
                                <a href="{{ route('owner.transactions.show', $order['order_slug']) }}" class="btn btn-outline btn-sm">Lihat Detail</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-10 text-center text-muted-foreground">Belum ada transaksi yang sesuai dengan filter saat ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.admin>
