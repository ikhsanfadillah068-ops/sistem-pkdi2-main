@php
    $activeDataset = $dataset ?? [
        'report_type' => request('report_type', 'cashbook'),
        'title' => 'Laporan Buku Kas Masuk & Keluar PKDI',
        'date_basis_label' => 'Berdasarkan Tanggal Transaksi Kas',
        'from' => request('from'),
        'to' => request('to'),
        'category' => request('category'),
        'entry_type' => request('entry_type'),
        'rows' => array_map(fn ($r) => [
            'reference' => $r['entry_number'],
            'date' => $r['date'],
            'type' => $r['type'] === 'IN' ? 'Pemasukan Jasa (IN)' : 'Biaya Operasional (OUT)',
            'description' => $r['source'],
            'amount' => $r['amount'],
        ], $cashbook ?? []),
        'summary' => [
            'total_in' => collect($cashbook ?? [])->where('type', 'IN')->sum('amount'),
            'total_out' => collect($cashbook ?? [])->where('type', 'OUT')->sum('amount'),
            'total_amount' => collect($cashbook ?? [])->sum('amount'),
        ],
    ];
    $exportQuery = array_filter([
        'report_type' => request('report_type', 'cashbook'),
        'from' => request('from'),
        'to' => request('to'),
        'category' => request('category'),
        'entry_type' => request('entry_type'),
        'q' => request('q'),
    ], fn ($v) => $v !== null && $v !== '');
@endphp
<x-layouts.admin title="Laporan & Ekspor" group="Kas & Pelaporan" role="keuangan">
    <x-ui.page-header title="Laporan Keuangan dan Ekspor" />

    <x-ui.card title="Filter Laporan Periode & Jenis Transaksi">
        <form action="{{ route('finance.reports.index') }}" method="GET" class="space-y-5">
            <div class="grid gap-4 lg:grid-cols-12 lg:items-start">
                <div class="lg:col-span-7">
                    <x-ui.date-range id="report-range" fromName="from" toName="to" :from="request('from', '2026-11-01')" :to="request('to', '2026-11-30')" />
                </div>
                <div class="lg:col-span-5">
                    <x-ui.field label="Jenis Laporan" name="report_type" help="Pilih jenis rekapitulasi yang ingin ditampilkan atau diekspor.">
                        <x-ui.select name="report_type" :options="[
                            'cashbook' => 'Buku Kas Masuk & Keluar (Tanggal Transaksi)',
                            'invoices' => 'Rekap Tagihan & Piutang Client (Tanggal Terbit Invoice)',
                            'expenses' => 'Rekap Biaya Operasional per Kategori (Tanggal Pengeluaran)',
                        ]" :value="request('report_type', 'cashbook')" />
                    </x-ui.field>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.field label="Kategori Biaya / Kata Kunci" name="category">
                    <x-ui.input name="category" :value="request('category')" placeholder="Contoh: Gaji Karyawan / Bahan Baku" />
                </x-ui.field>
                <x-ui.field label="Jenis Arus Kas" name="entry_type">
                    <select id="f-entry_type" name="entry_type" class="select">
                        <option value="">Semua Arus (IN & OUT)</option>
                        <option value="IN" @selected(request('entry_type') === 'IN')>Kas Masuk (IN)</option>
                        <option value="OUT" @selected(request('entry_type') === 'OUT')>Kas Keluar (OUT)</option>
                    </select>
                </x-ui.field>
            </div>

            <div class="flex flex-col gap-3 border-t pt-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex flex-wrap items-center gap-2">
                    <button type="submit" class="btn btn-primary">Terapkan Filter</button>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('finance.reports.export', array_merge($exportQuery, ['format' => 'pdf'])) }}" class="btn btn-outline">
                        <x-ui.icon name="printer" />
                        Ekspor PDF
                    </a>
                    <a href="{{ route('finance.reports.export', array_merge($exportQuery, ['format' => 'xlsx'])) }}" class="btn btn-outline">
                        <x-ui.icon name="file-spreadsheet" />
                        Ekspor Excel (.xlsx)
                    </a>
                </div>
            </div>
        </form>
    </x-ui.card>

    <x-ui.card :title="$activeDataset['title']" :description="$activeDataset['date_basis_label'].' · Menampilkan '.count($activeDataset['rows']).' transaksi.'">
        <div class="overflow-x-auto border rounded-lg">
            <table class="table">
                <thead>
                    <tr>
                        <th>No. Referensi</th>
                        <th>Tanggal</th>
                        <th>Jenis / Kategori</th>
                        <th>Keterangan</th>
                        <th class="text-right">Nominal</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($activeDataset['rows'] as $row)
                        <tr>
                            <td class="font-medium tabular-nums">{{ $row['reference'] }}</td>
                            <td class="whitespace-nowrap">{{ $row['date'] }}</td>
                            <td>{{ $row['type'] }}</td>
                            <td>{{ $row['description'] }}</td>
                            <td class="text-right font-medium tabular-nums">Rp {{ number_format($row['amount'], 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-6 text-center text-muted-foreground">Tidak ada transaksi pada rentang filter yang dipilih.</td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" class="text-right font-semibold">Total Nilai Rekapitulasi Filter</td>
                        <td class="text-right font-bold text-brand-accent tabular-nums">Rp {{ number_format($activeDataset['summary']['total_amount'] ?? 0, 0, ',', '.') }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </x-ui.card>
</x-layouts.admin>
