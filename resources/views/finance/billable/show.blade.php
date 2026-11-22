@php
    $orderSlug = \App\Support\PkdiDemoData::orderSlug($order['order_number']);
    $completedQty = (int) ($order['result']['completed_qty'] ?? $order['requested_qty']);
    $tariffOptions = ['' => 'Input Harga Satuan Manual'];
    $tariffPrices = [];
    foreach ($tariffs as $t) {
        $tariffOptions[$t['code']] = $t['name'].' - Rp '.number_format($t['price'], 0, ',', '.');
        $tariffPrices[$t['code']] = (float) $t['price'];
    }
    $defaultPrice = (float) old('unit_price', $tariffs[0]['price'] ?? 65000);
    $defaultQty = (int) old('quantity', $completedQty);
    $defaultAdj = (float) old('adjustment_amount', 0);
    $defaultTax = (float) old('tax_amount', 0);
    $initialTotal = max(0, ($defaultQty * $defaultPrice) - $defaultAdj + $defaultTax);
@endphp
<x-layouts.admin :title="'Hitung Tagihan '.$order['order_number']" group="Keuangan" role="keuangan">
    <div>
        <a href="{{ route('finance.billable.index') }}" class="mb-2 inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground">
            <x-ui.icon name="arrow-left" class="h-4 w-4" />
            Kembali ke Antrean Hitung
        </a>
        <h1 class="text-2xl font-semibold tracking-tight">Perhitungan Biaya Jasa dan Penerbitan Invoice</h1>
        <p class="mt-1 text-sm text-muted-foreground">Pesanan {{ $order['order_number'] }} · {{ $order['client_company'] }}</p>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <x-ui.detail-list title="Hasil Pekerjaan Aktual Kepala Gudang" description="Menjadi dasar resmi perhitungan kuantitas yang ditagihkan." :items="[
            ['label' => 'Perusahaan Client', 'value' => $order['client_company']],
            ['label' => 'Jenis Jasa Aktual', 'value' => $order['result']['actual_service_type'] ?? $order['service_type']],
            ['label' => 'Kuantitas Selesai (Ditagihkan)', 'value' => $completedQty.' '.$order['unit']],
            ['label' => 'Tidak Layak / Tidak Selesai', 'value' => ($order['result']['rejected_qty'] ?? 0).' '.$order['unit']],
            ['label' => 'Permintaan Awal Client', 'value' => $order['requested_qty'].' '.$order['unit'].' ('.$order['service_category'].')'],
            ['label' => 'Catatan Kepala Gudang', 'value' => $order['result']['notes'] ?? '-'],
        ]" />

        <x-ui.card title="Rincian & Penerbitan Invoice ke Client">
            <form action="{{ route('finance.billable.store', $orderSlug) }}" method="POST" class="space-y-4" novalidate data-validate-form data-prevent-double-submit data-invoice-calc data-tariff-prices="{{ json_encode($tariffPrices) }}">
                @csrf
                <x-ui.field label="Referensi Tarif Jasa (Opsional)" name="tariff_id">
                    <x-ui.select name="tariff_id" :options="$tariffOptions" :value="old('tariff_id', $tariffs[0]['code'] ?? '')" />
                </x-ui.field>

                <x-ui.field label="Uraian Jasa pada Invoice" name="description" :error="$errors->first('description')" required>
                    <x-ui.input name="description" :value="old('description', 'Jasa '.($order['result']['actual_service_type'] ?? $order['service_type']).' - '.$order['service_category'])" required />
                </x-ui.field>

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.field label="Kuantitas Ditagihkan" name="quantity" :error="$errors->first('quantity')" required>
                        <x-ui.input name="quantity" type="number" min="1" :value="$defaultQty" required />
                    </x-ui.field>
                    <x-ui.field label="Harga Satuan (Rp)" name="unit_price" :error="$errors->first('unit_price')" required>
                        <x-ui.input name="unit_price" type="number" min="1" :value="$defaultPrice" required />
                    </x-ui.field>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.field label="Penyesuaian / Potongan (Rp)" name="adjustment_amount" :error="$errors->first('adjustment_amount')">
                        <x-ui.input name="adjustment_amount" type="number" min="0" :value="$defaultAdj" />
                    </x-ui.field>
                    <x-ui.field label="Pajak / PPN (Rp)" name="tax_amount" :error="$errors->first('tax_amount')">
                        <x-ui.input name="tax_amount" type="number" min="0" :value="$defaultTax" />
                    </x-ui.field>
                </div>

                <div class="rounded-lg border bg-muted/40 p-4 text-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-muted-foreground">Estimasi Total Tagihan</span>
                        <span class="text-lg font-bold text-brand-accent tabular-nums" data-invoice-total>Rp {{ number_format($initialTotal, 0, ',', '.') }}</span>
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
</x-layouts.admin>
