@php
    $totalOrders = $operational['total_orders'] ?? count($orders);
    $reconditioningCount = $operational['reconditioning_orders'] ?? 0;
    $drumSaleCount = $operational['drum_sale_orders'] ?? 0;
    $warehouseActive = $operational['warehouse_active'] ?? 0;
    $warehouseCompleted = $operational['warehouse_completed'] ?? 0;
    $readyToBillCount = $operational['ready_to_bill_count'] ?? 0;

    $totalPaid = $summary['total_paid'] ?? 0;
    $totalExpenses = $summary['total_expenses'] ?? 0;
    $totalReceivable = $summary['total_receivable'] ?? 0;
    $trend6 = \App\Support\OrderPresenter::monthlyTrend(6);
    $trend12 = \App\Support\OrderPresenter::monthlyTrend(12);
@endphp
<x-layouts.admin title="Ringkasan Eksekutif" group="Pemantauan PKDI" role="owner">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <x-ui.page-header title="Ringkasan Operasional dan Keuangan PKDI" description="Pemantauan menyeluruh pengangkutan limbah, rekondisi drum, pekerjaan Gudang, penagihan, pembayaran, dan biaya operasional (Akses Read-Only)." />
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('owner.financial') }}" class="btn btn-outline btn-sm">Pendapatan & Biaya</a>
            <a href="{{ route('owner.audit-logs') }}" class="btn btn-primary btn-sm">
                <x-ui.icon name="history" />
                Jejak Audit Sistem
            </a>
        </div>
    </div>

    <section aria-label="Ringkasan operasional PRD 2.1" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat-card title="Total Pesanan" :value="$totalOrders.' Pesanan'" icon="package" :note="$reconditioningCount.' Rekondisi · '.$drumSaleCount.' Pengangkutan Limbah'" />
        <x-ui.stat-card title="Pekerjaan Gudang" :value="$warehouseActive.' Aktif'" icon="warehouse" tile="bg-info-soft text-info" :note="$warehouseCompleted.' selesai · '.$readyToBillCount.' siap ditagih'" />
        <x-ui.stat-card title="Pembayaran Diterima" :value="'Rp '.number_format($totalPaid, 0, ',', '.')" icon="wallet" tile="bg-success-soft text-success" note="Invoice terverifikasi lunas" />
        <x-ui.stat-card title="Piutang & Biaya" :value="'Rp '.number_format($totalReceivable, 0, ',', '.')" icon="clock" tile="bg-warning-soft text-warning" :note="'Biaya tercatat: Rp '.number_format($totalExpenses, 0, ',', '.')" />
    </section>

    <div class="grid gap-6 lg:grid-cols-7">
        <x-ui.card class="lg:col-span-4" title="Perbandingan Pemasukan Jasa vs Biaya Operasional (6 Bulan)" description="Grafik perbandingan dalam jutaan Rupiah untuk memantau selisih arus kas bulanan.">
            <x-ui.chart
                type="bar"
                height="260"
                prefix="Rp "
                suffix=" jt"
                :legend="true"
                aria="Grafik perbandingan pemasukan jasa dan biaya operasional"
                :labels="$trend6['labels']"
                :series="[
                    ['name' => 'Pemasukan Jasa', 'color' => '1', 'data' => $trend6['income']],
                    ['name' => 'Biaya Operasional', 'color' => '2', 'data' => $trend6['expense']],
                ]"
            />
        </x-ui.card>

        <x-ui.card class="lg:col-span-3" title="Aktivitas Terbaru Lintas Bagian" description="Ringkasan tindakan terbaru dari Client, Kepala Gudang, dan Keuangan.">
            <ul class="space-y-4">
                @forelse (array_slice($auditLogs, 0, 5) as $log)
                    <li class="flex items-start gap-3 border-b pb-3 last:border-b-0 last:pb-0">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full {{ $log['tile'] }}">
                            <x-ui.icon :name="$log['icon']" class="h-4 w-4" />
                        </span>
                        <div class="min-w-0 flex-1 leading-snug">
                            <p class="text-sm font-medium">{{ $log['detail'] }}</p>
                            <p class="mt-0.5 text-xs text-muted-foreground">{{ $log['role'] }} · {{ $log['user'] }} · {{ $log['time'] }}</p>
                        </div>
                    </li>
                @empty
                    <li class="py-6 text-center text-xs text-muted-foreground">Belum ada aktivitas tercatat.</li>
                @endforelse
            </ul>
        </x-ui.card>
    </div>

    <x-ui.card title="Tren Naik-Turun Pendapatan Jasa & Biaya Operasional (12 Bulan)" description="Grafik tren fluktuasi bulanan dalam jutaan Rupiah untuk memantau kinerja operasional.">
        <x-ui.chart
            type="area"
            height="270"
            prefix="Rp "
            suffix=" jt"
            :smooth="true"
            :legend="true"
            aria="Grafik tren naik turun pendapatan jasa dan biaya operasional"
            :labels="$trend12['labels']"
            :series="[
                ['name' => 'Pendapatan Jasa', 'color' => '1', 'data' => $trend12['income']],
                ['name' => 'Biaya Operasional', 'color' => '2', 'data' => $trend12['expense']],
            ]"
        />
    </x-ui.card>

    <div class="card">
        <div class="flex flex-wrap items-center justify-between gap-3 p-5 sm:p-6 pb-4">
            <div>
                <h2 class="card-title">Pantauan Pesanan & Status 3 Jalur</h2>
                <p class="card-desc mt-1">Klik salah satu transaksi untuk menelusuri detail operasional Gudang, penagihan Keuangan, dan pembayaran Client.</p>
            </div>
            <a href="{{ route('owner.transactions.index') }}" class="btn btn-outline btn-sm">Lihat Semua Transaksi</a>
        </div>
        <div class="overflow-x-auto border-t">
            <table class="table min-w-[780px]">
                <thead>
                    <tr>
                        <th>No. Pesanan</th>
                        <th>Perusahaan Client</th>
                        <th>Transaksi & Produk</th>
                        <th>Operasional</th>
                        <th>Penagihan</th>
                        <th class="text-right">Total Tagihan</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse (array_slice($orders, 0, 6) as $order)
                        @php($st = \App\Support\PkdiDemoData::statusMeta($order['order_status']))
                        @php($bst = $order['billing_status_meta'])
                        <tr>
                            <td class="font-semibold tabular-nums whitespace-nowrap">{{ $order['order_number'] }}</td>
                            <td>{{ $order['client_company'] }}</td>
                            <td>
                                <p class="font-medium">{{ $order['product_name'] }} · {{ $order['service_type'] }}</p>
                                <p class="text-xs text-muted-foreground">{{ $order['transaction_type_label'] }} ({{ $order['requested_qty'] }} {{ $order['unit'] }})</p>
                            </td>
                            <td><x-ui.badge :variant="$st['tone']" :dot="true">{{ $st['label'] }}</x-ui.badge></td>
                            <td><x-ui.badge :variant="$bst['tone']">{{ $bst['label'] }}</x-ui.badge></td>
                            <td class="text-right tabular-nums whitespace-nowrap">{{ !empty($order['invoice']) ? 'Rp '.number_format($order['invoice']['total'], 0, ',', '.') : '-' }}</td>
                            <td class="text-right"><a href="{{ route('owner.transactions.show', $order['order_slug']) }}" class="btn btn-outline btn-sm">Telusuri</a></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-muted-foreground">Belum ada transaksi pesanan tercatat.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.admin>
