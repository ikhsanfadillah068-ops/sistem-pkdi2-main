<x-layouts.admin title="Antrean Gudang" group="Operasional Gudang" role="kepala_gudang">
    <x-ui.page-header
        title="Dashboard Operasional Kepala Gudang"
        description="Kelola peninjauan pesanan, penerimaan fisik dan penyortiran drum, proses rekondisi, persiapan penjualan drum, serta penyerahan barang.">
        <x-slot:actions>
            <a href="{{ route('warehouse.reports.index') }}" class="btn btn-outline">
                <x-ui.icon name="file-spreadsheet" />
                Laporan Gudang
            </a>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat-card title="Perlu Ditinjau" :value="$stats['needs_review'].' Pesanan'" note="Antrean awal Client" tile="bg-info-soft text-info" icon="clipboard-check" />
        <x-ui.stat-card title="Terima & Sortir Drum" :value="$stats['waiting_receipt'].' Pesanan'" note="Tahap fisik rekondisi" tile="bg-warning-soft text-warning" icon="package" />
        <x-ui.stat-card title="Proses & Persiapan" :value="$stats['active_work'].' Pesanan'" note="Rekondisi & penjualan aktif" tile="bg-primary-soft text-primary" icon="sliders" />
        <x-ui.stat-card title="Siap Serah & Selesai" :value="$stats['ready_or_completed'].' Pesanan'" note="Tercatat ke antrean tagih" tile="bg-success-soft text-success" icon="truck" />
    </div>

    <div class="card p-5 sm:p-6">
        <x-ui.tabs id="wh-tabs" variant="underline" aria-label="Antrean operasional gudang" :tabs="[
            'review' => 'Perlu Ditinjau ('.count($needsReview).')',
            'receipt' => 'Terima & Sortir ('.count($waitingReceiptOrSort).')',
            'active' => 'Proses & Persiapan ('.count($activeWork).')',
            'ready' => 'Siap Serah & Riwayat ('.count($readyOrCompleted).')',
        ]">
            <x-slot:tab_review>
                <div class="overflow-x-auto rounded-lg border">
                    <table class="table min-w-[700px]">
                        <thead>
                            <tr>
                                <th>No. Pesanan</th>
                                <th>Perusahaan Client</th>
                                <th>Transaksi & Produk</th>
                                <th>Kuantitas</th>
                                <th>Status</th>
                                <th class="text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($needsReview as $order)
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
                                    <td class="tabular-nums whitespace-nowrap">{{ $order['requested_qty'] }} {{ $order['unit'] }}</td>
                                    <td><x-ui.badge :variant="$st['tone']" :dot="true">{{ $st['label'] }}</x-ui.badge></td>
                                    <td class="text-right">
                                        <a href="{{ route('warehouse.orders.show', $order['order_slug']) }}" class="btn btn-primary btn-sm">Tinjau Pesanan</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-10 text-center text-muted-foreground">Belum ada pesanan baru yang menunggu peninjauan Kepala Gudang.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-slot:tab_review>

            <x-slot:tab_receipt>
                <div class="overflow-x-auto rounded-lg border">
                    <table class="table min-w-[700px]">
                        <thead>
                            <tr>
                                <th>No. Pesanan</th>
                                <th>Perusahaan Client</th>
                                <th>Produk & Paket Rekondisi</th>
                                <th>Pengajuan / Diterima</th>
                                <th>Status</th>
                                <th class="text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($waitingReceiptOrSort as $order)
                                @php($st = \App\Support\PkdiDemoData::statusMeta($order['order_status']))
                                <tr>
                                    <td class="font-semibold tabular-nums whitespace-nowrap">{{ $order['order_number'] }}</td>
                                    <td>{{ $order['client_company'] }}</td>
                                    <td>{{ $order['product_name'] }} · {{ $order['service_type'] }}</td>
                                    <td class="tabular-nums whitespace-nowrap">
                                        {{ $order['requested_qty'] }} {{ $order['unit'] }}
                                        @if (!empty($order['receipt']))
                                            <span class="text-xs text-muted-foreground">(Diterima: {{ $order['receipt']['actual_received_quantity'] }})</span>
                                        @endif
                                    </td>
                                    <td><x-ui.badge :variant="$st['tone']" :dot="true">{{ $st['label'] }}</x-ui.badge></td>
                                    <td class="text-right">
                                        <a href="{{ route('warehouse.orders.show', $order['order_slug']) }}" class="btn btn-primary btn-sm">
                                            {{ $order['order_status'] === 'RECEIVED' ? 'Periksa & Sortir' : 'Catat Penerimaan' }}
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-10 text-center text-muted-foreground">Tidak ada pesanan rekondisi yang menunggu penerimaan fisik atau penyortiran.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-slot:tab_receipt>

            <x-slot:tab_active>
                <div class="overflow-x-auto rounded-lg border">
                    <table class="table min-w-[700px]">
                        <thead>
                            <tr>
                                <th>No. Pesanan</th>
                                <th>Perusahaan Client</th>
                                <th>Transaksi & Layanan</th>
                                <th>Kuantitas Diproses</th>
                                <th>Tahap Terakhir</th>
                                <th class="text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($activeWork as $order)
                                <tr>
                                    <td class="font-semibold tabular-nums whitespace-nowrap">{{ $order['order_number'] }}</td>
                                    <td>{{ $order['client_company'] }}</td>
                                    <td>
                                        <p class="font-medium">{{ $order['product_name'] }} · {{ $order['service_type'] }}</p>
                                        <p class="text-xs text-muted-foreground">{{ $order['transaction_type_label'] }}</p>
                                    </td>
                                    <td class="tabular-nums whitespace-nowrap">
                                        {{ $order['sorting']['processable_qty'] ?? ($order['inspection']['actual_quantity'] ?? $order['requested_qty']) }} {{ $order['unit'] }}
                                    </td>
                                    <td>{{ collect($order['processes'])->last()['stage'] ?? ($order['is_drum_sale'] ? 'Persiapan produk dimulai' : 'Proses rekondisi dimulai') }}</td>
                                    <td class="text-right">
                                        <a href="{{ route('warehouse.orders.show', $order['order_slug']) }}" class="btn btn-outline btn-sm">Perbarui / Selesaikan</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-10 text-center text-muted-foreground">Belum ada pekerjaan rekondisi atau persiapan produk yang sedang berjalan.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-slot:tab_active>

            <x-slot:tab_ready>
                <div class="overflow-x-auto rounded-lg border">
                    <table class="table min-w-[740px]">
                        <thead>
                            <tr>
                                <th>No. Pesanan</th>
                                <th>Perusahaan Client</th>
                                <th>Hasil Layak / Tolak</th>
                                <th>Status Operasional</th>
                                <th>Status Penagihan</th>
                                <th class="text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($readyOrCompleted as $order)
                                @php($st = \App\Support\PkdiDemoData::statusMeta($order['order_status']))
                                @php($bst = $order['billing_status_meta'])
                                <tr>
                                    <td class="font-semibold tabular-nums whitespace-nowrap">{{ $order['order_number'] }}</td>
                                    <td>{{ $order['client_company'] }}</td>
                                    <td class="tabular-nums whitespace-nowrap">
                                        {{ $order['result']['completed_qty'] ?? '-' }} {{ $order['unit'] }}
                                        @if (($order['result']['rejected_qty'] ?? 0) > 0)
                                            <span class="text-xs text-danger">({{ $order['result']['rejected_qty'] }} tidak layak)</span>
                                        @endif
                                    </td>
                                    <td><x-ui.badge :variant="$st['tone']" :dot="true">{{ $st['label'] }}</x-ui.badge></td>
                                    <td><x-ui.badge :variant="$bst['tone']">{{ $bst['label'] }}</x-ui.badge></td>
                                    <td class="text-right">
                                        <a href="{{ route('warehouse.orders.show', $order['order_slug']) }}" class="btn btn-outline btn-sm">
                                            {{ in_array($order['order_status'], ['READY', 'IN_DELIVERY'], true) ? 'Catat Penyerahan' : 'Lihat Detail' }}
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-10 text-center text-muted-foreground">Belum ada pekerjaan selesai atau siap diserahkan.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-slot:tab_ready>
        </x-ui.tabs>
    </div>
</x-layouts.admin>
