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
                ['label' => 'Kode Pesanan', 'value' => $order['order_number']],
                ['label' => 'Tanggal & Jam Pemesanan', 'value' => $order['submitted_at'] ?? '-'],
                ['label' => 'Perusahaan Client', 'value' => $order['client_company']],
                ['label' => 'Alamat / Wilayah Perusahaan', 'value' => $order['company_address'] ?? '-'],
                ['label' => 'Jenis Transaksi', 'value' => $order['transaction_type_label']],
                ['label' => 'Produk & Layanan', 'value' => $order['product_name'].' · '.$order['service_type']],
                ['label' => 'Kuantitas Pengajuan', 'value' => $order['requested_qty'].' '.$order['unit']],
                ['label' => 'Tanggal Penyerahan / Terima', 'value' => $order['needed_date'] ?? '-'],
                ['label' => 'Jenis Pembayaran', 'value' => $order['payment_method_display'] ?? ($order['payment_method'] ?: 'Belum dipilih')],
                ['label' => 'Catatan Tambahan, Warna & Penanganan', 'value' => $order['specification_notes'] ?? ($order['client_notes'] ?? '-')],
            ]">
                @if (!empty($order['google_maps_url']))
                    <div class="min-w-0 sm:col-span-2">
                        <a href="{{ $order['google_maps_url'] }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 text-xs font-semibold text-brand-accent hover:underline">
                            <x-ui.icon name="map-pin" class="h-3.5 w-3.5" />
                            Buka Titik Lokasi Perusahaan di Google Maps
                        </a>
                    </div>
                @endif
            </x-ui.detail-list>

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

            {{-- FASE 4 (5.1): KONTROL EXCEPTION HANDLING - MANAGER OVERRIDE & SPLIT BILLING --}}
            @php
                $checklist = $order['compliance_checklist'] ?? ['items' => [], 'all_valid' => false];
                $gateIn = $order['warehouse_gate_in'] ?? null;
                $ba = $order['discrepancy_report'] ?? null;
                $billableMax = (int) ($gateIn['billable_qty'] ?? $order['result']['completed_qty'] ?? $order['requested_qty'] ?? 1);
            @endphp
            <x-ui.card title="4. Kontrol Kepatuhan, Manager Override & Split Billing (Direktur)" description="Otorisasi khusus Direktur/Owner untuk membuka kunci transaksi tertahan dokumen atau memecah penagihan parsial. Semua tindakan tercatat permanen di Audit Log.">
                <div class="space-y-5">
                    <div class="flex flex-wrap gap-2">
                        @foreach ($checklist['items'] as $ci)
                            <span class="badge {{ $ci['valid'] ? 'badge-success' : 'badge-warning' }}">
                                {{ $ci['label'] }}: {{ $ci['valid'] ? 'Valid' : 'Pending' }}
                            </span>
                        @endforeach
                        @if (!empty($order['recondition_cert']))
                            <a href="{{ $order['recondition_cert']['verify_url'] }}" target="_blank" rel="noopener" class="badge badge-info hover:underline">
                                Verifikasi QR {{ $order['recondition_cert']['cert_number'] }}
                            </a>
                        @endif
                    </div>

                    @if (!empty($order['manager_override']['active']))
                        <x-ui.alert variant="warning" title="Manager Override Sudah Diterapkan">
                            Oleh <strong>{{ $order['manager_override']['by'] }}</strong> pada {{ $order['manager_override']['at'] }}. Alasan: {{ $order['manager_override']['reason'] }}
                        </x-ui.alert>
                    @else
                        <form action="{{ route('owner.transactions.override', $order['order_slug']) }}" method="POST" class="space-y-3 rounded-lg border bg-muted/20 p-4" novalidate data-validate-form data-prevent-double-submit>
                            @csrf
                            <p class="text-sm font-semibold">Manager Override (Buka Kunci Dokumen / Transaksi Tertahan)</p>
                            <x-ui.field label="Alasan Resmi Manager Override (Wajib minimal 20 karakter)" name="override_reason" :error="$errors->first('override_reason')" required>
                                <x-ui.textarea name="override_reason" rows="2" minlength="20" placeholder="Jelaskan alasan otorisasi direksi membuka kunci dokumen transaksi ini (min. 20 karakter)..." required>{{ old('override_reason') }}</x-ui.textarea>
                            </x-ui.field>
                            <x-ui.button type="submit" variant="destructive">
                                <x-ui.icon name="shield" class="h-4 w-4" />
                                Terapkan Manager Override (Tercatat di Audit Log)
                            </x-ui.button>
                        </form>
                    @endif

                    <form action="{{ route('owner.transactions.split-billing', $order['order_slug']) }}" method="POST" class="space-y-3 rounded-lg border p-4" data-prevent-double-submit>
                        @csrf
                        <p class="text-sm font-semibold">Penagihan Parsial (Split Billing)</p>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <x-ui.field label="Jumlah Drum Tagih Parsial" name="split_qty" required>
                                <x-ui.input name="split_qty" type="number" min="1" :max="max(1, $billableMax)" :value="max(1, $billableMax)" required />
                            </x-ui.field>
                            <x-ui.field label="Label Sub-Tagihan" name="split_label">
                                <x-ui.input name="split_label" :value="'Tagihan Parsial ('.$billableMax.' '.$order['unit'].' Disetujui)'" />
                            </x-ui.field>
                        </div>
                        <x-ui.button type="submit" variant="outline">
                            <x-ui.icon name="layers" class="h-4 w-4" />
                            Terbitkan Tagihan Parsial (Split Billing)
                        </x-ui.button>
                    </form>
                </div>
            </x-ui.card>
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
