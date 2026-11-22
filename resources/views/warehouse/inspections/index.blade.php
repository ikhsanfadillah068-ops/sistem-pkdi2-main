<x-layouts.admin title="Penerimaan & Penyortiran Gudang" group="Operasional Gudang" role="kepala_gudang">
    <x-ui.page-header
        title="Penerimaan, Pemeriksaan & Penyortiran Drum"
        description="Daftar pesanan Client yang menunggu peninjauan awal, penerimaan fisik drum, serta pemeriksaan dan penyortiran." />

    <div class="card p-5 sm:p-6">
        <form method="GET" action="{{ route('warehouse.inspections.index') }}" class="grid gap-3 md:grid-cols-12 md:items-end">
            <div class="md:col-span-5">
                <label for="wh-ins-q" class="label mb-1.5">Cari Pesanan / Client / PO</label>
                <input id="wh-ins-q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="input" placeholder="Nomor pesanan, nama PT, produk, atau PO">
            </div>
            <div class="md:col-span-3">
                <label for="wh-ins-type" class="label mb-1.5">Jenis Transaksi</label>
                <select id="wh-ins-type" name="transaction_type" class="select">
                    <option value="">Semua Jenis Transaksi</option>
                    <option value="RECONDITIONING" @selected(($filters['transaction_type'] ?? '') === 'RECONDITIONING')>Rekondisi Drum</option>
                    <option value="WASTE_TRANSPORT" @selected(in_array($filters['transaction_type'] ?? '', ['WASTE_TRANSPORT', 'DRUM_SALE'], true))>Pengangkutan Limbah</option>
                </select>
            </div>
            <div class="md:col-span-2">
                <label for="wh-ins-status" class="label mb-1.5">Tahap</label>
                <select id="wh-ins-status" name="status" class="select">
                    <option value="">Semua Tahap</option>
                    <option value="UNDER_REVIEW" @selected(($filters['status'] ?? '') === 'UNDER_REVIEW')>Ditinjau Gudang</option>
                    <option value="NEEDS_CLARIFICATION" @selected(($filters['status'] ?? '') === 'NEEDS_CLARIFICATION')>Perlu Klarifikasi</option>
                    <option value="TRANSPORT_SCHEDULED" @selected(($filters['status'] ?? '') === 'TRANSPORT_SCHEDULED')>Pengangkutan Dijadwalkan</option>
                    <option value="IN_TRANSIT" @selected(($filters['status'] ?? '') === 'IN_TRANSIT')>Dalam Perjalanan</option>
                    <option value="WAITING_RECEIPT" @selected(($filters['status'] ?? '') === 'WAITING_RECEIPT')>Menunggu Drum Masuk</option>
                    <option value="RECEIVED" @selected(($filters['status'] ?? '') === 'RECEIVED')>Diterima di PKDI</option>
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
                <h2 class="card-title">Antrean Penerimaan & Penyortiran</h2>
                <p class="card-desc mt-1">Menampilkan {{ count($orders) }} pesanan yang memerlukan tindakan Gudang.</p>
            </div>
        </div>
        <div class="overflow-x-auto border-t">
            <table class="table min-w-[720px]">
                <thead>
                    <tr>
                        <th>No. Pesanan</th>
                        <th>Perusahaan Client</th>
                        <th>Transaksi & Produk</th>
                        <th>Pengajuan / Diterima</th>
                        <th>Tgl Kebutuhan</th>
                        <th>Status</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($orders as $order)
                        @php($st = \App\Support\PkdiDemoData::statusMeta($order['order_status']))
                        <tr>
                            <td class="whitespace-nowrap">
                                <p class="font-semibold tabular-nums">{{ $order['order_number'] }}</p>
                                <p class="text-xs text-muted-foreground">{{ $order['submitted_at'] }}</p>
                            </td>
                            <td>
                                <p class="font-medium">{{ $order['client_company'] }}</p>
                                @if (!empty($order['po_number']))
                                    <p class="text-xs text-muted-foreground tabular-nums">PO: {{ $order['po_number'] }}</p>
                                @endif
                            </td>
                            <td>
                                <p class="font-medium">{{ $order['product_name'] }} · {{ $order['service_type'] }}</p>
                                <p class="text-xs text-muted-foreground">{{ $order['transaction_type_label'] }}</p>
                            </td>
                            <td class="tabular-nums whitespace-nowrap">
                                {{ $order['requested_qty'] }} {{ $order['unit'] }}
                                @if (!empty($order['receipt']))
                                    <p class="text-xs text-success">Diterima: {{ $order['receipt']['actual_received_quantity'] }} {{ $order['receipt']['unit'] }}</p>
                                @endif
                            </td>
                            <td class="tabular-nums whitespace-nowrap">{{ $order['needed_date'] ?? '-' }}</td>
                            <td><x-ui.badge :variant="$st['tone']" :dot="true">{{ $st['label'] }}</x-ui.badge></td>
                            <td class="text-right">
                                <a href="{{ route('warehouse.orders.show', $order['order_slug']) }}" class="btn btn-outline btn-sm">
                                    @if ($order['order_status'] === 'RECEIVED')
                                        Periksa & Sortir
                                    @elseif ($order['order_status'] === 'WAITING_RECEIPT')
                                        Catat Penerimaan
                                    @else
                                        Tinjau Pesanan
                                    @endif
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-12 text-center text-muted-foreground">
                                @if (!empty($filters['q']) || !empty($filters['transaction_type']) || !empty($filters['status']))
                                    Tidak ada pesanan pada antrean yang cocok dengan kriteria filter saat ini.
                                @else
                                    Belum ada pesanan baru yang menunggu peninjauan, penerimaan fisik, atau penyortiran Gudang.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.admin>
