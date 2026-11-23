@php
    $orderSlug = \App\Support\PkdiDemoData::orderSlug($order['order_number']);
    $completedQty = (int) ($order['warehouse_gate_in']['billable_qty'] ?? $order['result']['completed_qty'] ?? $order['requested_qty']);
    $rejectQty = (int) ($order['warehouse_gate_in']['qty_reject'] ?? $order['result']['rejected_qty'] ?? 0);
    $tariffOptions = ['' => 'Input Harga Satuan Manual'];
    $tariffPrices = [];
    foreach ($tariffs as $t) {
        $tariffOptions[$t['code']] = $t['name'].' - Rp '.number_format($t['price'], 0, ',', '.');
        $tariffPrices[$t['code']] = (float) $t['price'];
    }
    $pricing = $contractPricing ?? ['unit_price' => $tariffs[0]['price'] ?? 65000, 'reject_disposal_fee' => 0, 'ppn_rate' => 11.0, 'pph23_rate' => 2.0, 'source' => 'Tarif Standar'];
    $defaultPrice = (float) old('unit_price', $pricing['unit_price']);
    $defaultQty = (int) old('quantity', $completedQty);
    $defaultAdditional = (float) old('additional_fees', $rejectQty * (float) ($pricing['reject_disposal_fee'] ?? 0));
    $defaultAdj = (float) old('adjustment_amount', 0);
    $defaultPpnRate = (float) old('ppn_rate', $pricing['ppn_rate'] ?? 11.0);
    $defaultPph23Rate = (float) old('pph23_rate', $pricing['pph23_rate'] ?? 2.0);
    $initSubtotal = ($defaultQty * $defaultPrice) + $defaultAdditional;
    $initPpn = round($initSubtotal * ($defaultPpnRate / 100), 2);
    $initPph23 = round($initSubtotal * ($defaultPph23Rate / 100), 2);
    $initialTotal = max(0, round($initSubtotal - $defaultAdj + $initPpn - $initPph23, 2));
    $checklist = $order['compliance_checklist'] ?? ['all_valid' => true, 'items' => []];
@endphp
<x-layouts.admin :title="'Hitung Tagihan '.$order['order_number']" group="Keuangan" role="keuangan">
    <div>
        <a href="{{ route('finance.billable.index') }}" class="mb-2 inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground">
            <x-ui.icon name="arrow-left" class="h-4 w-4" />
            Kembali ke Antrean Hitung
        </a>
        <div class="flex flex-wrap items-center gap-2.5">
            <h1 class="text-2xl font-semibold tracking-tight">Perhitungan Biaya Jasa, Pajak (PPN & PPh 23) dan Invoice</h1>
            <x-ui.badge variant="info">{{ $pricing['source'] }}</x-ui.badge>
        </div>
        <p class="mt-1 text-sm text-muted-foreground">Pesanan {{ $order['order_number'] }} · {{ $order['client_company'] }}</p>
    </div>

    @if (!empty($checklist['items']))
        <div class="card p-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Status Validasi Dokumen & Stok Gudang (Gatekeeping PRD v2.1)</p>
                <x-ui.badge :variant="!empty($checklist['all_valid']) ? 'success' : 'danger'" :dot="true">
                    {{ !empty($checklist['all_valid']) ? 'Siap Terbit Invoice' : 'Tertahan Dokumen / BA Selisih' }}
                </x-ui.badge>
            </div>
            <div class="mt-2.5 flex flex-wrap gap-2">
                @foreach ($checklist['items'] as $ci)
                    <span class="badge {{ $ci['valid'] ? 'badge-success' : 'badge-warning' }}">
                        {{ $ci['label'] }}: {{ $ci['valid'] ? 'Valid' : 'Belum Lengkap' }}
                    </span>
                @endforeach
            </div>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="space-y-6">
            <x-ui.detail-list title="Informasi Administrasi & Rincian Pesanan" description="Data pesanan dan alamat perusahaan Client untuk kelengkapan penagihan dan verifikasi pembayaran." :items="[
                ['label' => 'Kode Pesanan', 'value' => $order['order_number']],
                ['label' => 'Tanggal & Jam Pemesanan', 'value' => $order['submitted_at'] ?: '-'],
                ['label' => 'Perusahaan Pemohon (PT / CV)', 'value' => $order['origin_company'] ?: $order['client_company']],
                ['label' => 'Alamat Perusahaan', 'value' => $order['company_address'] ?? '-'],
                ['label' => 'Rincian Kemasan & Paket Layanan', 'value' => $order['product_name'].' · '.$order['service_type'].' ('.$order['requested_qty'].' '.$order['unit'].')'],
                ['label' => 'Tanggal Penyerahan / Terima', 'value' => $order['needed_date'] ?: '-'],
                ['label' => 'Jenis Pembayaran', 'value' => $order['payment_method_display'] ?? ($order['payment_method'] ?: 'Belum dipilih')],
                ['label' => 'Jenis Limbah / Bekas Isi Kemasan', 'value' => $order['cargo_type'] ?: '-'],
                ['label' => 'Catatan Tambahan, Warna & Penanganan', 'value' => $order['specification_notes'] ?: ($order['client_notes'] ?: '-')],
            ]" />

            <x-ui.detail-list title="Hasil Validasi Fisik Kepala Gudang (Single Source of Truth)" description="Dasar resmi perhitungan billable_qty = qty_good + qty_dent." :items="[
                ['label' => 'Jenis Jasa Aktual', 'value' => $order['result']['actual_service_type'] ?? $order['service_type']],
                ['label' => 'Kuantitas Layak Tagih (Good + Dent)', 'value' => $completedQty.' '.$order['unit']],
                ['label' => 'Tidak Layak / Reject', 'value' => $rejectQty.' '.$order['unit']],
                ['label' => 'Catatan Kepala Gudang', 'value' => $order['result']['notes'] ?? ($order['warehouse_gate_in']['notes'] ?? '-')],
            ]" />

            {{-- FASE 4 (5.1): PENAGIHAN PARSIAL (SPLIT BILLING) --}}
            <x-ui.card title="Penagihan Parsial (Split Billing)" description="Pecah pesanan menjadi sub-tagihan untuk drum yang sudah lolos QC tanpa menunggu sengketa selisih selesai.">
                <form action="{{ route('finance.orders.split-billing.store', $orderSlug) }}" method="POST" class="space-y-3" data-prevent-double-submit>
                    @csrf
                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-ui.field label="Jumlah Drum Ditagih Parsial" name="split_qty" required>
                            <x-ui.input name="split_qty" type="number" min="1" :max="max(1, $completedQty)" :value="max(1, $completedQty)" required />
                        </x-ui.field>
                        <x-ui.field label="Harga Satuan Kontrak (Rp)" name="unit_price" required>
                            <x-ui.input name="unit_price" type="number" min="1" :value="(int) $defaultPrice" required />
                        </x-ui.field>
                    </div>
                    <x-ui.field label="Label Sub-Tagihan Parsial" name="split_label">
                        <x-ui.input name="split_label" :value="'Tagihan Parsial ('.$completedQty.' '.$order['unit'].' Lolos QC)'" />
                    </x-ui.field>
                    <input type="hidden" name="ppn_rate" value="{{ $defaultPpnRate }}">
                    <input type="hidden" name="pph23_rate" value="{{ $defaultPph23Rate }}">
                    <x-ui.button type="submit" variant="outline" class="w-full">
                        <x-ui.icon name="layers" class="h-4 w-4" />
                        Terbitkan Tagihan Parsial (Split Billing)
                    </x-ui.button>
                </form>
            </x-ui.card>
        </div>

        <x-ui.card title="Kalkulator Invoice & Pajak Otomatis (PPN 11% & PPh 23 2%)">
            <form action="{{ route('finance.billable.store', $orderSlug) }}" method="POST" class="space-y-4" novalidate data-validate-form data-prevent-double-submit id="prd-invoice-calc-form" data-tariff-prices="{{ json_encode($tariffPrices) }}">
                @csrf
                <input type="hidden" name="use_prd_tax_calc" value="1">
                <x-ui.field label="Referensi Tarif Jasa (Opsional - Default mengikuti Tarif Kontrak)" name="tariff_id">
                    <x-ui.select name="tariff_id" :options="$tariffOptions" :value="old('tariff_id', '')" />
                </x-ui.field>

                <x-ui.field label="Uraian Jasa pada Invoice" name="description" :error="$errors->first('description')" required>
                    <x-ui.input name="description" :value="old('description', 'Jasa '.($order['result']['actual_service_type'] ?? $order['service_type']).' - '.$order['service_category'])" required />
                </x-ui.field>

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.field label="Kuantitas Layak Tagih (Good + Dent)" name="quantity" :error="$errors->first('quantity')" required>
                        <x-ui.input id="calc_qty" name="quantity" type="number" min="1" :value="$defaultQty" required />
                    </x-ui.field>
                    <x-ui.field label="Harga Satuan Kontrak (Rp)" name="unit_price" :error="$errors->first('unit_price')" required>
                        <x-ui.input id="calc_price" name="unit_price" type="number" min="1" :value="(int) $defaultPrice" required />
                    </x-ui.field>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.field label="Biaya Tambahan / Pemusnahan Reject (Rp)" name="additional_fees" :error="$errors->first('additional_fees')">
                        <x-ui.input id="calc_add" name="additional_fees" type="number" min="0" :value="(int) $defaultAdditional" />
                    </x-ui.field>
                    <x-ui.field label="Potongan / Penyesuaian (Rp)" name="adjustment_amount" :error="$errors->first('adjustment_amount')">
                        <x-ui.input id="calc_adj" name="adjustment_amount" type="number" min="0" :value="(int) $defaultAdj" />
                    </x-ui.field>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.field label="Tarif PPN (%)" name="ppn_rate" :error="$errors->first('ppn_rate')">
                        <x-ui.input id="calc_ppn_rate" name="ppn_rate" type="number" step="0.01" min="0" max="100" :value="$defaultPpnRate" />
                    </x-ui.field>
                    <x-ui.field label="Estimasi Potongan PPh 23 (%)" name="pph23_rate" :error="$errors->first('pph23_rate')">
                        <x-ui.input id="calc_pph23_rate" name="pph23_rate" type="number" step="0.01" min="0" max="100" :value="$defaultPph23Rate" />
                    </x-ui.field>
                </div>

                <div class="rounded-xl border bg-muted/40 p-4 text-sm space-y-2">
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-muted-foreground">Subtotal (DPP)</span>
                        <span class="font-medium tabular-nums" id="disp_subtotal">Rp {{ number_format($initSubtotal, 0, ',', '.') }}</span>
                    </div>
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-muted-foreground">+ PPN (<span id="disp_ppn_pct">{{ $defaultPpnRate }}</span>%)</span>
                        <span class="font-medium tabular-nums text-foreground" id="disp_ppn">Rp {{ number_format($initPpn, 0, ',', '.') }}</span>
                    </div>
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-muted-foreground">- Potongan PPh Pasal 23 (<span id="disp_pph23_pct">{{ $defaultPph23Rate }}</span>%)</span>
                        <span class="font-medium tabular-nums text-warning" id="disp_pph23">-Rp {{ number_format($initPph23, 0, ',', '.') }}</span>
                    </div>
                    <div class="flex items-center justify-between border-t pt-2.5">
                        <span class="font-semibold">Net Receivable (Total Transfer Bersih)</span>
                        <span class="text-lg font-bold text-brand-accent tabular-nums" id="disp_net" data-invoice-total>Rp {{ number_format($initialTotal, 0, ',', '.') }}</span>
                    </div>
                </div>

                <div class="grid gap-3 pt-2 sm:grid-cols-2">
                    <button type="submit" name="invoice_action" value="DRAFT" class="btn btn-outline w-full">
                        Simpan Sebagai Draft
                    </button>
                    <x-ui.button type="submit" name="invoice_action" value="ISSUE" class="w-full">
                        <x-ui.icon name="check-circle" />
                        Terbitkan Tagihan ke Client
                    </x-ui.button>
                </div>
            </form>
        </x-ui.card>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var form = document.getElementById('prd-invoice-calc-form');
            if (!form) return;
            var prices = {};
            try { prices = JSON.parse(form.getAttribute('data-tariff-prices') || '{}'); } catch (e) {}
            var tariffSel = form.querySelector('[name="tariff_id"]');
            var qtyEl = document.getElementById('calc_qty');
            var priceEl = document.getElementById('calc_price');
            var addEl = document.getElementById('calc_add');
            var adjEl = document.getElementById('calc_adj');
            var ppnRateEl = document.getElementById('calc_ppn_rate');
            var pphRateEl = document.getElementById('calc_pph23_rate');

            var fmt = function (n) { return 'Rp ' + Math.round(n).toLocaleString('id-ID'); };
            var recalc = function () {
                var q = parseFloat(qtyEl && qtyEl.value ? qtyEl.value : '0') || 0;
                var p = parseFloat(priceEl && priceEl.value ? priceEl.value : '0') || 0;
                var add = parseFloat(addEl && addEl.value ? addEl.value : '0') || 0;
                var adj = parseFloat(adjEl && adjEl.value ? adjEl.value : '0') || 0;
                var ppnR = parseFloat(ppnRateEl && ppnRateEl.value ? ppnRateEl.value : '0') || 0;
                var pphR = parseFloat(pphRateEl && pphRateEl.value ? pphRateEl.value : '0') || 0;
                var sub = (q * p) + add;
                var ppn = sub * (ppnR / 100);
                var pph = sub * (pphR / 100);
                var net = Math.max(0, sub - adj + ppn - pph);

                var sEl = document.getElementById('disp_subtotal');
                var ppEl = document.getElementById('disp_ppn');
                var phEl = document.getElementById('disp_pph23');
                var nEl = document.getElementById('disp_net');
                var ppPct = document.getElementById('disp_ppn_pct');
                var phPct = document.getElementById('disp_pph23_pct');

                if (sEl) sEl.textContent = fmt(sub);
                if (ppEl) ppEl.textContent = fmt(ppn);
                if (phEl) phEl.textContent = '-' + fmt(pph);
                if (nEl) nEl.textContent = fmt(net);
                if (ppPct) ppPct.textContent = String(ppnR);
                if (phPct) phPct.textContent = String(pphR);
            };

            if (tariffSel) {
                tariffSel.addEventListener('change', function () {
                    if (tariffSel.value && prices[tariffSel.value] && priceEl) {
                        priceEl.value = Math.round(prices[tariffSel.value]);
                        recalc();
                    }
                });
            }
            [qtyEl, priceEl, addEl, adjEl, ppnRateEl, pphRateEl].forEach(function (el) {
                if (el) el.addEventListener('input', recalc);
            });
            recalc();
        });
    </script>
</x-layouts.admin>
