<x-layouts.admin title="Proses & Hasil Kerja" group="Operasional Gudang" role="kepala_gudang">
    <x-ui.page-header
        title="Proses Pengerjaan, Hasil Akhir & Penyerahan"
        description="Pantau pekerjaan rekondisi, pengangkutan limbah, hasil akhir layak, status antrean penagihan, dan penyerahan ke Client." />

    <div class="card p-5 sm:p-6">
        <form method="GET" action="{{ route('warehouse.processes.index') }}" class="grid gap-3 md:grid-cols-12 md:items-end">
            <div class="md:col-span-4">
                <label for="wh-proc-q" class="label mb-1.5">Cari Pesanan / Client</label>
                <input id="wh-proc-q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="input" placeholder="Nomor pesanan, nama PT, atau produk">
            </div>
            <div class="md:col-span-3">
                <label for="wh-proc-type" class="label mb-1.5">Jenis Transaksi</label>
                <select id="wh-proc-type" name="transaction_type" class="select">
                    <option value="">Semua Jenis Transaksi</option>
                    <option value="RECONDITIONING" @selected(($filters['transaction_type'] ?? '') === 'RECONDITIONING')>Rekondisi Drum</option>
                    <option value="WASTE_TRANSPORT" @selected(in_array($filters['transaction_type'] ?? '', ['WASTE_TRANSPORT', 'DRUM_SALE'], true))>Pengangkutan Limbah</option>
                </select>
            </div>
            <div class="md:col-span-3">
                <label for="wh-proc-pkg" class="label mb-1.5">Paket Rekondisi</label>
                <select id="wh-proc-pkg" name="reconditioning_service_id" class="select">
                    <option value="">Semua Paket</option>
                    @foreach ($reconditioningServices as $svc)
                        <option value="{{ $svc->id }}" @selected((string) ($filters['reconditioning_service_id'] ?? '') === (string) $svc->id)>{{ $svc->name }}</option>
                    @endforeach
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
                <h2 class="card-title">Daftar Pekerjaan Aktif & Riwayat Hasil</h2>
                <p class="card-desc mt-1">Total {{ count($orders) }} pekerjaan Gudang tercatat.</p>
            </div>
            <a href="{{ route('warehouse.reports.index') }}" class="btn btn-outline btn-sm">
                <x-ui.icon name="file-spreadsheet" />
                Ekspor Laporan
            </a>
        </div>
        <div class="overflow-x-auto border-t">
            <table class="table min-w-[760px]">
                <thead>
                    <tr>
                        <th>No. Pesanan</th>
                        <th>Perusahaan Client</th>
                        <th>Produk & Layanan</th>
                        <th>Kuantitas Proses / Selesai</th>
                        <th>Status Operasional</th>
                        <th>Status Penagihan</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($orders as $order)
                        @php($st = \App\Support\PkdiDemoData::statusMeta($order['order_status']))
                        @php($bst = $order['billing_status_meta'])
                        <tr>
                            <td class="font-semibold tabular-nums whitespace-nowrap">{{ $order['order_number'] }}</td>
                            <td>{{ $order['client_company'] }}</td>
                            <td>
                                <p class="font-medium">{{ $order['product_name'] }} · {{ $order['service_type'] }}</p>
                                <p class="text-xs text-muted-foreground">{{ $order['transaction_type_label'] }}</p>
                            </td>
                            <td class="tabular-nums whitespace-nowrap">
                                @if (!empty($order['result']))
                                    <span class="font-medium">{{ $order['result']['completed_qty'] }} {{ $order['unit'] }} selesai</span>
                                    @if ($order['result']['rejected_qty'] > 0)
                                        <p class="text-xs text-danger">{{ $order['result']['rejected_qty'] }} tidak layak</p>
                                    @endif
                                @else
                                    {{ $order['sorting']['processable_qty'] ?? ($order['inspection']['actual_quantity'] ?? $order['requested_qty']) }} {{ $order['unit'] }}
                                @endif
                            </td>
                            <td><x-ui.badge :variant="$st['tone']" :dot="true">{{ $st['label'] }}</x-ui.badge></td>
                            <td><x-ui.badge :variant="$bst['tone']">{{ $bst['label'] }}</x-ui.badge></td>
                            <td class="text-right">
                                <a href="{{ route('warehouse.orders.show', $order['order_slug']) }}" class="btn btn-primary btn-sm">
                                    {{ in_array($order['order_status'], ['PREPARING', 'IN_PROGRESS'], true) ? 'Kelola Proses' : 'Lihat / Serah Terima' }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-12 text-center text-muted-foreground">
                                @if (!empty($filters['q']) || !empty($filters['transaction_type']) || !empty($filters['reconditioning_service_id']))
                                    Tidak ada pekerjaan Gudang yang sesuai dengan filter yang dipilih.
                                @else
                                    Belum ada pekerjaan rekondisi atau persiapan produk yang tercatat.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.admin>
