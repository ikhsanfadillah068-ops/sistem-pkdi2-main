@php
    $summary = $dataset['summary'] ?? [];
    $rows = $dataset['rows'] ?? [];
    $exportParams = array_filter([
        'from' => $filters['from'] ?? '',
        'to' => $filters['to'] ?? '',
        'transaction_type' => $filters['transaction_type'] ?? '',
        'reconditioning_service_id' => $filters['reconditioning_service_id'] ?? '',
        'status' => $filters['status'] ?? '',
        'q' => $filters['q'] ?? '',
    ], static fn ($v): bool => $v !== '');
@endphp
<x-layouts.admin title="Laporan Gudang" group="Operasional Gudang" role="kepala_gudang">
    <x-ui.page-header
        title="Riwayat & Laporan Operasional Gudang"
        description="Rekapitulasi koordinasi pengangkutan, penerimaan fisik drum/limbah, penyortiran, hasil pekerjaan rekondisi, dan kesiapan penagihan." />

    <div class="card p-5 sm:p-6">
        <h2 class="card-title mb-4">Filter Laporan Operasional</h2>
        <form method="GET" action="{{ route('warehouse.reports.index') }}" class="space-y-4">
            <div class="grid gap-4 md:grid-cols-12 md:items-end">
                <div class="md:col-span-3">
                    <label for="wh-rep-from" class="label mb-1.5">Dari Tanggal</label>
                    <input id="wh-rep-from" type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="input">
                </div>
                <div class="md:col-span-3">
                    <label for="wh-rep-to" class="label mb-1.5">Sampai Tanggal</label>
                    <input id="wh-rep-to" type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="input">
                </div>
                <div class="md:col-span-3">
                    <label for="wh-rep-type" class="label mb-1.5">Jenis Transaksi</label>
                    <select id="wh-rep-type" name="transaction_type" class="select">
                        <option value="">Semua Jenis Transaksi</option>
                        <option value="RECONDITIONING" @selected(($filters['transaction_type'] ?? '') === 'RECONDITIONING')>Rekondisi Drum</option>
                        <option value="WASTE_TRANSPORT" @selected(in_array($filters['transaction_type'] ?? '', ['WASTE_TRANSPORT', 'DRUM_SALE'], true))>Pengangkutan Limbah</option>
                    </select>
                </div>
                <div class="md:col-span-3">
                    <label for="wh-rep-pkg" class="label mb-1.5">Paket Rekondisi</label>
                    <select id="wh-rep-pkg" name="reconditioning_service_id" class="select">
                        <option value="">Semua Paket</option>
                        @foreach ($reconditioningServices as $svc)
                            <option value="{{ $svc->id }}" @selected((string) ($filters['reconditioning_service_id'] ?? '') === (string) $svc->id)>{{ $svc->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-12 md:items-end">
                <div class="md:col-span-3">
                    <label for="wh-rep-status" class="label mb-1.5">Status Operasional</label>
                    <select id="wh-rep-status" name="status" class="select">
                        <option value="">Semua Status</option>
                        @foreach (['UNDER_REVIEW', 'WAITING_RECEIPT', 'RECEIVED', 'PREPARING', 'IN_PROGRESS', 'READY', 'IN_DELIVERY', 'COMPLETED', 'CANCELLED'] as $stCode)
                            @php($stMeta = \App\Support\PkdiDemoData::statusMeta($stCode))
                            <option value="{{ $stCode }}" @selected(($filters['status'] ?? '') === $stCode)>{{ $stMeta['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="md:col-span-4">
                    <label for="wh-rep-q" class="label mb-1.5">Kata Kunci (Pesanan / PT / PO)</label>
                    <input id="wh-rep-q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="input" placeholder="Cari nomor pesanan, nama PT, atau PO">
                </div>
                <div class="md:col-span-5 flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-outline">Terapkan Filter</button>
                    <a href="{{ route('warehouse.reports.export', array_merge($exportParams, ['format' => 'pdf'])) }}" class="btn btn-primary">
                        <x-ui.icon name="printer" />
                        Ekspor PDF
                    </a>
                    <a href="{{ route('warehouse.reports.export', array_merge($exportParams, ['format' => 'xlsx'])) }}" class="btn btn-outline">
                        <x-ui.icon name="file-spreadsheet" />
                        Ekspor Excel (.xlsx)
                    </a>
                </div>
            </div>
        </form>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat-card title="Total Pesanan" :value="number_format($summary['total_orders'] ?? 0, 0, ',', '.').' Pesanan'" note="Sesuai periode filter" tile="bg-primary-soft text-primary" icon="package" />
        <x-ui.stat-card title="Total Selesai Layak" :value="number_format($summary['total_completed'] ?? 0, 0, ',', '.').' Unit'" note="Dasar kuantitas tagih" tile="bg-success-soft text-success" icon="check-circle" />
        <x-ui.stat-card title="Tidak Layak / Reject" :value="number_format($summary['total_rejected'] ?? 0, 0, ',', '.').' Unit'" note="Tercatat terpisah" tile="bg-danger-soft text-danger" icon="alert-triangle" />
        <x-ui.stat-card title="Siap Ditagihkan" :value="number_format($summary['ready_to_bill_count'] ?? 0, 0, ',', '.').' Transaksi'" note="Menunggu invoice Keuangan" tile="bg-warning-soft text-warning" icon="clipboard-check" />
    </div>

    <div class="card">
        <div class="flex items-center justify-between gap-3 p-5 sm:p-6">
            <div>
                <h2 class="card-title">Rincian Transaksi Operasional Gudang</h2>
                <p class="card-desc mt-1">Menampilkan {{ count($rows) }} baris data operasional.</p>
            </div>
        </div>
        <div class="overflow-x-auto border-t">
            <table class="table min-w-[820px]">
                <thead>
                    <tr>
                        <th>No. Pesanan</th>
                        <th>Tanggal</th>
                        <th>Perusahaan Client</th>
                        <th>Transaksi, Produk & Layanan</th>
                        <th class="text-right">Diminta / Diterima</th>
                        <th class="text-right">Selesai / Tolak</th>
                        <th>Operasional</th>
                        <th>Penagihan</th>
                        <th class="text-right">Detail</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td class="font-semibold tabular-nums whitespace-nowrap">{{ $row['order_number'] }}</td>
                            <td class="tabular-nums whitespace-nowrap text-muted-foreground">{{ $row['date'] }}</td>
                            <td>{{ $row['client'] }}</td>
                            <td>
                                <p class="font-medium">{{ $row['product'] }} · {{ $row['service'] }}</p>
                                <p class="text-xs text-muted-foreground">{{ $row['transaction_type_label'] }}</p>
                            </td>
                            <td class="text-right tabular-nums whitespace-nowrap">
                                {{ $row['requested_qty'] }}
                                @if ($row['received_qty'] !== null)
                                    / <span class="font-medium">{{ $row['received_qty'] }}</span>
                                @endif
                                {{ $row['unit'] }}
                            </td>
                            <td class="text-right tabular-nums whitespace-nowrap">
                                <span class="font-semibold text-success">{{ $row['completed_qty'] }}</span>
                                /
                                <span class="{{ $row['rejected_qty'] > 0 ? 'font-semibold text-danger' : 'text-muted-foreground' }}">{{ $row['rejected_qty'] }}</span>
                                {{ $row['unit'] }}
                            </td>
                            <td><x-ui.badge :variant="$row['status_tone']" :dot="true">{{ $row['status_label'] }}</x-ui.badge></td>
                            <td>
                                <x-ui.badge :variant="$row['billing_status_meta']['tone']">{{ $row['billing_status_meta']['label'] }}</x-ui.badge>
                                @if (!empty($row['billable_code']))
                                    <p class="mt-0.5 text-xs text-muted-foreground tabular-nums">{{ $row['billable_code'] }}</p>
                                @endif
                            </td>
                            <td class="text-right">
                                <a href="{{ route('warehouse.orders.show', $row['order_slug']) }}" class="btn btn-outline btn-sm">Buka</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="py-12 text-center text-muted-foreground">Belum ada transaksi Gudang pada rentang filter yang dipilih.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.admin>
