@php
    $inv = $invoice;
    $invoiceSlug = $inv['invoice_slug'];
    $hasPendingVerification = (bool) ($inv['has_pending_verification'] ?? false);
    [$badgeVariant, $badgeLabel] = match (true) {
        $inv['status'] === 'PAID' => ['success', 'Lunas'],
        $inv['status'] === 'VOIDED' => ['danger', 'Dibatalkan (Void)'],
        $inv['status'] === 'DRAFT' => ['neutral', 'Draft'],
        $hasPendingVerification => ['info', 'Menunggu Verifikasi Bayar'],
        $inv['status'] === 'PARTIALLY_PAID' => ['warning', 'Bayar Sebagian'],
        default => ['warning', 'Menunggu Pembayaran Client'],
    };
@endphp
<x-layouts.admin :title="'Invoice '.$inv['invoice_number']" group="Keuangan" role="keuangan">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <a href="{{ route('finance.invoices.index') }}" class="mb-2 inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground">
                <x-ui.icon name="arrow-left" class="h-4 w-4" />
                Kembali ke Daftar Invoice
            </a>
            <div class="flex flex-wrap items-center gap-2.5">
                <h1 class="text-2xl font-semibold tracking-tight tabular-nums">{{ $inv['invoice_number'] }}</h1>
                <x-ui.badge :variant="$badgeVariant" :dot="true">{{ $badgeLabel }}</x-ui.badge>
                @if ($inv['is_combined'])
                    <x-ui.badge variant="soft">Invoice Gabungan ({{ $inv['transaction_count'] }} transaksi)</x-ui.badge>
                @endif
            </div>
            <p class="mt-1 text-sm text-muted-foreground">
                {{ $inv['client_company'] }} · Pesanan: {{ implode(', ', $inv['order_numbers']) }} · Terbit {{ $inv['issue_date'] }} · Jatuh Tempo {{ $inv['due_date'] }}
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('invoices.download-pdf', $invoiceSlug) }}" class="btn btn-outline btn-sm">
                <x-ui.icon name="printer" />
                Unduh PDF Invoice
            </a>
            @if (!in_array($inv['status'], ['PAID', 'VOIDED'], true))
                <button type="button" class="btn btn-outline btn-sm" data-modal-open="#modal-revise-invoice">
                    <x-ui.icon name="edit" />
                    Revisi Tagihan
                </button>
            @endif
            @if (!in_array($inv['status'], ['PAID', 'VOIDED'], true) && $inv['paid'] <= 0)
                <button type="button" class="btn btn-destructive btn-sm" data-modal-open="#modal-void-invoice">
                    <x-ui.icon name="ban" />
                    Batalkan (Void)
                </button>
            @endif
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(19rem,1fr)]">
        <div class="space-y-6">
            <x-ui.card title="Rincian Tagihan Invoice" description="Tarif dan kuantitas tersimpan sebagai snapshot permanen saat invoice dibuat.">
                <div class="overflow-x-auto rounded-lg border">
                    <table class="table min-w-[600px]">
                        <thead>
                            <tr>
                                <th>Uraian Transaksi / Jasa</th>
                                <th class="text-right">Kuantitas</th>
                                <th class="text-right">Harga Satuan</th>
                                <th class="text-right">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($inv['items'] as $item)
                                <tr>
                                    <td>
                                        <p class="font-medium">{{ $item['description'] }}</p>
                                        @if (!empty($item['transaction_code']) || !empty($item['transaction_type_label']))
                                            <p class="text-xs text-muted-foreground">
                                                {{ $item['transaction_code'] ?? '' }}
                                                @if (!empty($item['transaction_code']) && !empty($item['transaction_type_label'])) · @endif
                                                {{ $item['transaction_type_label'] ?? '' }}
                                            </p>
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

            <x-ui.card title="Bukti Pembayaran dari Client">
                <div class="space-y-4">
                    @forelse ($inv['payments'] as $pay)
                        <div class="rounded-xl border p-4 space-y-3">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <p class="font-semibold tabular-nums">{{ $pay['number'] }} · Rp {{ number_format($pay['amount'], 0, ',', '.') }}</p>
                                    <p class="text-xs text-muted-foreground">{{ $pay['date'] }} · {{ $pay['method'] }} · Ref: {{ $pay['reference'] }}</p>
                                    <p class="mt-1 text-xs text-muted-foreground">
                                        Berkas bukti: <strong class="text-foreground">{{ $pay['proof'] }}</strong>
                                        @if (!empty($pay['has_file']))
                                            · <a href="{{ route('payments.proof.show', $pay['id']) }}" target="_blank" rel="noopener" class="font-medium text-primary hover:underline">Lihat Bukti Transfer</a>
                                        @endif
                                    </p>
                                    @if (!empty($pay['notes']) && $pay['status'] === 'REJECTED')
                                        <p class="mt-1.5 text-xs text-danger">Alasan penolakan: {{ $pay['notes'] }}</p>
                                    @endif
                                </div>
                                <x-ui.badge :variant="$pay['status'] === 'VERIFIED' ? 'success' : ($pay['status'] === 'REJECTED' ? 'danger' : 'warning')" :dot="true">
                                    {{ $pay['status'] === 'VERIFIED' ? 'Terverifikasi Sah' : ($pay['status'] === 'REJECTED' ? 'Bukti Ditolak' : 'Menunggu Verifikasi') }}
                                </x-ui.badge>
                            </div>
                            @if ($pay['status'] === 'PENDING_VERIFICATION' && $inv['status'] !== 'VOIDED')
                                <div class="flex flex-wrap items-center gap-2 border-t pt-3">
                                    <form action="{{ route('finance.invoices.payments.store', $invoiceSlug) }}" method="POST" data-prevent-double-submit>
                                        @csrf
                                        <input type="hidden" name="payment_id" value="{{ $pay['id'] }}">
                                        <input type="hidden" name="decision" value="VERIFIED">
                                        <button type="submit" class="btn btn-success btn-sm">
                                            <x-ui.icon name="check" />
                                            Verifikasi Pembayaran Sah
                                        </button>
                                    </form>
                                    <button type="button" class="btn btn-destructive btn-sm" data-modal-open="#modal-reject-pay-{{ $pay['id'] }}">
                                        <x-ui.icon name="ban" />
                                        Tolak Bukti
                                    </button>
                                </div>

                                <x-ui.modal :id="'modal-reject-pay-'.$pay['id']" title="Tolak Bukti Pembayaran" icon="alert-triangle" tone="danger" size="sm">
                                    <form action="{{ route('finance.invoices.payments.store', $invoiceSlug) }}" method="POST" class="space-y-4" novalidate data-validate-form data-prevent-double-submit>
                                        @csrf
                                        <input type="hidden" name="payment_id" value="{{ $pay['id'] }}">
                                        <input type="hidden" name="decision" value="REJECTED">
                                        <x-ui.field label="Alasan Penolakan" name="reject_reason" required>
                                            <x-ui.textarea name="reject_reason" rows="3" minlength="5" placeholder="Jelaskan ketidaksesuaian nominal, mutasi, atau berkas bukti" required />
                                        </x-ui.field>
                                        <div class="flex justify-end gap-2 border-t pt-4">
                                            <x-ui.button variant="outline" data-modal-close>Batal</x-ui.button>
                                            <x-ui.button type="submit" variant="destructive">Tolak Bukti Bayar</x-ui.button>
                                        </div>
                                    </form>
                                </x-ui.modal>
                            @endif
                        </div>
                    @empty
                        <x-ui.empty icon="receipt" title="Belum ada bukti pembayaran dari Client" description="Client belum mengunggah bukti transfer pada Portal Client untuk tagihan ini." />
                    @endforelse
                </div>
            </x-ui.card>
        </div>

        <div class="space-y-6">
            @if ($inv['status'] === 'VOIDED')
                <x-ui.card title="Status Tagihan: Dibatalkan">
                    <x-ui.alert variant="danger" title="Invoice Dibatalkan (Void)">
                        Invoice ini telah dibatalkan. Seluruh transaksi operasional terkait telah dikembalikan ke antrean Siap Ditagihkan.
                    </x-ui.alert>
                </x-ui.card>
            @elseif ($inv['status'] === 'PAID' || $inv['balance'] <= 0)
                <x-ui.card title="Status Pelunasan">
                    <x-ui.alert variant="success" title="Tagihan Telah Lunas">
                        Pembayaran sebesar Rp {{ number_format($inv['paid'], 0, ',', '.') }} telah diverifikasi sah dan tercatat satu kali di Buku Kas Masuk.
                    </x-ui.alert>
                </x-ui.card>
            @elseif ($inv['status'] === 'DRAFT')
                <x-ui.card title="Status Tagihan: Draft">
                    <x-ui.alert variant="warning" title="Belum Diterbitkan ke Client">
                        Invoice ini masih berstatus Draft dan belum terlihat pada Portal Client.
                    </x-ui.alert>
                    <form action="{{ route('finance.invoices.publish', $invoiceSlug) }}" method="POST" class="mt-4" data-prevent-double-submit>
                        @csrf
                        <button type="submit" class="btn btn-primary w-full">
                            <x-ui.icon name="check-circle" />
                            Terbitkan Invoice ke Client
                        </button>
                    </form>
                </x-ui.card>
            @elseif ($hasPendingVerification)
                <x-ui.card title="Tindakan Keuangan">
                    <x-ui.alert variant="info" title="Bukti Pembayaran Menunggu Verifikasi">
                        {{ $inv['client_company'] }} telah mengunggah bukti transfer. Cocokkan mutasi rekening lalu klik <strong>Verifikasi Pembayaran Sah</strong> atau <strong>Tolak Bukti</strong>.
                    </x-ui.alert>
                </x-ui.card>
            @else
                <x-ui.card title="Status Pembayaran Client">
                    <x-ui.alert variant="warning" title="Menunggu Pembayaran dari Client">
                        Tagihan sebesar <strong>Rp {{ number_format($inv['balance'], 0, ',', '.') }}</strong> telah dikirim ke Portal Client ({{ $inv['client_company'] }}). Tombol verifikasi akan muncul setelah Client mengunggah bukti transfer.
                    </x-ui.alert>
                </x-ui.card>
            @endif

            <x-ui.card title="Identitas & Instruksi Transfer">
                <dl class="space-y-3 text-sm">
                    <div>
                        <dt class="text-xs text-muted-foreground">Perusahaan Client</dt>
                        <dd class="mt-0.5 font-medium">{{ $inv['client_company'] }}</dd>
                    </div>
                    @if (!empty($inv['client_npwp']))
                        <div>
                            <dt class="text-xs text-muted-foreground">NPWP Client</dt>
                            <dd class="mt-0.5 tabular-nums">{{ $inv['client_npwp'] }}</dd>
                        </div>
                    @endif
                    <div>
                        <dt class="text-xs text-muted-foreground">Instruksi Transfer Bank</dt>
                        <dd class="mt-0.5 text-muted-foreground leading-relaxed">{{ $inv['payment_instructions'] }}</dd>
                    </div>
                    @if (!empty($inv['notes']))
                        <div class="border-t pt-3">
                            <dt class="text-xs text-muted-foreground">Catatan / Riwayat Revisi</dt>
                            <dd class="mt-0.5 whitespace-pre-line text-xs text-muted-foreground">{{ $inv['notes'] }}</dd>
                        </div>
                    @endif
                </dl>
            </x-ui.card>
        </div>
    </div>

    <x-ui.modal id="modal-revise-invoice" title="Revisi / Koreksi Tagihan" icon="edit" tone="warning" size="sm">
        <form action="{{ route('finance.invoices.revise', $invoiceSlug) }}" method="POST" class="space-y-4" novalidate data-validate-form data-prevent-double-submit>
            @csrf
            @method('PUT')
            <x-ui.field label="Nominal Koreksi Baru (Rp)" name="revised_total" :error="$errors->first('revised_total')" required>
                <x-ui.input name="revised_total" type="number" min="1" :value="old('revised_total', $inv['total'])" required />
            </x-ui.field>
            <x-ui.field label="Alasan Revisi (Wajib)" name="revision_reason" :error="$errors->first('revision_reason')" required>
                <x-ui.textarea name="revision_reason" rows="3" minlength="5" placeholder="Jelaskan dasar penyesuaian atau koreksi invoice" required>{{ old('revision_reason') }}</x-ui.textarea>
            </x-ui.field>
            <div class="flex justify-end gap-2 border-t pt-4">
                <x-ui.button variant="outline" data-modal-close>Batal</x-ui.button>
                <x-ui.button type="submit">Simpan Revisi</x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    <x-ui.modal id="modal-void-invoice" title="Batalkan Invoice (Void)" icon="alert-triangle" tone="danger" size="sm">
        <form action="{{ route('finance.invoices.void', $invoiceSlug) }}" method="POST" class="space-y-4" novalidate data-validate-form data-prevent-double-submit>
            @csrf
            <p class="text-sm text-muted-foreground">Pembatalan akan mengubah status invoice menjadi Dibatalkan (VOIDED) dan mengembalikan transaksi terkait ke antrean Siap Ditagihkan.</p>
            <x-ui.field label="Alasan Pembatalan (Wajib)" name="void_reason" :error="$errors->first('void_reason')" required>
                <x-ui.textarea name="void_reason" rows="3" minlength="5" placeholder="Jelaskan alasan resmi pembatalan invoice" required>{{ old('void_reason') }}</x-ui.textarea>
            </x-ui.field>
            <div class="flex justify-end gap-2 border-t pt-4">
                <x-ui.button variant="outline" data-modal-close>Batal</x-ui.button>
                <x-ui.button type="submit" variant="destructive">Konfirmasi Void Invoice</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
</x-layouts.admin>
