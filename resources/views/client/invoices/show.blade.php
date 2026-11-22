@php
    $inv = $invoice;
    $invoiceSlug = $inv['invoice_slug'];
    [$invVariant, $invLabel] = match (true) {
        $inv['status'] === 'PAID' => ['success', 'Lunas'],
        $inv['status'] === 'VOIDED' => ['danger', 'Dibatalkan'],
        $inv['has_pending_verification'] => ['info', 'Menunggu Verifikasi'],
        $inv['has_rejected_payment'] && $inv['paid'] <= 0 => ['danger', 'Bukti Ditolak'],
        $inv['status'] === 'PARTIALLY_PAID' => ['warning', 'Pembayaran Sebagian'],
        default => ['warning', 'Menunggu Pembayaran'],
    };
@endphp
<x-layouts.admin :title="'Tagihan '.$inv['invoice_number']" group="Portal Client" role="client">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <a href="{{ route('client.invoices.index') }}" class="mb-2 inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground">
                <x-ui.icon name="arrow-left" class="h-4 w-4" />
                Kembali ke daftar tagihan
            </a>
            <div class="flex flex-wrap items-center gap-2.5">
                <h1 class="text-2xl font-semibold tracking-tight tabular-nums">{{ $inv['invoice_number'] }}</h1>
                <x-ui.badge :variant="$invVariant" :dot="true">{{ $invLabel }}</x-ui.badge>
                @if ($inv['is_combined'])
                    <x-ui.badge variant="soft">Invoice Gabungan ({{ $inv['transaction_count'] }} transaksi)</x-ui.badge>
                @endif
            </div>
            <p class="mt-1 text-sm text-muted-foreground">
                Referensi Pesanan: {{ implode(', ', $inv['order_numbers']) }} · Terbit {{ $inv['issue_date'] }} · Jatuh Tempo {{ $inv['due_date'] }}
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('invoices.download-pdf', $invoiceSlug) }}" class="btn btn-outline">
                <x-ui.icon name="printer" />
                Unduh PDF Invoice
            </a>
        </div>
    </div>

    @if ($inv['has_rejected_payment'] && $inv['balance'] > 0 && !empty($inv['latest_rejected_note']))
        <x-ui.alert variant="danger" title="Bukti Pembayaran Sebelumnya Ditolak">
            Alasan dari Keuangan: <strong>{{ $inv['latest_rejected_note'] }}</strong>. Silakan periksa kembali mutasi transfer lalu unggah ulang bukti pembayaran yang benar di panel kanan.
        </x-ui.alert>
    @endif

    <div class="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(19rem,1fr)]">
        <div class="space-y-6">
            <x-ui.card title="Rincian Perhitungan Tagihan" description="Dihitung berdasarkan kuantitas hasil pekerjaan aktual yang dikonfirmasi Kepala Gudang.">
                <div class="overflow-x-auto border rounded-lg">
                    <table class="table min-w-[560px]">
                        <thead>
                            <tr>
                                <th>Uraian Transaksi / Jasa</th>
                                <th class="text-right">Kuantitas</th>
                                <th class="text-right">Tarif Satuan</th>
                                <th class="text-right">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($inv['items'] as $item)
                                <tr>
                                    <td>
                                        <p class="font-medium">{{ $item['description'] }}</p>
                                        @if (!empty($item['transaction_type_label']))
                                            <p class="text-xs text-muted-foreground">{{ $item['transaction_type_label'] }}</p>
                                        @endif
                                    </td>
                                    <td class="text-right tabular-nums whitespace-nowrap">{{ $item['qty'] }} {{ $item['unit'] }}</td>
                                    <td class="text-right tabular-nums whitespace-nowrap">Rp {{ number_format($item['unit_price'], 0, ',', '.') }}</td>
                                    <td class="text-right font-medium tabular-nums whitespace-nowrap">Rp {{ number_format($item['total'], 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            @if (($inv['adjustment'] ?? 0) > 0)
                                <tr>
                                    <td colspan="3" class="text-right text-muted-foreground">Penyesuaian / Potongan</td>
                                    <td class="text-right tabular-nums">-Rp {{ number_format($inv['adjustment'], 0, ',', '.') }}</td>
                                </tr>
                            @endif
                            @if (($inv['tax'] ?? 0) > 0)
                                <tr>
                                    <td colspan="3" class="text-right text-muted-foreground">Pajak / PPN</td>
                                    <td class="text-right tabular-nums">Rp {{ number_format($inv['tax'], 0, ',', '.') }}</td>
                                </tr>
                            @endif
                            <tr>
                                <td colspan="3" class="text-right font-semibold">Total Tagihan</td>
                                <td class="text-right font-semibold tabular-nums">Rp {{ number_format($inv['total'], 0, ',', '.') }}</td>
                            </tr>
                            <tr>
                                <td colspan="3" class="text-right text-success">Sudah Terverifikasi Lunas</td>
                                <td class="text-right text-success tabular-nums">Rp {{ number_format($inv['paid'], 0, ',', '.') }}</td>
                            </tr>
                            <tr>
                                <td colspan="3" class="text-right font-semibold text-brand-accent">Sisa Tagihan Belum Dibayar</td>
                                <td class="text-right font-semibold text-brand-accent tabular-nums">Rp {{ number_format($inv['balance'], 0, ',', '.') }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </x-ui.card>

            <x-ui.card title="Riwayat Pembayaran" description="Unggahan bukti transfer berstatus Menunggu Verifikasi hingga disahkan oleh divisi Keuangan.">
                <div class="overflow-x-auto border rounded-lg">
                    <table class="table min-w-[540px]">
                        <thead>
                            <tr>
                                <th>No. Referensi</th>
                                <th>Tanggal & Metode</th>
                                <th class="text-right">Nominal</th>
                                <th>Status Verifikasi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($inv['payments'] as $pay)
                                <tr>
                                    <td class="font-medium tabular-nums">
                                        {{ $pay['number'] }}
                                        <p class="text-xs font-normal text-muted-foreground">{{ $pay['reference'] }}</p>
                                        @if (!empty($pay['has_file']))
                                            <a href="{{ route('payments.proof.show', $pay['id']) }}" target="_blank" rel="noopener" class="text-xs font-medium text-primary hover:underline">Lihat berkas bukti</a>
                                        @endif
                                    </td>
                                    <td>
                                        <p>{{ $pay['date'] }}</p>
                                        <p class="text-xs text-muted-foreground">{{ $pay['method'] }}</p>
                                    </td>
                                    <td class="text-right font-medium tabular-nums whitespace-nowrap">Rp {{ number_format($pay['amount'], 0, ',', '.') }}</td>
                                    <td>
                                        <x-ui.badge :variant="$pay['status'] === 'VERIFIED' ? 'success' : ($pay['status'] === 'REJECTED' ? 'danger' : 'warning')" :dot="true">
                                            {{ $pay['status'] === 'VERIFIED' ? 'Terverifikasi' : ($pay['status'] === 'REJECTED' ? 'Ditolak' : 'Menunggu Verifikasi') }}
                                        </x-ui.badge>
                                        @if ($pay['status'] === 'REJECTED' && !empty($pay['notes']))
                                            <p class="mt-1 text-xs text-danger">{{ $pay['notes'] }}</p>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="py-6 text-center text-muted-foreground">Belum ada bukti pembayaran yang dikirim.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-ui.card>
        </div>

        <div class="space-y-6">
            <x-ui.card title="Instruksi Transfer Bank Resmi">
                <p class="text-sm leading-relaxed text-muted-foreground">{{ $inv['payment_instructions'] }}</p>
            </x-ui.card>

            <x-ui.card title="Kirim Bukti Pembayaran">
                @if ($inv['status'] === 'VOIDED')
                    <x-ui.alert variant="danger" title="Invoice Dibatalkan">
                        Tagihan ini telah dibatalkan oleh divisi Keuangan dan tidak perlu dibayar.
                    </x-ui.alert>
                @elseif ($inv['balance'] <= 0)
                    <x-ui.alert variant="success" title="Tagihan Sudah Lunas">
                        Seluruh tagihan telah terverifikasi lunas oleh Keuangan PKDI.
                    </x-ui.alert>
                @else
                    <form action="{{ route('client.invoices.payments.store', $invoiceSlug) }}" method="POST" enctype="multipart/form-data" class="space-y-4" novalidate data-validate-form data-prevent-double-submit>
                        @csrf
                        <x-ui.field label="Nominal Pembayaran (Rp)" name="amount" :error="$errors->first('amount')" required>
                            <x-ui.input name="amount" type="number" min="1" :value="old('amount', (int) $inv['balance'])" required />
                        </x-ui.field>
                        <x-ui.field label="Tanggal Transfer" name="payment_date" :error="$errors->first('payment_date')" required>
                            <x-ui.input name="payment_date" type="date" :value="old('payment_date', now('Asia/Jakarta')->toDateString())" required />
                        </x-ui.field>
                        <x-ui.field label="Metode / Bank Pengirim" name="payment_method" :error="$errors->first('payment_method')" required>
                            <x-ui.input name="payment_method" :value="old('payment_method')" placeholder="Transfer Bank BCA / Mandiri" required />
                        </x-ui.field>
                        <x-ui.field label="Nomor Referensi Mutasi (Opsional)" name="reference_number" :error="$errors->first('reference_number')">
                            <x-ui.input name="reference_number" :value="old('reference_number')" placeholder="Nomor referensi transfer bank" />
                        </x-ui.field>
                        <x-ui.field label="Unggah Bukti Transfer (Wajib)" name="proof_file" :error="$errors->first('proof_file')" required help="Format PDF, JPG, atau PNG maksimal 5 MB.">
                            <x-ui.file-preview id="client-proof-upload" name="proof_file" label="Pilih foto atau PDF bukti transfer" />
                        </x-ui.field>
                        <x-ui.button type="submit" class="w-full">
                            <x-ui.icon name="upload" />
                            Kirim Bukti Pembayaran
                        </x-ui.button>
                    </form>
                @endif
            </x-ui.card>
        </div>
    </div>
</x-layouts.admin>
