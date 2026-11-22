@php
    $st = \App\Support\PkdiDemoData::statusMeta($order['order_status']);
    $bst = $order['billing_status_meta'];
    $pst = $order['payment_status_meta'];
@endphp
<x-layouts.admin :title="'Telusur '.$order['order_number']" group="Pemantauan PKDI" role="owner">
    <div>
        <a href="{{ route('owner.transactions.index') }}" class="mb-2 inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground">
            <x-ui.icon name="arrow-left" class="h-4 w-4" />
            Kembali ke Transaksi Lintas Bagian
        </a>
        <div class="flex flex-wrap items-center gap-2.5">
            <h1 class="text-2xl font-semibold tracking-tight tabular-nums">{{ $order['order_number'] }}</h1>
            <x-ui.badge :variant="$st['tone']" :dot="true">{{ $st['label'] }}</x-ui.badge>
            <x-ui.badge variant="outline">{{ $order['transaction_type_label'] }}</x-ui.badge>
            <span class="badge badge-outline">Akses Pemantauan Owner (Read-Only)</span>
        </div>
        <p class="mt-1 text-sm text-muted-foreground">{{ $order['client_company'] }} · Diajukan {{ $order['submitted_at'] }}</p>
    </div>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(18rem,1fr)]">
        <div class="space-y-6">
            <x-ui.detail-list title="1. Pengajuan Awal Client" :items="[
                ['label' => 'Perusahaan Client', 'value' => $order['client_company']],
                ['label' => 'Jenis Transaksi', 'value' => $order['transaction_type_label']],
                ['label' => 'Produk & Layanan', 'value' => $order['product_name'].' · '.$order['service_type']],
                ['label' => 'Kuantitas Pengajuan', 'value' => $order['requested_qty'].' '.$order['unit']],
                ['label' => 'Nomor PO & Tgl Kebutuhan', 'value' => ($order['po_number'] ?? '-').' · '.($order['needed_date'] ?? '-')],
                ['label' => 'Spesifikasi / Catatan', 'value' => $order['specification_notes'] ?? ($order['client_notes'] ?? '-')],
            ]" />

            <x-ui.detail-list title="2. Operasional Gudang & Penyerahan" :items="[
                ['label' => 'Penerimaan Fisik Drum', 'value' => !empty($order['receipt']) ? $order['receipt']['receipt_number'].' · '.$order['receipt']['actual_received_quantity'].' '.$order['receipt']['unit'].' ('.$order['receipt']['received_at'].')' : ($order['is_drum_sale'] ? 'Tidak berlaku untuk Penjualan Drum' : 'Belum dicatat')],
                ['label' => 'Hasil Penyortiran', 'value' => !empty($order['sorting']) ? $order['sorting']['qty_passed'].' layak, '.$order['sorting']['qty_special_repair'].' penanganan khusus, '.$order['sorting']['qty_rejected'].' tidak layak' : (!empty($order['inspection']) ? $order['inspection']['actual_quantity'].' '.$order['inspection']['actual_unit'].' ('.$order['inspection']['cargo_condition'].')' : 'Belum dicatat')],
                ['label' => 'Hasil Akhir Gudang', 'value' => !empty($order['result']) ? $order['result']['completed_qty'].' selesai, '.$order['result']['rejected_qty'].' tidak layak ('.$order['result']['unit'].' · '.$order['result']['actual_service_type'].')' : 'Belum selesai'],
                ['label' => 'Penyerahan / Pengiriman', 'value' => !empty($order['delivery_method_label']) ? $order['delivery_method_label'].' · '.($order['delivered_quantity'] ?? '-').' '.$order['unit'].(!empty($order['license_plate']) ? ' ('.$order['license_plate'].')' : '') : 'Belum diserahkan'],
            ]" />

            <x-ui.detail-list title="3. Penagihan & Pembayaran Keuangan" :items="[
                ['label' => 'Kode Antrean Siap Tagih', 'value' => $order['billable_transaction']['transaction_code'] ?? 'Belum masuk antrean'],
                ['label' => 'Nomor Invoice', 'value' => !empty($order['invoice']) ? $order['invoice']['invoice_number'].($order['invoice']['is_combined'] ? ' (Invoice Gabungan)' : '') : 'Belum diterbitkan'],
                ['label' => 'Total & Terbayar', 'value' => !empty($order['invoice']) ? 'Rp '.number_format($order['invoice']['total'], 0, ',', '.').' / Terbayar Rp '.number_format($order['invoice']['paid'], 0, ',', '.') : '-'],
                ['label' => 'Sisa Piutang', 'value' => !empty($order['invoice']) ? 'Rp '.number_format($order['invoice']['balance'], 0, ',', '.') : '-'],
            ]" />
        </div>

        <div class="space-y-6">
            <x-ui.card title="Status 3 Jalur Terpisah">
                <dl class="space-y-3 text-sm">
                    <div class="flex items-center justify-between gap-2 border-b pb-3">
                        <dt class="text-muted-foreground">Operasional</dt>
                        <dd><x-ui.badge :variant="$st['tone']" :dot="true">{{ $st['label'] }}</x-ui.badge></dd>
                    </div>
                    <div class="flex items-center justify-between gap-2 border-b pb-3">
                        <dt class="text-muted-foreground">Penagihan</dt>
                        <dd><x-ui.badge :variant="$bst['tone']">{{ $bst['label'] }}</x-ui.badge></dd>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <dt class="text-muted-foreground">Pembayaran</dt>
                        <dd><x-ui.badge :variant="$pst['tone']">{{ $pst['label'] }}</x-ui.badge></dd>
                    </div>
                </dl>
            </x-ui.card>

            <x-ui.card title="Riwayat Perubahan Status" description="Rekam jejak kronologis lintas bagian.">
                <x-ui.timeline :items="$order['timeline']" />
            </x-ui.card>
        </div>
    </div>
</x-layouts.admin>
