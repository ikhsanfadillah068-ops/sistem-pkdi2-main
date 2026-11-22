@php
    $totalInvoiced = $summary['total_invoiced'] ?? 0;
    $totalReceivable = $summary['total_receivable'] ?? 0;
    $totalPaid = $summary['total_paid'] ?? 0;
    $totalExpenses = $summary['total_expenses'] ?? 0;
    $invoiceCount = $summary['invoice_count'] ?? 0;
    $unpaidCount = $summary['unpaid_invoice_count'] ?? 0;
    $expenseCount = $summary['expense_count'] ?? 0;

    $pendingPayments = [];
    foreach ($invoices as $inv) {
        foreach ($inv['payments'] ?? [] as $p) {
            if (($p['status'] ?? '') === 'PENDING_VERIFICATION') {
                $pendingPayments[] = ['invoice' => $inv, 'payment' => $p];
            }
        }
    }
@endphp
<x-layouts.admin title="Ringkasan Keuangan" group="Keuangan" role="keuangan">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <x-ui.page-header title="Dashboard Divisi Keuangan" description="Kelola antrean transaksi siap ditagihkan, invoice gabungan per Client, verifikasi bukti transfer manual, biaya operasional, dan buku kas." />
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('finance.billable.index') }}" class="btn btn-outline btn-sm">
                <x-ui.icon name="clipboard-check" />
                Antrean Penagihan ({{ count($billableTransactions) }})
            </a>
            <a href="{{ route('finance.expenses.index') }}" class="btn btn-outline btn-sm">
                <x-ui.icon name="plus" />
                Catat Biaya
            </a>
            <a href="{{ route('finance.reports.index') }}" class="btn btn-primary btn-sm">
                <x-ui.icon name="file-spreadsheet" />
                Laporan & Ekspor
            </a>
        </div>
    </div>

    <section aria-label="Ringkasan kas periode dari transaksi tersimpan" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat-card title="Total Tagihan Terbit" :value="'Rp '.number_format($totalInvoiced, 0, ',', '.')" icon="receipt" :note="$invoiceCount.' invoice diterbitkan'" />
        <x-ui.stat-card title="Piutang Belum Lunas" :value="'Rp '.number_format($totalReceivable, 0, ',', '.')" icon="clock" tile="bg-warning-soft text-warning" :note="$unpaidCount.' invoice belum lunas'" />
        <x-ui.stat-card title="Pembayaran Terverifikasi" :value="'Rp '.number_format($totalPaid, 0, ',', '.')" icon="wallet" tile="bg-success-soft text-success" note="Tercatat otomatis ke Buku Kas" />
        <x-ui.stat-card title="Biaya Operasional" :value="'Rp '.number_format($totalExpenses, 0, ',', '.')" icon="dollar" tile="bg-info-soft text-info" :note="$expenseCount.' transaksi tercatat'" />
    </section>

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="card min-w-0">
            <div class="flex items-center justify-between gap-3 p-5 sm:p-6 pb-4">
                <div>
                    <h2 class="card-title">Antrean Transaksi Siap Ditagihkan</h2>
                    <p class="card-desc mt-1">Pilih satu atau beberapa transaksi milik Client yang sama untuk digabung ke satu invoice.</p>
                </div>
                <a href="{{ route('finance.billable.index') }}" class="btn btn-primary btn-sm">Buat Invoice</a>
            </div>
            <div class="overflow-x-auto border-t">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Kode / Pesanan</th>
                            <th>Perusahaan Client</th>
                            <th>Transaksi & Kuantitas</th>
                            <th class="text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse (array_slice($billableTransactions, 0, 6) as $tx)
                            <tr>
                                <td class="whitespace-nowrap">
                                    <p class="font-semibold tabular-nums">{{ $tx['transaction_code'] }}</p>
                                    <p class="text-xs text-muted-foreground tabular-nums">{{ $tx['order_number'] }}</p>
                                </td>
                                <td>{{ $tx['client_company'] }}</td>
                                <td>
                                    <p class="font-medium tabular-nums">{{ $tx['billable_quantity'] }} {{ $tx['unit'] }} · {{ $tx['product_name'] }}</p>
                                    <p class="text-xs text-muted-foreground">{{ $tx['transaction_type_label'] }} ({{ $tx['service_name'] }})</p>
                                </td>
                                <td class="text-right">
                                    <a href="{{ route('finance.billable.index', ['client_company_id' => $tx['client_company_id']]) }}" class="btn btn-outline btn-sm">Tagihkan</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-8 text-center text-muted-foreground">Belum ada transaksi selesai Gudang yang menunggu penagihan.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card min-w-0">
            <div class="flex items-center justify-between gap-3 p-5 sm:p-6 pb-4">
                <div>
                    <h2 class="card-title">Bukti Transfer Menunggu Verifikasi</h2>
                    <p class="card-desc mt-1">Unggahan bukti transfer Client baru sah menjadi Lunas setelah diverifikasi Keuangan.</p>
                </div>
                <a href="{{ route('finance.invoices.index') }}" class="btn btn-outline btn-sm">Semua Invoice</a>
            </div>
            <div class="overflow-x-auto border-t">
                <table class="table">
                    <thead>
                        <tr>
                            <th>No. Invoice</th>
                            <th>Client</th>
                            <th class="text-right">Nominal Bukti</th>
                            <th class="text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($pendingPayments as $row)
                            <tr>
                                <td class="font-semibold tabular-nums whitespace-nowrap">{{ $row['invoice']['invoice_number'] }}</td>
                                <td>{{ $row['invoice']['client_company'] }}</td>
                                <td class="text-right font-medium tabular-nums whitespace-nowrap">Rp {{ number_format($row['payment']['amount'], 0, ',', '.') }}</td>
                                <td class="text-right">
                                    <a href="{{ route('finance.invoices.show', $row['invoice']['invoice_slug']) }}" class="btn btn-warning btn-sm">Verifikasi Bayar</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-8 text-center text-muted-foreground">Tidak ada bukti pembayaran yang menunggu verifikasi.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-layouts.admin>
