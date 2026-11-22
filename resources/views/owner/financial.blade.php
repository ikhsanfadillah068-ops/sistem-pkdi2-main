@php
    $totalInvoiced = $summary['total_invoiced'] ?? 0;
    $totalPaid = $summary['total_paid'] ?? 0;
    $totalReceivable = $summary['total_receivable'] ?? 0;
    $totalExpenses = $summary['total_expenses'] ?? 0;
    $exportParams = array_filter([
        'from' => $from ?? request('from'),
        'to' => $to ?? request('to'),
    ], fn ($v) => $v !== null && $v !== '');
@endphp
<x-layouts.admin title="Pendapatan & Biaya" group="Pemantauan PKDI" role="owner">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <x-ui.page-header title="Ringkasan Pendapatan Jasa dan Biaya Operasional" description="Pantauan arus kas masuk, tagihan belum dibayar, dan rincian pengeluaran perusahaan dari transaksi tersimpan (Read-Only)." />
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('owner.reports.export', array_merge($exportParams, ['report_type' => 'cashbook', 'format' => 'pdf'])) }}" class="btn btn-primary btn-sm">
                <x-ui.icon name="printer" />
                Ekspor PDF Kas
            </a>
            <a href="{{ route('owner.reports.export', array_merge($exportParams, ['report_type' => 'cashbook', 'format' => 'xlsx'])) }}" class="btn btn-outline btn-sm">
                <x-ui.icon name="file-spreadsheet" />
                Ekspor Excel (.xlsx)
            </a>
        </div>
    </div>

    <x-ui.card title="Filter Periode Pemantauan Owner" description="Acuan tanggal: Tagihan mengikuti tanggal terbit invoice, Biaya Operasional mengikuti tanggal pengeluaran, dan Buku Kas mengikuti tanggal transaksi.">
        <form action="{{ route('owner.financial') }}" method="GET" class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="flex-1 min-w-[240px]">
                <x-ui.date-range id="owner-fin-range" fromName="from" toName="to" :from="$from ?? request('from', '')" :to="$to ?? request('to', '')" />
            </div>
            <div class="flex flex-wrap items-center gap-2 lg:pt-[1.625rem]">
                <button type="submit" class="btn btn-primary">Terapkan Periode</button>
            </div>
        </form>
    </x-ui.card>

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat-card title="Pendapatan Diterbitkan" :value="'Rp '.number_format($totalInvoiced, 0, ',', '.')" icon="receipt" note="Berdasarkan tanggal terbit invoice" />
        <x-ui.stat-card title="Pembayaran Diterima" :value="'Rp '.number_format($totalPaid, 0, ',', '.')" icon="check-circle" tile="bg-success-soft text-success" note="Pembayaran terverifikasi Keuangan" />
        <x-ui.stat-card title="Piutang Belum Lunas" :value="'Rp '.number_format($totalReceivable, 0, ',', '.')" icon="alert-circle" tile="bg-warning-soft text-warning" note="Sisa tagihan belum terbayar" />
        <x-ui.stat-card title="Biaya Operasional" :value="'Rp '.number_format($totalExpenses, 0, ',', '.')" icon="wallet" tile="bg-danger-soft text-danger" note="Berdasarkan tanggal pengeluaran" />
    </section>

    <div class="card p-5 sm:p-6">
        <x-ui.tabs id="owner-fin-tabs" variant="pill" aria-label="Rincian keuangan Owner" :tabs="[
            'income' => 'Tagihan & Pemasukan Jasa',
            'expense' => 'Rincian Biaya Operasional',
            'cashbook' => 'Arus Kas Buku Keuangan',
        ]">
            <x-slot:tab_income>
                <div class="overflow-x-auto rounded-lg border">
                    <table class="table">
                        <thead><tr><th>No. Invoice</th><th>Client</th><th class="text-right">Total</th><th class="text-right">Dibayar</th><th class="text-right">Sisa</th><th>Status</th><th class="text-right">Detail</th></tr></thead>
                        <tbody>
                            @forelse ($invoicedOrders as $order)
                                @php($inv = $order['invoice'])
                                <tr>
                                    <td class="font-semibold tabular-nums">{{ $inv['invoice_number'] }}</td>
                                    <td>{{ $order['client_company'] }}</td>
                                    <td class="text-right tabular-nums">Rp {{ number_format($inv['total'], 0, ',', '.') }}</td>
                                    <td class="text-right tabular-nums">Rp {{ number_format($inv['paid'], 0, ',', '.') }}</td>
                                    <td class="text-right tabular-nums">Rp {{ number_format($inv['balance'], 0, ',', '.') }}</td>
                                    <td>
                                        <x-ui.badge :variant="$inv['status'] === 'PAID' ? 'success' : 'warning'">
                                            {{ $inv['status'] === 'PAID' ? 'Lunas' : ($inv['status'] === 'PARTIALLY_PAID' ? 'Bayar Sebagian' : 'Menunggu Bayar') }}
                                        </x-ui.badge>
                                    </td>
                                    <td class="text-right">
                                        <a href="{{ route('owner.transactions.show', \App\Support\PkdiDemoData::orderSlug($order['order_number'])) }}" class="btn btn-outline btn-sm">Telusuri</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="py-8 text-center text-muted-foreground">Belum ada tagihan diterbitkan.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-slot:tab_income>

            <x-slot:tab_expense>
                <div class="overflow-x-auto rounded-lg border">
                    <table class="table">
                        <thead><tr><th>No. Pengeluaran</th><th>Tanggal</th><th>Kategori</th><th>Uraian & Vendor</th><th class="text-right">Nominal</th></tr></thead>
                        <tbody>
                            @forelse ($expenses as $exp)
                                <tr>
                                    <td class="font-semibold tabular-nums">{{ $exp['number'] }}</td>
                                    <td>{{ $exp['date'] }}</td>
                                    <td><span class="badge badge-outline">{{ $exp['category'] }}</span></td>
                                    <td>{{ $exp['description'] }} ({{ $exp['vendor'] }})</td>
                                    <td class="text-right font-medium tabular-nums">Rp {{ number_format($exp['amount'], 0, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-8 text-center text-muted-foreground">Belum ada biaya operasional tercatat.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-slot:tab_expense>

            <x-slot:tab_cashbook>
                <div class="overflow-x-auto rounded-lg border">
                    <table class="table">
                        <thead><tr><th>No. Kas</th><th>Tanggal Transaksi</th><th>Jenis</th><th>Keterangan</th><th class="text-right">Nominal</th></tr></thead>
                        <tbody>
                            @forelse ($cashbook as $row)
                                <tr>
                                    <td class="font-semibold tabular-nums">{{ $row['entry_number'] }}</td>
                                    <td>{{ $row['date'] }}</td>
                                    <td><x-ui.badge :variant="$row['type'] === 'IN' ? 'success' : 'danger'">{{ $row['type'] === 'IN' ? 'Masuk' : 'Keluar' }}</x-ui.badge></td>
                                    <td>{{ $row['source'] }}</td>
                                    <td class="text-right font-medium tabular-nums">Rp {{ number_format($row['amount'], 0, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-8 text-center text-muted-foreground">Belum ada mutasi pada buku kas.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-slot:tab_cashbook>
        </x-ui.tabs>
    </div>
</x-layouts.admin>
