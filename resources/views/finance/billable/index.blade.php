@php
    $readyTransactions = array_values(array_filter($transactions, static fn (array $t): bool => $t['billing_status'] === 'READY_TO_BILL' && empty($t['invoice_id'])));
@endphp
<x-layouts.admin title="Antrean Hitung Tagihan" group="Keuangan" role="keuangan">
    <x-ui.page-header
        title="Antrean Transaksi Siap Ditagihkan & Invoice Gabungan"
        description="Filter transaksi yang selesai dari Gudang berdasarkan Client, periode, dan jenis transaksi. Centang satu atau beberapa transaksi milik Client yang sama untuk diterbitkan menjadi satu invoice." />

    <div class="card p-5 sm:p-6">
        <form method="GET" action="{{ route('finance.billable.index') }}" class="grid gap-3 md:grid-cols-12 md:items-end">
            <div class="md:col-span-3">
                <label for="fin-bill-client" class="label mb-1.5">Perusahaan Client</label>
                <select id="fin-bill-client" name="client_company_id" class="select">
                    <option value="">Semua Perusahaan Client</option>
                    @foreach ($clients as $client)
                        <option value="{{ $client->id }}" @selected((string) ($filters['client_company_id'] ?? '') === (string) $client->id)>{{ $client->company_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="md:col-span-2">
                <label for="fin-bill-type" class="label mb-1.5">Jenis Transaksi</label>
                <select id="fin-bill-type" name="transaction_type" class="select">
                    <option value="">Semua Jenis</option>
                    <option value="RECONDITIONING" @selected(($filters['transaction_type'] ?? '') === 'RECONDITIONING')>Rekondisi Drum</option>
                    <option value="WASTE_TRANSPORT" @selected(in_array($filters['transaction_type'] ?? '', ['WASTE_TRANSPORT', 'DRUM_SALE'], true))>Pengangkutan Limbah</option>
                </select>
            </div>
            <div class="md:col-span-2">
                <label for="fin-bill-status" class="label mb-1.5">Status Tagih</label>
                <select id="fin-bill-status" name="billing_status" class="select">
                    <option value="READY_TO_BILL" @selected(($filters['billing_status'] ?? 'READY_TO_BILL') === 'READY_TO_BILL')>Belum Ditagihkan</option>
                    <option value="INVOICED" @selected(($filters['billing_status'] ?? '') === 'INVOICED')>Sudah Ditagihkan</option>
                    <option value="ALL" @selected(($filters['billing_status'] ?? '') === 'ALL')>Semua Status</option>
                </select>
            </div>
            <div class="md:col-span-2">
                <label for="fin-bill-from" class="label mb-1.5">Dari Tanggal</label>
                <input id="fin-bill-from" type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="input">
            </div>
            <div class="md:col-span-2">
                <label for="fin-bill-to" class="label mb-1.5">Sampai Tanggal</label>
                <input id="fin-bill-to" type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="input">
            </div>
            <div class="md:col-span-1">
                <button type="submit" class="btn btn-primary w-full">Filter</button>
            </div>
        </form>
    </div>

    @if ($errors->any())
        <x-ui.alert variant="danger" title="Periksa Data Penagihan">
            <ul class="list-disc pl-5 text-sm">
                @foreach ($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </x-ui.alert>
    @endif

    <form action="{{ route('finance.billable.combined.store') }}" method="POST" class="space-y-6" data-combined-billing-form data-prevent-double-submit>
        @csrf
        <div class="card">
            <div class="flex flex-wrap items-center justify-between gap-3 p-5 sm:p-6">
                <div>
                    <h2 class="card-title">Daftar Transaksi Operasional</h2>
                    <p class="card-desc mt-1">Transaksi yang sudah masuk invoice terkunci otomatis dan tidak dapat dipilih ulang.</p>
                </div>
                <span class="badge badge-info tabular-nums" data-combined-count>0 transaksi dipilih</span>
            </div>

            <div class="mx-5 mb-4 sm:mx-6" data-combined-client-warning hidden>
                <x-ui.alert variant="warning" title="Transaksi Lintas Client Terdeteksi">
                    Satu invoice gabungan hanya boleh menggabungkan transaksi milik perusahaan Client yang sama. Silakan sesuaikan pilihan centang atau filter berdasarkan Client terlebih dahulu.
                </x-ui.alert>
            </div>

            <div class="overflow-x-auto border-t">
                <table class="table min-w-[920px]">
                    <thead>
                        <tr>
                            <th class="w-10 text-center">Pilih</th>
                            <th>Kode & Pesanan</th>
                            <th>Perusahaan Client</th>
                            <th>Produk & Layanan</th>
                            <th class="text-right">Kuantitas Layak</th>
                            <th>Referensi Tarif</th>
                            <th class="w-40 text-right">Harga Satuan (Rp)</th>
                            <th class="text-right">Subtotal</th>
                            <th class="text-right">Status / Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($transactions as $tx)
                            @php($canSelect = $tx['billing_status'] === 'READY_TO_BILL' && empty($tx['invoice_id']))
                            <tr>
                                <td class="text-center">
                                    @if ($canSelect)
                                        <input
                                            type="checkbox"
                                            name="billable_transaction_ids[]"
                                            value="{{ $tx['id'] }}"
                                            class="check"
                                            aria-label="Pilih transaksi {{ $tx['transaction_code'] }}"
                                            data-billable-check
                                            data-client-id="{{ $tx['client_company_id'] }}"
                                            data-client-name="{{ $tx['client_company'] }}"
                                            data-qty="{{ $tx['billable_quantity'] }}"
                                            @checked(in_array($tx['id'], (array) old('billable_transaction_ids', []), false))
                                        >
                                    @else
                                        <span class="text-xs text-muted-foreground" title="Sudah ditagihkan">-</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap">
                                    <p class="font-semibold tabular-nums">{{ $tx['transaction_code'] }}</p>
                                    <p class="text-xs text-muted-foreground tabular-nums">{{ $tx['order_number'] }} · {{ $tx['ready_at'] }}</p>
                                </td>
                                <td>{{ $tx['client_company'] }}</td>
                                <td>
                                    <p class="font-medium">{{ $tx['product_name'] }} · {{ $tx['service_name'] }}</p>
                                    <p class="text-xs text-muted-foreground">{{ $tx['transaction_type_label'] }}</p>
                                </td>
                                <td class="text-right tabular-nums whitespace-nowrap">
                                    <span class="font-semibold">{{ $tx['billable_quantity'] }} {{ $tx['unit'] }} selesai</span>
                                    @if ($tx['rejected_quantity'] > 0)
                                        <p class="text-xs text-muted-foreground">{{ $tx['rejected_quantity'] }} tidak layak</p>
                                    @endif
                                </td>
                                <td>
                                    @if ($canSelect)
                                        <select name="tariff_ids[{{ $tx['id'] }}]" class="select text-xs" aria-label="Tarif untuk {{ $tx['transaction_code'] }}" data-billable-tariff>
                                            <option value="">Tarif manual...</option>
                                            @foreach ($tariffs as $t)
                                                <option value="{{ $t['id'] }}" data-price="{{ (int) $t['price'] }}" @selected((string) old('tariff_ids.'.$tx['id'], $tx['suggested_tariff_id']) === (string) $t['id'])>
                                                    {{ $t['name'] }} (Rp {{ number_format($t['price'], 0, ',', '.') }})
                                                </option>
                                            @endforeach
                                        </select>
                                    @else
                                        <span class="text-xs text-muted-foreground">Snapshot terkunci</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($canSelect)
                                        <input
                                            type="number"
                                            min="0"
                                            name="unit_prices[{{ $tx['id'] }}]"
                                            value="{{ old('unit_prices.'.$tx['id'], $tx['suggested_unit_price'] ? (int) $tx['suggested_unit_price'] : '') }}"
                                            class="input text-right tabular-nums"
                                            placeholder="Harga satuan"
                                            aria-label="Harga satuan transaksi {{ $tx['transaction_code'] }}"
                                            data-billable-price
                                        >
                                    @else
                                        <span class="block text-right text-xs text-muted-foreground">-</span>
                                    @endif
                                </td>
                                <td class="text-right font-medium tabular-nums whitespace-nowrap" data-billable-line-total>
                                    Rp {{ number_format(($tx['suggested_unit_price'] ?? 0) * $tx['billable_quantity'], 0, ',', '.') }}
                                </td>
                                <td class="text-right whitespace-nowrap">
                                    @if ($canSelect)
                                        <a href="{{ route('finance.billable.show', $tx['order_slug']) }}" class="btn btn-outline btn-sm">Hitung Satuan</a>
                                    @elseif (!empty($tx['invoice_slug']))
                                        <a href="{{ route('finance.invoices.show', $tx['invoice_slug']) }}" class="badge badge-soft hover:underline">{{ $tx['invoice_number'] }}</a>
                                    @else
                                        <x-ui.badge :variant="$tx['billing_status_meta']['tone']">{{ $tx['billing_status_meta']['label'] }}</x-ui.badge>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="py-12 text-center text-muted-foreground">
                                    Belum ada transaksi yang sesuai dengan filter antrean penagihan saat ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if (count($readyTransactions) > 0)
            <x-ui.card title="Ringkasan & Penerbitan Invoice Gabungan" description="Centang satu atau beberapa transaksi milik Client yang sama pada tabel di atas, lalu atur tanggal dan terbitkan invoice.">
                <div class="grid gap-4 md:grid-cols-4">
                    <x-ui.field label="Tanggal Terbit" name="issue_date">
                        <x-ui.input name="issue_date" type="date" :value="old('issue_date', now('Asia/Jakarta')->toDateString())" />
                    </x-ui.field>
                    <x-ui.field label="Jatuh Tempo" name="due_date">
                        <x-ui.input name="due_date" type="date" :value="old('due_date', now('Asia/Jakarta')->addDays(7)->toDateString())" />
                    </x-ui.field>
                    <x-ui.field label="Potongan / Penyesuaian (Rp)" name="adjustment_amount">
                        <x-ui.input name="adjustment_amount" type="number" min="0" :value="old('adjustment_amount', 0)" />
                    </x-ui.field>
                    <x-ui.field label="Pajak / PPN (Rp)" name="tax_amount">
                        <x-ui.input name="tax_amount" type="number" min="0" :value="old('tax_amount', 0)" />
                    </x-ui.field>
                </div>

                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <x-ui.field label="Instruksi Rekening Transfer PKDI" name="payment_instructions">
                        <x-ui.textarea name="payment_instructions" rows="2">{{ old('payment_instructions', 'Transfer ke Rekening Resmi PT Pulo Kencana Drum Industri (BCA / Mandiri) dengan mencantumkan nomor invoice.') }}</x-ui.textarea>
                    </x-ui.field>
                    <x-ui.field label="Catatan Invoice (Opsional)" name="notes">
                        <x-ui.textarea name="notes" rows="2" placeholder="Catatan tambahan untuk Client atau rincian penagihan">{{ old('notes') }}</x-ui.textarea>
                    </x-ui.field>
                </div>

                <div class="mt-5 flex flex-col gap-4 border-t pt-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="space-y-0.5 text-sm">
                        <p class="text-muted-foreground">Subtotal terpilih: <strong class="tabular-nums text-foreground" data-combined-subtotal>Rp 0</strong></p>
                        <p class="text-base font-semibold">Estimasi Total Invoice: <span class="tabular-nums text-brand-accent" data-combined-total>Rp 0</span></p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="submit" name="invoice_action" value="DRAFT" class="btn btn-outline" data-combined-submit disabled>
                            Simpan Draft Invoice
                        </button>
                        <button type="submit" name="invoice_action" value="ISSUE" class="btn btn-primary" data-combined-submit disabled>
                            <x-ui.icon name="receipt" />
                            Terbitkan Invoice Terpilih
                        </button>
                    </div>
                </div>
            </x-ui.card>
        @endif
    </form>

    @if (count($legacyReadyOrders) > 0)
        <div class="card">
            <div class="p-5 sm:p-6">
                <h2 class="card-title">Pesanan Selesai Warisan (Perhitungan Satuan)</h2>
                <p class="card-desc mt-1">Pesanan lama yang telah selesai dikerjakan sebelum tabel antrean gabungan aktif.</p>
            </div>
            <div class="overflow-x-auto border-t">
                <table class="table min-w-[640px]">
                    <thead>
                        <tr><th>No. Pesanan</th><th>Perusahaan Client</th><th>Hasil Aktual</th><th class="text-right">Aksi</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($legacyReadyOrders as $ord)
                            <tr>
                                <td class="font-semibold tabular-nums whitespace-nowrap">{{ $ord['order_number'] }}</td>
                                <td>{{ $ord['client_company'] }}</td>
                                <td class="tabular-nums">{{ $ord['result']['completed_qty'] }} {{ $ord['unit'] }} ({{ $ord['result']['actual_service_type'] }})</td>
                                <td class="text-right">
                                    <a href="{{ route('finance.billable.show', $ord['order_slug']) }}" class="btn btn-primary btn-sm">Buat Perhitungan Tagihan</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</x-layouts.admin>
