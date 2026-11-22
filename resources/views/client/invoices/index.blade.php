@php
    $companyName = auth()->user()?->companyDisplay() ?? 'Perusahaan Anda';
@endphp
<x-layouts.admin title="Tagihan & Pembayaran" group="Portal Client" role="client">
    <x-ui.page-header title="Tagihan dan Status Pembayaran" :description="'Daftar invoice resmi yang diterbitkan Keuangan PKDI untuk '.$companyName.'.'" />

    <div class="card" data-table data-page-size="8">
        <div class="flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6">
            <div>
                <h2 class="card-title">Daftar Tagihan Perusahaan</h2>
                <p class="card-desc mt-1">Unduh invoice PDF, lakukan transfer bank manual, lalu unggah bukti transfer agar diverifikasi Keuangan.</p>
            </div>
            <input type="search" class="input sm:w-64" placeholder="Cari nomor invoice atau pesanan..." aria-label="Cari tagihan" data-table-search>
        </div>

        <div class="overflow-x-auto border-t">
            <table class="table min-w-[700px]">
                <thead>
                    <tr>
                        <th>No. Invoice</th>
                        <th>Pesanan Tercakup</th>
                        <th>Tanggal Terbit</th>
                        <th class="text-right">Total Tagihan</th>
                        <th class="text-right">Sisa Tagihan</th>
                        <th>Status Bayar</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($invoices as $inv)
                        @php
                            [$invVariant, $invLabel] = match (true) {
                                $inv['status'] === 'PAID' => ['success', 'Lunas'],
                                $inv['status'] === 'VOIDED' => ['danger', 'Dibatalkan'],
                                $inv['has_pending_verification'] => ['info', 'Menunggu Verifikasi'],
                                $inv['has_rejected_payment'] && $inv['paid'] <= 0 => ['danger', 'Bukti Ditolak'],
                                $inv['status'] === 'PARTIALLY_PAID' => ['warning', 'Pembayaran Sebagian'],
                                default => ['warning', 'Menunggu Pembayaran'],
                            };
                        @endphp
                        <tr>
                            <td class="whitespace-nowrap">
                                <a href="{{ route('client.invoices.show', $inv['invoice_slug']) }}" class="font-semibold tabular-nums text-brand-accent hover:underline">{{ $inv['invoice_number'] }}</a>
                                @if ($inv['is_combined'])
                                    <span class="ml-1 badge badge-soft">{{ $inv['transaction_count'] }} transaksi</span>
                                @endif
                            </td>
                            <td class="tabular-nums text-xs">{{ implode(', ', $inv['order_numbers']) }}</td>
                            <td class="text-muted-foreground tabular-nums whitespace-nowrap">{{ $inv['issue_date'] }}</td>
                            <td class="text-right font-medium tabular-nums whitespace-nowrap">Rp {{ number_format($inv['total'], 0, ',', '.') }}</td>
                            <td class="text-right font-medium tabular-nums whitespace-nowrap">Rp {{ number_format($inv['balance'], 0, ',', '.') }}</td>
                            <td>
                                <x-ui.badge :variant="$invVariant" :dot="true">{{ $invLabel }}</x-ui.badge>
                            </td>
                            <td class="text-right whitespace-nowrap">
                                <a href="{{ route('client.invoices.show', $inv['invoice_slug']) }}" class="btn btn-outline btn-sm">Detail & Bayar</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-10 text-center text-muted-foreground">Belum ada invoice yang diterbitkan untuk perusahaan Anda.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            <div data-table-empty hidden><x-ui.table-state state="filtered" /></div>
        </div>
        <div class="flex flex-col items-center justify-between gap-3 border-t px-5 py-3 sm:flex-row sm:px-6">
            <p data-table-info class="text-sm text-muted-foreground"></p>
            <div data-table-pager class="flex items-center gap-1"></div>
        </div>
    </div>
</x-layouts.admin>
