<x-layouts.admin title="Daftar Invoice & Verifikasi Bayar" group="Keuangan" role="keuangan">
    <x-ui.page-header
        title="Daftar Invoice dan Verifikasi Pembayaran"
        description="Kelola seluruh invoice satuan maupun gabungan multi-transaksi, verifikasi bukti transfer Client, revisi, dan pembatalan tercatat.">
        <x-slot:actions>
            <a href="{{ route('finance.billable.index') }}" class="btn btn-primary">
                <x-ui.icon name="plus" />
                Buat Invoice dari Antrean
            </a>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="card" data-table data-page-size="10">
        <div class="flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6">
            <div>
                <h2 class="card-title">Semua Invoice ({{ count($invoices) }})</h2>
                <p class="card-desc mt-1">Cari berdasarkan nomor invoice, nama PT Client, atau nomor pesanan.</p>
            </div>
            <div class="flex flex-col gap-2 sm:flex-row">
                <input type="search" class="input sm:w-64" placeholder="Cari nomor invoice, PT, atau pesanan..." aria-label="Cari invoice" data-table-search>
                <select class="select sm:w-52" aria-label="Filter status bayar" data-table-filter>
                    <option value="">Semua Status</option>
                    <option value="ISSUED">Menunggu Pembayaran</option>
                    <option value="PENDING_VERIFICATION">Menunggu Verifikasi</option>
                    <option value="PARTIALLY_PAID">Bayar Sebagian</option>
                    <option value="PAID">Lunas</option>
                    <option value="DRAFT">Draft</option>
                    <option value="VOIDED">Dibatalkan (Void)</option>
                </select>
            </div>
        </div>
        <div class="overflow-x-auto border-t">
            <table class="table min-w-[760px]">
                <thead>
                    <tr>
                        <th>No. Invoice</th>
                        <th>Perusahaan Client</th>
                        <th>Transaksi Tercakup</th>
                        <th class="text-right">Total</th>
                        <th class="text-right">Terbayar</th>
                        <th>Status</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($invoices as $inv)
                        @php
                            $hasPending = (bool) ($inv['has_pending_verification'] ?? false);
                            $filterStatus = $hasPending && !in_array($inv['status'], ['PAID', 'VOIDED'], true)
                                ? 'PENDING_VERIFICATION'
                                : $inv['status'];
                            [$invVariant, $invLabel] = match (true) {
                                $inv['status'] === 'PAID' => ['success', 'Lunas'],
                                $inv['status'] === 'VOIDED' => ['danger', 'Dibatalkan'],
                                $inv['status'] === 'DRAFT' => ['neutral', 'Draft'],
                                $hasPending => ['info', 'Menunggu Verifikasi'],
                                $inv['status'] === 'PARTIALLY_PAID' => ['warning', 'Bayar Sebagian'],
                                default => ['warning', 'Menunggu Pembayaran'],
                            };
                        @endphp
                        <tr data-status="{{ $filterStatus }}">
                            <td class="whitespace-nowrap">
                                <a href="{{ route('finance.invoices.show', $inv['invoice_slug']) }}" class="font-semibold tabular-nums text-brand-accent hover:underline">{{ $inv['invoice_number'] }}</a>
                                <p class="text-xs text-muted-foreground tabular-nums">Terbit {{ $inv['issue_date'] }}</p>
                            </td>
                            <td>{{ $inv['client_company'] }}</td>
                            <td>
                                @if ($inv['is_combined'])
                                    <span class="badge badge-soft">Gabungan ({{ $inv['transaction_count'] }} transaksi)</span>
                                @endif
                                <p class="mt-0.5 text-xs text-muted-foreground tabular-nums">{{ implode(', ', $inv['order_numbers']) }}</p>
                            </td>
                            <td class="text-right font-medium tabular-nums whitespace-nowrap">Rp {{ number_format($inv['total'], 0, ',', '.') }}</td>
                            <td class="text-right tabular-nums whitespace-nowrap">Rp {{ number_format($inv['paid'], 0, ',', '.') }}</td>
                            <td><x-ui.badge :variant="$invVariant" :dot="true">{{ $invLabel }}</x-ui.badge></td>
                            <td class="text-right whitespace-nowrap">
                                <a href="{{ route('finance.invoices.show', $inv['invoice_slug']) }}" class="btn btn-outline btn-sm">Detail & Verifikasi</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-10 text-center text-muted-foreground">Belum ada invoice yang diterbitkan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            <div data-table-empty hidden><x-ui.table-state state="filtered" /></div>
        </div>
    </div>
</x-layouts.admin>
