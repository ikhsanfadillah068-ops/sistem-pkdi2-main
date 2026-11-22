@php
    $st = \App\Support\PkdiDemoData::statusMeta($order['order_status']);
    $bst = $order['billing_status_meta'];
    $pst = $order['payment_status_meta'];
    $orderSlug = $order['order_slug'];
    $isWasteTransport = (bool) ($order['is_waste_transport'] ?? $order['is_drum_sale'] ?? false);
    $isReconditioning = ! $isWasteTransport;
    $whTracker = $order['warehouse_tracker'] ?? ['variant' => 'RECONDITIONING', 'title' => 'Alur Operasional Gudang', 'steps' => []];

    $isReviewStage = in_array($order['order_status'], ['SUBMITTED', 'UNDER_REVIEW', 'NEEDS_CLARIFICATION', 'WAITING_WAREHOUSE', 'WAREHOUSE_REVIEW', 'WAITING_GATE_IN'], true);
    $canScheduleTransport = $isWasteTransport && in_array($order['order_status'], ['APPROVED', 'ACCEPTED', 'WAITING_RECEIPT', 'TRANSPORT_SCHEDULED', 'IN_TRANSIT', 'PREPARING'], true) && empty($order['receipt']);
    $canRecordReceipt = in_array($order['order_status'], ['APPROVED', 'ACCEPTED', 'WAITING_RECEIPT', 'TRANSPORT_SCHEDULED', 'IN_TRANSIT', 'PREPARING'], true) && empty($order['receipt']);
    $isSortingStage = $isReconditioning && $order['order_status'] === 'RECEIVED';
    $isActiveReconditioning = $isReconditioning && in_array($order['order_status'], ['IN_PROGRESS', 'PROCESSING', 'PREPARING'], true);
    $canCompleteWasteTransport = $isWasteTransport && in_array($order['order_status'], ['RECEIVED', 'SORTING', 'TRANSPORT_SCHEDULED', 'IN_TRANSIT', 'PREPARING', 'IN_PROGRESS'], true) && empty($order['result']);
    $isDeliveryStage = $isReconditioning && in_array($order['order_status'], ['READY', 'READY_FOR_HANDOVER', 'PROCESS_COMPLETED', 'IN_DELIVERY', 'SERVICE_COMPLETED', 'WAITING_FINANCE', 'INVOICE_ISSUED', 'PAYMENT_PARTIAL', 'PAID', 'WAITING_EXIT_DOCUMENT', 'WAITING_GATE_OUT'], true);
    $isCompleted = in_array($order['order_status'], ['DELIVERED', 'COMPLETED'], true) || ($isWasteTransport && !empty($order['result']));
    $isCancelled = in_array($order['order_status'], ['CANCELLED', 'REJECTED'], true);

    $expectedSortTotal = $order['receipt']['actual_received_quantity'] ?? $order['requested_qty'];
    $defaultCompletedQty = $order['result']['completed_qty']
        ?? ($order['sorting']['processable_qty']
            ?? ($order['inspection']['actual_quantity']
                ?? ($order['receipt']['actual_received_quantity'] ?? $order['requested_qty'])));
    $defaultRejectedQty = $order['result']['rejected_qty'] ?? ($order['sorting']['qty_rejected'] ?? 0);
@endphp
<x-layouts.admin :title="'Gudang '.$order['order_number']" group="Operasional Gudang" role="kepala_gudang">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <a href="{{ route('warehouse.dashboard') }}" class="mb-2 inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground">
                <x-ui.icon name="arrow-left" class="h-4 w-4" />
                Kembali ke Antrean Gudang
            </a>
            <div class="flex flex-wrap items-center gap-2.5">
                <h1 class="text-2xl font-semibold tracking-tight tabular-nums">{{ $order['order_number'] }}</h1>
                <x-ui.badge :variant="$st['tone']" :dot="true">{{ $st['label'] }}</x-ui.badge>
                <x-ui.badge variant="outline">{{ $order['transaction_type_label'] }}</x-ui.badge>
            </div>
            <p class="mt-1 text-sm text-muted-foreground">{{ $order['client_company'] }} · Diajukan {{ $order['submitted_at'] }}</p>
        </div>
    </div>

    {{-- TRACKER OPERASIONAL INTERNAL GUDANG --}}
    <section class="card p-4 sm:p-5" aria-label="Tahapan operasional gudang">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <div class="flex items-center gap-2">
                <span class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Tahapan Operasional</span>
                <span class="badge {{ $isWasteTransport ? 'badge-info' : 'badge-soft' }}">{{ $whTracker['title'] }}</span>
            </div>
            <span class="text-xs font-medium text-muted-foreground tabular-nums">
                {{ count($whTracker['steps']) }} Tahap
            </span>
        </div>
        <ol class="grid gap-2 {{ $isWasteTransport ? 'sm:grid-cols-4' : 'sm:grid-cols-5' }}">
            @foreach ($whTracker['steps'] as $wStep)
                <li @class([
                    'flex items-center gap-2.5 rounded-lg border px-3 py-2 text-xs',
                    'border-success/40 bg-success-soft/40 text-foreground font-medium' => $wStep['state'] === 'done',
                    'border-primary bg-soft text-foreground font-semibold' => $wStep['state'] === 'current',
                    'bg-muted/20 text-muted-foreground' => $wStep['state'] === 'todo',
                ])>
                    <span @class([
                        'flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold tabular-nums',
                        'bg-success text-white' => $wStep['state'] === 'done',
                        'bg-primary text-primary-foreground' => $wStep['state'] === 'current',
                        'bg-muted text-muted-foreground' => $wStep['state'] === 'todo',
                    ])>{{ $wStep['number'] }}</span>
                    <span class="truncate">{{ $wStep['label'] }}</span>
                </li>
            @endforeach
        </ol>
    </section>

    @if ($isCancelled)
        <x-ui.alert variant="danger" title="Permintaan Ditolak / Dibatalkan">
            {{ $order['progress']['summary'] ?? 'Permintaan ini telah dibatalkan oleh Kepala Gudang.' }}
        </x-ui.alert>
    @elseif ($order['order_status'] === 'NEEDS_CLARIFICATION')
        <x-ui.alert variant="warning" title="Menunggu Klarifikasi Client">
            {{ $order['progress']['summary'] ?? 'Permintaan klarifikasi telah dikirim ke Client. Anda tetap dapat menyetujui atau menolak setelah klarifikasi selesai.' }}
        </x-ui.alert>
    @endif

    <div class="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(18rem,1fr)]">
        <div class="space-y-6">
            {{-- Permintaan Awal Client (Read-Only Snapshot - Tidak Pernah Ditimpa) --}}
            <x-ui.card title="Permintaan Awal Client" description="Data pengajuan asli dari Client. Data estimasi ini tidak ditimpa oleh hasil penerimaan aktual Gudang.">
                <dl class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Jenis Layanan</dt>
                        <dd class="mt-1 font-medium">{{ $order['transaction_type_label'] }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Jenis Drum / Kemasan</dt>
                        <dd class="mt-1 font-medium">{{ $order['product_name'] }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Paket / Layanan</dt>
                        <dd class="mt-1 font-medium">{{ $order['service_type'] }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Kuantitas Estimasi</dt>
                        <dd class="mt-1 font-semibold tabular-nums">{{ $order['requested_qty'] }} {{ $order['unit'] }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Nomor PO</dt>
                        <dd class="mt-1 font-medium tabular-nums">{{ $order['po_number'] ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Tanggal Kebutuhan</dt>
                        <dd class="mt-1 font-medium tabular-nums">{{ $order['needed_date'] ?? '-' }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Spesifikasi & Catatan Client</dt>
                        <dd class="mt-1 text-sm leading-relaxed text-foreground">
                            {{ $order['specification_notes'] ?: ($order['client_notes'] ?: 'Tidak ada catatan tambahan dari Client.') }}
                            @if (!empty($order['specification_notes']) && !empty($order['client_notes']))
                                <span class="mt-1 block text-muted-foreground">Catatan lokasi/tambahan: {{ $order['client_notes'] }}</span>
                            @endif
                        </dd>
                    </div>
                </dl>

                @if (!empty($order['po_attachment_path']) || !empty($order['attachment_path']))
                    <div class="mt-5 flex flex-wrap gap-2 border-t pt-4">
                        @if (!empty($order['po_attachment_path']))
                            <a href="{{ route('orders.attachments.show', ['code' => $orderSlug, 'type' => 'po']) }}" target="_blank" rel="noopener" class="btn btn-outline btn-sm">
                                <x-ui.icon name="file-text" />
                                Lihat Dokumen PO
                            </a>
                        @endif
                        @if (!empty($order['attachment_path']))
                            <a href="{{ route('orders.attachments.show', ['code' => $orderSlug, 'type' => 'client']) }}" target="_blank" rel="noopener" class="btn btn-outline btn-sm">
                                <x-ui.icon name="image" />
                                Lihat Lampiran / Foto Client
                            </a>
                        @endif
                    </div>
                @endif
            </x-ui.card>

            {{-- TAHAP 1: TINJAUAN AWAL KEPALA GUDANG --}}
            @if ($isReviewStage)
                <x-ui.card
                    title="Tahap 1: Tinjauan Operasional Permintaan"
                    :description="$isWasteTransport
                        ? 'Periksa kelayakan permintaan pengangkutan limbah, jenis kemasan, dan tanggal kebutuhan sebelum menjadwalkan penjemputan atau penerimaan.'
                        : 'Tinjau permintaan pengangkutan & rekondisi drum dari Client. Setelah disetujui, lanjutkan ke koordinasi pengangkutan dan penerimaan fisik.'">
                    <form action="{{ route('warehouse.orders.review', $orderSlug) }}" method="POST" class="space-y-4" novalidate data-validate-form data-prevent-double-submit>
                        @csrf
                        <input type="hidden" name="decision" value="ACCEPT">
                        <x-ui.field label="Catatan Tinjauan / Kesiapan Operasional (Opsional)" name="review_notes" :error="$errors->first('review_notes')">
                            <x-ui.textarea name="review_notes" rows="2" placeholder="{{ $isWasteTransport ? 'Catat kesiapan jadwal armada pengangkutan limbah' : 'Catat kesiapan jalur rekondisi atau instruksi penjemputan drum' }}">{{ old('review_notes') }}</x-ui.textarea>
                        </x-ui.field>
                        <div class="flex flex-wrap gap-2 pt-1">
                            <x-ui.button type="submit" variant="success">
                                <x-ui.icon name="check" />
                                Setujui Permintaan untuk Ditangani
                            </x-ui.button>
                            <button type="button" class="btn btn-outline" data-modal-open="#modal-wh-clarify">Minta Klarifikasi</button>
                            <button type="button" class="btn btn-destructive" data-modal-open="#modal-wh-reject">Tolak Permintaan</button>
                        </div>
                    </form>
                </x-ui.card>
            @endif

            {{-- TAHAP 2: KOORDINASI PENGANGKUTAN & PERJALANAN (INTERNAL PKDI - PRD v2.1 BAB 8.4) --}}
            @if ($canScheduleTransport)
                <x-ui.card title="Tahap 2: Koordinasi Pengangkutan & Status Perjalanan (Internal PKDI)" description="Catat jadwal penjemputan, transporter/armada, nomor polisi, dan pengemudi bila pengangkutan diatur oleh PKDI.">
                    <form action="{{ route('warehouse.orders.transport.store', $orderSlug) }}" method="POST" class="space-y-4" novalidate data-validate-form data-prevent-double-submit>
                        @csrf
                        <div class="grid gap-4 sm:grid-cols-3">
                            <x-ui.field label="Status Pengangkutan" name="transport_status" required>
                                <select id="transport_status" name="transport_status" class="select" required>
                                    <option value="TRANSPORT_SCHEDULED" @selected(old('transport_status', $order['order_status']) === 'TRANSPORT_SCHEDULED')>Pengangkutan Dijadwalkan</option>
                                    <option value="IN_TRANSIT" @selected(old('transport_status', $order['order_status']) === 'IN_TRANSIT')>Dalam Perjalanan Menuju PKDI</option>
                                </select>
                            </x-ui.field>
                            <x-ui.field label="Tanggal Jadwal Penjemputan" name="scheduled_pickup_date">
                                <x-ui.input name="scheduled_pickup_date" type="date" :value="old('scheduled_pickup_date', $order['scheduled_pickup_date_raw'] ?? ($order['needed_date_raw'] ?? now('Asia/Jakarta')->toDateString()))" />
                            </x-ui.field>
                            <x-ui.field label="Transporter / Armada" name="transporter_name">
                                <x-ui.input name="transporter_name" :value="old('transporter_name', $order['transporter_name'] ?? 'Armada Internal PKDI')" placeholder="Armada Internal PKDI" />
                            </x-ui.field>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-4">
                            <x-ui.field label="Jenis Kendaraan" name="vehicle_type">
                                <x-ui.input name="vehicle_type" :value="old('vehicle_type', $order['vehicle_type'])" placeholder="Truk Fuso / Engkel" />
                            </x-ui.field>
                            <x-ui.field label="Nomor Polisi" name="license_plate">
                                <x-ui.input name="license_plate" :value="old('license_plate', $order['license_plate'])" placeholder="B 9102 PKD" />
                            </x-ui.field>
                            <x-ui.field label="Nama Pengemudi" name="driver_name">
                                <x-ui.input name="driver_name" :value="old('driver_name', $order['driver_name'])" placeholder="Nama sopir" />
                            </x-ui.field>
                            <x-ui.field label="Kontak Pengemudi" name="driver_phone">
                                <x-ui.input name="driver_phone" :value="old('driver_phone', $order['driver_phone'])" placeholder="0812..." />
                            </x-ui.field>
                        </div>
                        <x-ui.field label="Catatan Perjalanan / Penjemputan (Opsional)" name="transport_notes">
                            <x-ui.input name="transport_notes" :value="old('transport_notes', $order['transport_notes'])" placeholder="Rute penjemputan atau catatan koordinasi lapangan" />
                        </x-ui.field>
                        <x-ui.button type="submit" variant="outline">
                            <x-ui.icon name="truck" />
                            Simpan Jadwal / Status Perjalanan
                        </x-ui.button>
                    </form>
                </x-ui.card>
            @elseif (!empty($order['scheduled_pickup_date']) || !empty($order['transporter_name']) || !empty($order['in_transit_at']))
                <x-ui.card title="Ringkasan Pengangkutan & Perjalanan" description="Data transporter dan jadwal penjemputan yang dicatat oleh pihak PKDI.">
                    <dl class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Jadwal Penjemputan</dt>
                            <dd class="mt-1 font-medium tabular-nums">{{ $order['scheduled_pickup_date'] ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Transporter / Armada</dt>
                            <dd class="mt-1 font-medium">{{ $order['transporter_name'] ?? 'Internal PKDI' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Kendaraan & Nopol</dt>
                            <dd class="mt-1 tabular-nums">{{ trim(($order['vehicle_type'] ?? '').' '.($order['license_plate'] ?? '')) ?: '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Pengemudi & Kontak</dt>
                            <dd class="mt-1">{{ $order['driver_name'] ?? '-' }} @if (!empty($order['driver_phone'])) ({{ $order['driver_phone'] }}) @endif</dd>
                        </div>
                    </dl>
                </x-ui.card>
            @endif

            {{-- TAHAP 3: PENERIMAAN FISIK DI FASILITAS PKDI (BERLAKU UNTUK PENGANGKUTAN LIMBAH MAUPUN REKONDISI) --}}
            @if ($canRecordReceipt)
                <x-ui.card
                    title="Tahap 3: Pencatatan Penerimaan Fisik di Gudang PKDI"
                    :description="$isWasteTransport
                        ? 'Catat kuantitas dan kondisi fisik limbah/kemasan saat tiba di fasilitas PKDI. Data estimasi awal Client tetap tersimpan terpisah.'
                        : 'Catat kuantitas dan kondisi aktual drum saat tiba di Gudang sebelum masuk tahap penyortiran rekondisi.'">
                    <form action="{{ route('warehouse.orders.receipt.store', $orderSlug) }}" method="POST" enctype="multipart/form-data" class="space-y-4" novalidate data-validate-form data-prevent-double-submit>
                        @csrf
                        <div class="grid gap-4 sm:grid-cols-3">
                            <x-ui.field label="Jumlah Aktual Diterima" name="actual_received_quantity" :error="$errors->first('actual_received_quantity')" required>
                                <x-ui.input name="actual_received_quantity" type="number" min="1" :value="old('actual_received_quantity', $order['requested_qty'])" required />
                            </x-ui.field>
                            <x-ui.field label="Satuan" name="unit" :error="$errors->first('unit')" required>
                                <x-ui.input name="unit" :value="old('unit', $order['unit'])" required />
                            </x-ui.field>
                            <x-ui.field label="Waktu Penerimaan" name="received_at" :error="$errors->first('received_at')">
                                <x-ui.input name="received_at" type="datetime-local" :value="old('received_at', now('Asia/Jakarta')->format('Y-m-d\TH:i'))" />
                            </x-ui.field>
                        </div>
                        <x-ui.field label="Kondisi Awal Fisik Saat Diterima" name="initial_condition" :error="$errors->first('initial_condition')" required>
                            <x-ui.textarea name="initial_condition" rows="2" minlength="5" placeholder="Jelaskan kondisi fisik drum/kemasan limbah saat tiba di Gudang PKDI" required>{{ old('initial_condition') }}</x-ui.textarea>
                        </x-ui.field>
                        <x-ui.field label="Catatan Ketidaksesuaian / Selisih (Opsional)" name="discrepancy_notes" :error="$errors->first('discrepancy_notes')">
                            <x-ui.input name="discrepancy_notes" :value="old('discrepancy_notes')" placeholder="Catat bila jumlah aktual berbeda dari estimasi pengajuan Client" />
                        </x-ui.field>
                        <x-ui.field label="Foto Dokumentasi Penerimaan (Opsional)" name="photo" :error="$errors->first('photo')" help="Format JPG, PNG, atau WEBP maksimal 5 MB.">
                            <input id="photo" name="photo" type="file" accept="image/*" capture="environment" class="input file:mr-3 file:rounded-md file:border-0 file:bg-muted file:px-3 file:py-1.5 file:text-xs file:font-medium">
                        </x-ui.field>
                        <div class="flex flex-wrap gap-2 pt-2">
                            <x-ui.button type="submit" variant="primary">
                                <x-ui.icon name="package" />
                                Simpan Penerimaan Fisik
                            </x-ui.button>
                        </div>
                    </form>
                </x-ui.card>
            @endif

            {{-- RINGKASAN PENERIMAAN FISIK (Bila Sudah Tercatat) --}}
            @if (!empty($order['receipt']))
                <x-ui.card title="Ringkasan Penerimaan Fisik di PKDI" :description="'Nomor Terima: '.$order['receipt']['receipt_number'].' · '.$order['receipt']['received_at']">
                    <dl class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Jumlah Aktual Diterima</dt>
                            <dd class="mt-1 font-semibold tabular-nums">{{ $order['receipt']['actual_received_quantity'] }} {{ $order['receipt']['unit'] }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Petugas Penerima</dt>
                            <dd class="mt-1 font-medium">{{ $order['receipt']['officer'] }}</dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Kondisi Awal</dt>
                            <dd class="mt-1 text-sm">{{ $order['receipt']['initial_condition'] }}</dd>
                        </div>
                        @if (!empty($order['receipt']['discrepancy_notes']))
                            <div class="sm:col-span-2">
                                <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Catatan Selisih</dt>
                                <dd class="mt-1 text-sm text-warning">{{ $order['receipt']['discrepancy_notes'] }}</dd>
                            </div>
                        @endif
                    </dl>
                    @if (!empty($order['receipt']['photo_path']))
                        <div class="mt-4 border-t pt-3">
                            <a href="{{ route('orders.attachments.show', ['code' => $orderSlug, 'type' => 'receipt']) }}" target="_blank" rel="noopener" class="btn btn-outline btn-sm">
                                <x-ui.icon name="image" />
                                Lihat Foto Penerimaan
                            </a>
                        </div>
                    @endif
                </x-ui.card>
            @endif

            {{-- KONFIRMASI HASIL PENGANGKUTAN LIMBAH & SIAP TAGIH --}}
            @if ($canCompleteWasteTransport)
                <x-ui.card
                    title="Konfirmasi Hasil Penerimaan & Penagihan"
                    description="Catat kuantitas limbah/kemasan yang diterima layak untuk diteruskan ke antrean penagihan bagian Keuangan.">
                    <form action="{{ route('warehouse.orders.result.store', $orderSlug) }}" method="POST" enctype="multipart/form-data" class="space-y-4" novalidate data-validate-form data-prevent-double-submit>
                        @csrf
                        <input type="hidden" name="actual_service_type" value="Pengangkutan Limbah">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-ui.field label="Kuantitas Diterima & Ditangani (Layak Tagih)" name="completed_qty" :error="$errors->first('completed_qty')" required>
                                <x-ui.input name="completed_qty" type="number" min="0" :value="old('completed_qty', $defaultCompletedQty)" required />
                            </x-ui.field>
                            <x-ui.field label="Kuantitas Tidak Sesuai / Ditolak" name="rejected_qty" :error="$errors->first('rejected_qty')" required>
                                <x-ui.input name="rejected_qty" type="number" min="0" :value="old('rejected_qty', $defaultRejectedQty)" required />
                            </x-ui.field>
                        </div>
                        <x-ui.field label="Catatan Hasil Penerimaan Limbah untuk Keuangan" name="result_notes" :error="$errors->first('result_notes')">
                            <x-ui.textarea name="result_notes" rows="2" placeholder="Ringkasan hasil penerimaan pengangkutan limbah">{{ old('result_notes') }}</x-ui.textarea>
                        </x-ui.field>
                        <x-ui.field label="Foto Dokumentasi Hasil Penerimaan (Opsional)" name="photo" :error="$errors->first('photo')">
                            <input id="waste_result_photo" name="photo" type="file" accept="image/*" capture="environment" class="input file:mr-3 file:rounded-md file:border-0 file:bg-muted file:px-3 file:py-1.5 file:text-xs file:font-medium">
                        </x-ui.field>
                        <x-ui.button type="submit" variant="primary" class="w-full">
                            <x-ui.icon name="check-circle" />
                            Konfirmasi Pengangkutan Selesai & Masuk Antrean Penagihan
                        </x-ui.button>
                    </form>
                </x-ui.card>
            @endif

            {{-- PEMERIKSAAN KONDISI & PENYORTIRAN SEIMBANG --}}
            @if ($isSortingStage)
                <x-ui.card title="Pemeriksaan Kondisi & Penyortiran Drum" description="Klasifikasikan seluruh drum diterima. Jumlah layak proses + penanganan khusus + tidak layak wajib sama persis dengan jumlah aktual diterima.">
                    <form
                        action="{{ route('warehouse.orders.sorting.store', $orderSlug) }}"
                        method="POST"
                        enctype="multipart/form-data"
                        class="space-y-4"
                        novalidate
                        data-validate-form
                        data-prevent-double-submit
                        data-sorting-form
                        data-expected-total="{{ $expectedSortTotal }}"
                        data-sorting-unit="{{ $order['unit'] }}">
                        @csrf
                        <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg border bg-muted/40 px-4 py-3 text-sm">
                            <span class="font-medium">Target Total Sortir: <strong class="tabular-nums">{{ $expectedSortTotal }} {{ $order['unit'] }}</strong></span>
                            <span class="badge badge-success tabular-nums" data-sorting-balance>Seimbang: {{ $expectedSortTotal }} / {{ $expectedSortTotal }} {{ $order['unit'] }}</span>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-3">
                            <x-ui.field label="Layak Proses Standar" name="qty_passed" :error="$errors->first('qty_passed')" required>
                                <x-ui.input name="qty_passed" type="number" min="0" :value="old('qty_passed', $expectedSortTotal)" required />
                            </x-ui.field>
                            <x-ui.field label="Penanganan Khusus" name="qty_special_repair" :error="$errors->first('qty_special_repair')" required>
                                <x-ui.input name="qty_special_repair" type="number" min="0" :value="old('qty_special_repair', 0)" required />
                            </x-ui.field>
                            <x-ui.field label="Tidak Layak / Reject" name="qty_rejected" :error="$errors->first('qty_rejected')" required>
                                <x-ui.input name="qty_rejected" type="number" min="0" :value="old('qty_rejected', 0)" required />
                            </x-ui.field>
                        </div>

                        <x-ui.field label="Ringkasan Kondisi Fisik Hasil Pemeriksaan" name="cargo_condition" :error="$errors->first('cargo_condition')" required>
                            <x-ui.textarea name="cargo_condition" rows="2" minlength="5" placeholder="Catat hasil pemeriksaan fisik dan kelayakan proses" required>{{ old('cargo_condition', $order['receipt']['initial_condition'] ?? '') }}</x-ui.textarea>
                        </x-ui.field>

                        <div data-rejected-reason-wrap>
                            <x-ui.field label="Alasan Drum Tidak Layak (Wajib bila Reject > 0)" name="rejected_reason" :error="$errors->first('rejected_reason')">
                                <x-ui.input name="rejected_reason" :value="old('rejected_reason')" placeholder="Contoh: Kebocoran korosi parah pada dasar drum" />
                            </x-ui.field>
                        </div>

                        <x-ui.field label="Catatan Penyortiran Tambahan (Opsional)" name="sorting_notes" :error="$errors->first('sorting_notes')">
                            <x-ui.input name="sorting_notes" :value="old('sorting_notes')" placeholder="Catatan teknis internal untuk tim produksi" />
                        </x-ui.field>

                        <x-ui.field label="Foto Dokumentasi Penyortiran (Opsional)" name="photo" :error="$errors->first('photo')">
                            <input id="sorting_photo" name="photo" type="file" accept="image/*" capture="environment" class="input file:mr-3 file:rounded-md file:border-0 file:bg-muted file:px-3 file:py-1.5 file:text-xs file:font-medium">
                        </x-ui.field>

                        <div class="flex flex-wrap gap-2 pt-2">
                            <x-ui.button type="submit" variant="success">
                                <x-ui.icon name="check" />
                                Simpan Sortir & Mulai Proses Rekondisi
                            </x-ui.button>
                            <button type="button" class="btn btn-destructive" data-modal-open="#modal-wh-reject">Tolak Seluruh Permintaan</button>
                        </div>
                    </form>
                </x-ui.card>
            @endif

            {{-- RINGKASAN PENYORTIRAN (Bila Sudah Tercatat) --}}
            @if (!empty($order['sorting']))
                <x-ui.card title="Hasil Pemeriksaan & Penyortiran Drum" :description="'Dicatat '.$order['sorting']['sorted_at'].' · '.$order['sorting']['officer']">
                    <div class="grid gap-3 sm:grid-cols-3">
                        <div class="rounded-lg border p-3">
                            <p class="text-xs text-muted-foreground">Layak Proses Standar</p>
                            <p class="mt-1 text-lg font-semibold tabular-nums text-success">{{ $order['sorting']['qty_passed'] }} {{ $order['sorting']['unit'] }}</p>
                        </div>
                        <div class="rounded-lg border p-3">
                            <p class="text-xs text-muted-foreground">Penanganan Khusus</p>
                            <p class="mt-1 text-lg font-semibold tabular-nums text-warning">{{ $order['sorting']['qty_special_repair'] }} {{ $order['sorting']['unit'] }}</p>
                        </div>
                        <div class="rounded-lg border p-3">
                            <p class="text-xs text-muted-foreground">Tidak Layak / Reject</p>
                            <p class="mt-1 text-lg font-semibold tabular-nums text-danger">{{ $order['sorting']['qty_rejected'] }} {{ $order['sorting']['unit'] }}</p>
                        </div>
                    </div>
                    @if (!empty($order['sorting']['rejected_reason']) || !empty($order['sorting']['sorting_notes']))
                        <div class="mt-3 space-y-1 text-sm">
                            @if (!empty($order['sorting']['rejected_reason']))
                                <p><span class="font-medium text-danger">Alasan Reject:</span> {{ $order['sorting']['rejected_reason'] }}</p>
                            @endif
                            @if (!empty($order['sorting']['sorting_notes']))
                                <p class="text-muted-foreground">Catatan sortir: {{ $order['sorting']['sorting_notes'] }}</p>
                            @endif
                        </div>
                    @endif
                    @if (!empty($order['sorting']['photo_path']))
                        <div class="mt-4 border-t pt-3">
                            <a href="{{ route('orders.attachments.show', ['code' => $orderSlug, 'type' => 'sorting']) }}" target="_blank" rel="noopener" class="btn btn-outline btn-sm">
                                <x-ui.icon name="image" />
                                Lihat Foto Penyortiran
                            </a>
                        </div>
                    @endif
                </x-ui.card>
            @endif

            {{-- TAHAP 5 & 6 (KHUSUS REKONDISI): PROSES PENGERJAAN & HASIL AKHIR REKONDISI --}}
            @if ($isActiveReconditioning)
                <x-ui.card
                    :title="'Tahap 5 (Alur Rekondisi): Pembaruan Progres Rekondisi ('.$order['service_type'].')'"
                    description="Catat perkembangan pengerjaan internal beserta foto dokumentasi bila diperlukan.">
                    @if (!empty($order['processes']))
                        <div class="mb-5 space-y-2.5">
                            @foreach ($order['processes'] as $proc)
                                <div class="rounded-lg border bg-background p-3.5 text-sm">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <div class="flex items-center gap-2">
                                            <span class="font-semibold">{{ $proc['stage'] }}</span>
                                            @if (!$proc['is_visible_to_client'])
                                                <x-ui.badge variant="neutral">Internal Gudang</x-ui.badge>
                                            @endif
                                        </div>
                                        <span class="text-xs text-muted-foreground tabular-nums">{{ $proc['time'] }}</span>
                                    </div>
                                    <p class="mt-1 text-muted-foreground">{{ $proc['note'] }} ({{ $proc['qty'] }} {{ $order['unit'] }})</p>
                                    @if (!empty($proc['photo_path']))
                                        <div class="mt-2">
                                            <a href="{{ route('orders.attachments.show', ['code' => $orderSlug, 'type' => 'process', 'item' => $proc['id']]) }}" target="_blank" rel="noopener" class="text-xs font-medium text-primary hover:underline">
                                                Lihat foto progres
                                            </a>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <form action="{{ route('warehouse.orders.processes.store', $orderSlug) }}" method="POST" enctype="multipart/form-data" class="space-y-4" novalidate data-validate-form data-prevent-double-submit>
                        @csrf
                        <div class="grid gap-4 sm:grid-cols-3">
                            <div class="sm:col-span-2">
                                <x-ui.field label="Tahap Pengerjaan" name="process_stage" :error="$errors->first('process_stage')" required>
                                    <input id="process_stage" name="process_stage" list="wh-stage-presets" minlength="3" value="{{ old('process_stage') }}" class="input" placeholder="Pilih atau tulis tahap pengerjaan" required>
                                    <datalist id="wh-stage-presets">
                                        @foreach ($stagePresets as $preset)
                                            <option value="{{ $preset }}"></option>
                                        @endforeach
                                    </datalist>
                                </x-ui.field>
                            </div>
                            <x-ui.field label="Kuantitas Diproses" name="processed_qty" :error="$errors->first('processed_qty')">
                                <x-ui.input name="processed_qty" type="number" min="1" :value="old('processed_qty', $defaultCompletedQty)" />
                            </x-ui.field>
                        </div>

                        <x-ui.field label="Catatan Progres" name="progress_notes" :error="$errors->first('progress_notes')" required>
                            <x-ui.textarea name="progress_notes" rows="2" minlength="3" placeholder="Catat perkembangan pengerjaan aktual" required>{{ old('progress_notes') }}</x-ui.textarea>
                        </x-ui.field>

                        <x-ui.field label="Foto Progres (Opsional)" name="photo" :error="$errors->first('photo')">
                            <input id="process_photo" name="photo" type="file" accept="image/*" capture="environment" class="input file:mr-3 file:rounded-md file:border-0 file:bg-muted file:px-3 file:py-1.5 file:text-xs file:font-medium">
                        </x-ui.field>

                        <label class="flex cursor-pointer items-center gap-2 text-sm text-foreground">
                            <input type="hidden" name="is_visible_to_client" value="0">
                            <input type="checkbox" name="is_visible_to_client" value="1" class="check" checked>
                            <span>Tampilkan ringkasan tahap ini pada timeline Client</span>
                        </label>

                        <x-ui.button type="submit" variant="outline">
                            <x-ui.icon name="plus" />
                            Simpan Pembaruan Progres
                        </x-ui.button>
                    </form>
                </x-ui.card>

                <x-ui.card
                    title="Tahap 6 (Alur Rekondisi): Pencatatan Hasil Akhir Pekerjaan"
                    description="Setelah dikonfirmasi, status operasional berubah menjadi Siap Diserahkan dan transaksi otomatis masuk antrean penagihan Keuangan.">
                    <form action="{{ route('warehouse.orders.result.store', $orderSlug) }}" method="POST" enctype="multipart/form-data" class="space-y-4" novalidate data-validate-form data-prevent-double-submit>
                        @csrf
                        <x-ui.field label="Paket Layanan Rekondisi Aktual" name="reconditioning_service_id" :error="$errors->first('reconditioning_service_id')" required>
                            <select id="reconditioning_service_id" name="reconditioning_service_id" class="select" required>
                                @foreach ($reconditioningServices as $svc)
                                    <option value="{{ $svc->id }}" @selected((string) old('reconditioning_service_id', $order['reconditioning_service_id']) === (string) $svc->id)>
                                        {{ $svc->name }}
                                    </option>
                                @endforeach
                            </select>
                        </x-ui.field>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-ui.field label="Kuantitas Selesai (Layak Tagih)" name="completed_qty" :error="$errors->first('completed_qty')" required>
                                <x-ui.input name="completed_qty" type="number" min="0" :value="old('completed_qty', $defaultCompletedQty)" required />
                            </x-ui.field>
                            <x-ui.field label="Tidak Selesai / Tidak Layak" name="rejected_qty" :error="$errors->first('rejected_qty')" required>
                                <x-ui.input name="rejected_qty" type="number" min="0" :value="old('rejected_qty', $defaultRejectedQty)" required />
                            </x-ui.field>
                        </div>

                        <x-ui.field label="Catatan Hasil Pekerjaan untuk Keuangan & Serah Terima" name="result_notes" :error="$errors->first('result_notes')">
                            <x-ui.textarea name="result_notes" rows="2" placeholder="Ringkasan hasil akhir rekondisi atau catatan kualitas drum">{{ old('result_notes') }}</x-ui.textarea>
                        </x-ui.field>

                        <x-ui.field label="Foto Hasil Akhir Pekerjaan (Opsional)" name="photo" :error="$errors->first('photo')">
                            <input id="result_photo" name="photo" type="file" accept="image/*" capture="environment" class="input file:mr-3 file:rounded-md file:border-0 file:bg-muted file:px-3 file:py-1.5 file:text-xs file:font-medium">
                        </x-ui.field>

                        <x-ui.button type="submit" variant="primary" class="w-full">
                            <x-ui.icon name="check-circle" />
                            Konfirmasi Selesai & Masuk Antrean Penagihan
                        </x-ui.button>
                    </form>
                </x-ui.card>
            @endif

            {{-- RINGKASAN HASIL AKHIR (Setelah Selesai Kerja) --}}
            @if (!empty($order['result']))
                <x-ui.card title="Ringkasan Hasil Layanan Gudang" :description="'Diselesaikan '.$order['result']['completed_at'].' · '.$order['result']['officer']">
                    <dl class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Layanan Aktual</dt>
                            <dd class="mt-1 font-medium">{{ $order['result']['actual_service_type'] }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Kode Transaksi Siap Tagih</dt>
                            <dd class="mt-1 font-semibold tabular-nums">{{ $order['billable_transaction']['transaction_code'] ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Kuantitas Selesai Layak</dt>
                            <dd class="mt-1 text-lg font-semibold tabular-nums text-success">{{ $order['result']['completed_qty'] }} {{ $order['result']['unit'] }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Tidak Selesai / Tidak Layak</dt>
                            <dd class="mt-1 text-lg font-semibold tabular-nums {{ $order['result']['rejected_qty'] > 0 ? 'text-danger' : 'text-muted-foreground' }}">{{ $order['result']['rejected_qty'] }} {{ $order['result']['unit'] }}</dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Catatan Hasil</dt>
                            <dd class="mt-1 text-sm">{{ $order['result']['notes'] }}</dd>
                        </div>
                    </dl>
                    @if (!empty($order['result']['photo_path']))
                        <div class="mt-4 border-t pt-3">
                            <a href="{{ route('orders.attachments.show', ['code' => $orderSlug, 'type' => 'result']) }}" target="_blank" rel="noopener" class="btn btn-outline btn-sm">
                                <x-ui.icon name="image" />
                                Lihat Foto Hasil Akhir
                            </a>
                        </div>
                    @endif
                </x-ui.card>
            @endif

            {{-- TAHAP PENGIRIMAN DRUM HASIL REKONDISI KE CLIENT (KHUSUS REKONDISI DRUM) --}}
            @if ($isDeliveryStage)
                <x-ui.card title="Pengiriman Drum Hasil Rekondisi ke Client" description="Catat pengiriman drum yang telah selesai direkondisi ke lokasi perusahaan Client menggunakan armada PKDI.">
                    <form action="{{ route('warehouse.orders.delivery.store', $orderSlug) }}" method="POST" class="space-y-4" novalidate data-validate-form data-prevent-double-submit>
                        @csrf
                        <input type="hidden" name="delivery_method" value="DIANTAR_PKDI">

                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-ui.field label="Jumlah Drum Dikirim" name="delivered_quantity" :error="$errors->first('delivered_quantity')" required>
                                <x-ui.input name="delivered_quantity" type="number" min="1" :value="old('delivered_quantity', $order['delivered_quantity'] ?? ($order['result']['completed_qty'] ?? $order['requested_qty']))" required />
                            </x-ui.field>
                            <x-ui.field label="Status Pengiriman" name="delivery_action" :error="$errors->first('delivery_action')" required>
                                <select id="delivery_action" name="delivery_action" class="select" required>
                                    <option value="START_DELIVERY" @selected(old('delivery_action', $order['order_status'] === 'IN_DELIVERY' ? 'MARK_COMPLETED' : 'START_DELIVERY') === 'START_DELIVERY')>Sedang Dikirim ke Lokasi Client</option>
                                    <option value="MARK_COMPLETED" @selected(old('delivery_action', $order['order_status'] === 'IN_DELIVERY' ? 'MARK_COMPLETED' : '') === 'MARK_COMPLETED')>Sudah Diterima Client (Selesai)</option>
                                </select>
                            </x-ui.field>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-3">
                            <x-ui.field label="Jenis Armada Pengantar (Opsional)" name="vehicle_type" :error="$errors->first('vehicle_type')">
                                <x-ui.input name="vehicle_type" :value="old('vehicle_type', $order['vehicle_type'])" placeholder="Truk Engkel / Fuso" />
                            </x-ui.field>
                            <x-ui.field label="Nomor Polisi (Opsional)" name="license_plate" :error="$errors->first('license_plate')">
                                <x-ui.input name="license_plate" :value="old('license_plate', $order['license_plate'])" placeholder="B 1234 PKD" />
                            </x-ui.field>
                            <x-ui.field label="Nama Pengemudi (Opsional)" name="driver_name" :error="$errors->first('driver_name')">
                                <x-ui.input name="driver_name" :value="old('driver_name', $order['driver_name'])" placeholder="Nama pengemudi" />
                            </x-ui.field>
                        </div>

                        <x-ui.field label="Catatan Pengiriman / Penerima di Lokasi Client (Opsional)" name="delivery_notes" :error="$errors->first('delivery_notes')">
                            <x-ui.textarea name="delivery_notes" rows="2" placeholder="Nama penerima di lokasi Client atau nomor berita acara">{{ old('delivery_notes', $order['delivery_notes']) }}</x-ui.textarea>
                        </x-ui.field>

                        <x-ui.button type="submit" variant="success">
                            <x-ui.icon name="truck" />
                            Simpan Status Pengiriman
                        </x-ui.button>
                    </form>
                </x-ui.card>
            @endif

            {{-- RINGKASAN SERAH TERIMA SELESAI --}}
            @if ($isCompleted)
                <x-ui.card title="Ringkasan Penyelesaian Layanan" description="Seluruh tahapan operasional Gudang telah selesai dilaksanakan.">
                    <dl class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Metode Penyelesaian</dt>
                            <dd class="mt-1 font-medium">{{ $order['delivery_method_label'] ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Kuantitas Diselesaikan</dt>
                            <dd class="mt-1 font-semibold tabular-nums">{{ $order['delivered_quantity'] ?? ($order['result']['completed_qty'] ?? '-') }} {{ $order['unit'] }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Waktu Selesai</dt>
                            <dd class="mt-1 tabular-nums">{{ $order['delivered_at'] ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Armada / Pengemudi</dt>
                            <dd class="mt-1">
                                @if (!empty($order['license_plate']) || !empty($order['vehicle_type']) || !empty($order['driver_name']))
                                    {{ trim(($order['vehicle_type'] ?? '').' '.($order['license_plate'] ?? '')) }}
                                    @if (!empty($order['driver_name']))
                                        · {{ $order['driver_name'] }}
                                    @endif
                                @else
                                    -
                                @endif
                            </dd>
                        </div>
                        @if (!empty($order['delivery_notes']))
                            <div class="sm:col-span-2">
                                <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Catatan Penyelesaian</dt>
                                <dd class="mt-1 text-sm">{{ $order['delivery_notes'] }}</dd>
                            </div>
                        @endif
                    </dl>
                </x-ui.card>
            @endif
        </div>

        {{-- SIDEBAR KANAN: 3 JALUR STATUS, RINGKASAN KUANTITAS, RIWAYAT AKTIVITAS --}}
        <div class="space-y-6">
            <x-ui.card title="Status 3 Jalur Terpisah" description="Operasional, penagihan, dan pembayaran dicatat terpisah sesuai PRD 2.1.">
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

            <x-ui.card title="Ringkasan Kuantitas">
                <dl class="space-y-2.5 text-sm">
                    <div class="flex items-center justify-between">
                        <dt class="text-muted-foreground">Estimasi Client</dt>
                        <dd class="font-medium tabular-nums">{{ $order['requested_qty'] }} {{ $order['unit'] }}</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-muted-foreground">Diterima Fisik</dt>
                        <dd class="font-medium tabular-nums">{{ $order['receipt']['actual_received_quantity'] ?? '-' }} {{ !empty($order['receipt']) ? $order['receipt']['unit'] : '' }}</dd>
                    </div>
                    @if ($isReconditioning)
                        <div class="flex items-center justify-between">
                            <dt class="text-muted-foreground">Layak Diproses</dt>
                            <dd class="font-medium tabular-nums">{{ $order['sorting']['processable_qty'] ?? '-' }} {{ !empty($order['sorting']) ? $order['sorting']['unit'] : '' }}</dd>
                        </div>
                    @endif
                    <div class="flex items-center justify-between border-t pt-2.5">
                        <dt class="font-medium">Selesai Layak</dt>
                        <dd class="font-semibold tabular-nums text-success">{{ $order['result']['completed_qty'] ?? '-' }} {{ !empty($order['result']) ? $order['result']['unit'] : '' }}</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-muted-foreground">Tidak Layak / Reject</dt>
                        <dd class="font-medium tabular-nums">{{ $order['result']['rejected_qty'] ?? ($order['sorting']['qty_rejected'] ?? 0) }} {{ $order['unit'] }}</dd>
                    </div>
                    @if (!empty($order['delivered_quantity']))
                        <div class="flex items-center justify-between border-t pt-2.5">
                            <dt class="font-medium">Diselesaikan</dt>
                            <dd class="font-semibold tabular-nums">{{ $order['delivered_quantity'] }} {{ $order['unit'] }}</dd>
                        </div>
                    @endif
                </dl>
            </x-ui.card>

            <x-ui.card title="Riwayat Aktivitas" description="Jejak kronologis perubahan status permintaan.">
                <ol class="space-y-4 text-sm">
                    @foreach ($order['timeline'] as $item)
                        <li class="flex gap-3">
                            <span @class([
                                'mt-1.5 h-2 w-2 shrink-0 rounded-full',
                                'bg-primary' => ($item['tone'] ?? 'default') === 'primary',
                                'bg-success' => ($item['tone'] ?? 'default') === 'success',
                                'bg-danger' => ($item['tone'] ?? 'default') === 'danger',
                                'bg-muted-foreground' => !in_array($item['tone'] ?? 'default', ['primary', 'success', 'danger'], true),
                            ])></span>
                            <div class="min-w-0">
                                <p class="font-medium leading-snug">{{ $item['title'] }}</p>
                                <p class="mt-0.5 text-xs text-muted-foreground leading-relaxed">{{ $item['description'] }}</p>
                                <p class="mt-0.5 text-[11px] text-muted-foreground tabular-nums">{{ $item['time'] }}</p>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </x-ui.card>
        </div>
    </div>

    <x-ui.modal id="modal-wh-clarify" title="Minta Klarifikasi Permintaan" icon="info" tone="warning" size="sm">
        <form action="{{ route('warehouse.orders.review', $orderSlug) }}" method="POST" class="space-y-4" novalidate data-validate-form data-prevent-double-submit>
            @csrf
            <input type="hidden" name="decision" value="NEEDS_CLARIFICATION">
            <x-ui.field label="Alasan & Catatan Klarifikasi" name="decision_reason" required>
                <x-ui.textarea name="decision_reason" rows="3" minlength="5" placeholder="Jelaskan data, jenis kemasan, atau kuantitas yang perlu dikonfirmasi Client" required />
            </x-ui.field>
            <div class="flex justify-end gap-2 border-t pt-4">
                <x-ui.button variant="outline" data-modal-close>Batal</x-ui.button>
                <x-ui.button type="submit">Kirim Permintaan Klarifikasi</x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    <x-ui.modal id="modal-wh-reject" title="Tolak Permintaan" icon="alert-triangle" tone="danger" size="sm">
        <form action="{{ route('warehouse.orders.review', $orderSlug) }}" method="POST" class="space-y-4" novalidate data-validate-form data-prevent-double-submit>
            @csrf
            <input type="hidden" name="decision" value="REJECT">
            <x-ui.field label="Alasan Penolakan" name="decision_reason" required>
                <x-ui.textarea name="decision_reason" rows="3" minlength="5" placeholder="Tuliskan alasan operasional penolakan permintaan" required />
            </x-ui.field>
            <div class="flex justify-end gap-2 border-t pt-4">
                <x-ui.button variant="outline" data-modal-close>Batal</x-ui.button>
                <x-ui.button type="submit" variant="destructive">Konfirmasi Tolak Permintaan</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
</x-layouts.admin>
