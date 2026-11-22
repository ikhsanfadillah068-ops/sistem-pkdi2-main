@php
    $totalIn = $summary['total_in'] ?? collect($entries)->where('type', 'IN')->sum('amount');
    $totalOut = $summary['total_out'] ?? collect($entries)->where('type', 'OUT')->sum('amount');
    $netCash = $summary['net_cash'] ?? ($totalIn - $totalOut);
@endphp
<x-layouts.admin title="Buku Transaksi Keuangan" group="Kas & Pelaporan" role="keuangan">
    <x-ui.page-header title="Buku Transaksi Keuangan (Kas Masuk & Keluar)" description="Catatan terpadu pemasukan jasa terverifikasi dan pengeluaran biaya operasional berdasarkan tanggal transaksi kas (cashbook_entries.transaction_date)." />

    <section class="grid gap-4 sm:grid-cols-3">
        <x-ui.stat-card title="Total Kas Masuk" :value="'Rp '.number_format($totalIn, 0, ',', '.')" icon="trend-up" tile="bg-success-soft text-success" note="Dari pembayaran terverifikasi" />
        <x-ui.stat-card title="Total Kas Keluar" :value="'Rp '.number_format($totalOut, 0, ',', '.')" icon="trend-down" tile="bg-danger-soft text-danger" note="Dari biaya operasional tercatat" />
        <x-ui.stat-card title="Saldo Bersih Kas" :value="'Rp '.number_format($netCash, 0, ',', '.')" icon="wallet" note="Selisih kas masuk dikurangi kas keluar" />
    </section>

    <div class="card" data-table data-page-size="10">
        <div class="flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6">
            <div>
                <h2 class="card-title">Mutasi Buku Kas</h2>
                <p class="card-desc mt-1">Acuan tanggal: Tanggal pembayaran diverifikasi (Kas Masuk) dan tanggal pengeluaran biaya (Kas Keluar).</p>
            </div>
            <div class="flex flex-col gap-2 sm:flex-row">
                <input type="search" class="input sm:w-56" placeholder="Cari uraian atau referensi..." aria-label="Cari mutasi kas" data-table-search>
                <select class="select sm:w-40" aria-label="Filter jenis mutasi" data-table-filter>
                    <option value="">Semua Arus</option>
                    <option value="IN">Kas Masuk</option>
                    <option value="OUT">Kas Keluar</option>
                </select>
            </div>
        </div>
        <div class="overflow-x-auto border-t">
            <table class="table min-w-[680px]">
                <thead>
                    <tr><th>No. Buku Kas</th><th>Tanggal Transaksi</th><th>Jenis</th><th>Sumber & Keterangan</th><th>Referensi</th><th class="text-right">Nominal</th></tr>
                </thead>
                <tbody>
                    @foreach ($entries as $entry)
                        <tr data-status="{{ $entry['type'] }}">
                            <td class="font-semibold tabular-nums">{{ $entry['entry_number'] }}</td>
                            <td class="text-muted-foreground">{{ $entry['date'] }}</td>
                            <td>
                                <x-ui.badge :variant="$entry['type'] === 'IN' ? 'success' : 'danger'">
                                    {{ $entry['type'] === 'IN' ? 'Kas Masuk' : 'Kas Keluar' }}
                                </x-ui.badge>
                            </td>
                            <td>{{ $entry['source'] }}</td>
                            <td class="text-muted-foreground tabular-nums">{{ $entry['reference'] }}</td>
                            <td class="text-right font-semibold tabular-nums {{ $entry['type'] === 'IN' ? 'text-success' : 'text-danger' }}">
                                {{ $entry['type'] === 'IN' ? '+' : '-' }}Rp {{ number_format($entry['amount'], 0, ',', '.') }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div data-table-empty hidden><x-ui.table-state state="filtered" /></div>
        </div>
    </div>
</x-layouts.admin>
