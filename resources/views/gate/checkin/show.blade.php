@php
    $st = \App\Support\PkdiDemoData::statusMeta($order['order_status']);
    $orderSlug = \App\Support\PkdiDemoData::orderSlug($order['order_number']);
    $canCheckIn = in_array($order['order_status'], ['SUBMITTED', 'WAITING_GATE_IN', 'GATE_IN_HELD'], true)
        && in_array($order['gate_status'], ['EXPECTED_IN', 'CHECKED_IN_HELD'], true);
@endphp
<x-layouts.admin :title="'Periksa Masuk '.$order['license_plate']" group="Pos Gerbang" role="satpam">
    <div>
        <a href="{{ route('gate.dashboard') }}" class="mb-2 inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground">
            <x-ui.icon name="arrow-left" class="h-4 w-4" />
            Kembali ke Antrean Pos Gerbang
        </a>
        <div class="flex flex-wrap items-center gap-3">
            <h1 class="text-2xl font-bold tracking-tight tabular-nums">{{ $order['license_plate'] }}</h1>
            <x-ui.badge :variant="$st['tone']" :dot="true">{{ $st['label'] }}</x-ui.badge>
        </div>
        <p class="mt-1 text-sm text-muted-foreground">Pesanan {{ $order['order_number'] }} · {{ $order['client_company'] }}</p>
    </div>

    @if (!empty($order['gate_in']) && $order['gate_in']['decision'] === 'HELD')
        <x-ui.alert variant="danger" title="Penahanan Sebelumnya Tercatat">
            {{ $order['gate_in']['reason'] }} (Petugas: {{ $order['gate_in']['officer'] }}, {{ $order['gate_in']['time'] }}).
        </x-ui.alert>
    @endif

    @if (!$canCheckIn)
        <x-ui.alert variant="success" title="Pemeriksaan Masuk Selesai">
            Kendaraan untuk pesanan ini telah disetujui masuk ({{ $order['gate_in']['time'] ?? '-' }} oleh {{ $order['gate_in']['officer'] ?? 'Satpam' }}) dan sedang berada pada tahapan {{ $st['label'] }}.
        </x-ui.alert>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        <x-ui.detail-list title="Data Pesanan Client" :items="[
            ['label' => 'Nomor Pesanan', 'value' => $order['order_number']],
            ['label' => 'Perusahaan / PT Asal', 'value' => $order['origin_company']],
            ['label' => 'Nomor Polisi Terdaftar', 'value' => $order['license_plate']],
            ['label' => 'Jenis Kendaraan', 'value' => $order['vehicle_type']],
            ['label' => 'Nama Driver Pengajuan', 'value' => $order['driver_name'] ?? '-'],
            ['label' => 'Estimasi Muatan', 'value' => $order['requested_qty'].' '.$order['unit'].' ('.$order['service_type'].')'],
        ]" />

        <x-ui.card title="Pemeriksaan Masuk">
            <form action="{{ route('gate.checkin.store', $orderSlug) }}" method="POST" class="space-y-4" novalidate data-validate-form data-prevent-double-submit>
                @csrf
                <input type="hidden" name="decision" value="APPROVED">

                <x-ui.field label="Nomor Polisi Aktual di Gerbang" name="actual_license_plate" :error="$errors->first('actual_license_plate')" required>
                    <x-ui.input name="actual_license_plate" :value="old('actual_license_plate', $order['gate_in']['actual_plate'] ?? $order['license_plate'])" :disabled="!$canCheckIn" required />
                </x-ui.field>

                <x-ui.field label="Jenis Kendaraan Aktual" name="actual_vehicle_type" :error="$errors->first('actual_vehicle_type')" required>
                    <x-ui.input name="actual_vehicle_type" :value="old('actual_vehicle_type', $order['vehicle_type'])" :disabled="!$canCheckIn" required />
                </x-ui.field>

                <x-ui.field label="Nama Driver Aktual" name="actual_driver_name" :error="$errors->first('actual_driver_name')" required>
                    <x-ui.input name="actual_driver_name" :value="old('actual_driver_name', $order['gate_in']['actual_driver'] ?? $order['driver_name'])" placeholder="Nama driver di gerbang" :disabled="!$canCheckIn" required />
                </x-ui.field>

                <div class="space-y-2 rounded-lg border p-3.5 text-sm">
                    <p class="font-medium">Daftar Periksa Kesesuaian</p>
                    <input type="hidden" name="is_plate_matched" value="0">
                    <label class="flex items-center gap-2.5">
                        <input type="checkbox" name="is_plate_matched" value="1" @checked(old('is_plate_matched', '1') == '1') @disabled(!$canCheckIn)>
                        <span>Nomor polisi cocok dengan data pesanan</span>
                    </label>
                    <input type="hidden" name="is_vehicle_matched" value="0">
                    <label class="flex items-center gap-2.5">
                        <input type="checkbox" name="is_vehicle_matched" value="1" @checked(old('is_vehicle_matched', '1') == '1') @disabled(!$canCheckIn)>
                        <span>Jenis kendaraan dan identitas driver valid</span>
                    </label>
                </div>

                <x-ui.field label="Catatan Pemeriksaan / Alasan Verifikasi (Wajib bila ada perbedaan data)" name="hold_or_note_reason" :error="$errors->first('hold_or_note_reason')">
                    <x-ui.input name="hold_or_note_reason" :value="old('hold_or_note_reason')" placeholder="Opsional jika cocok; wajib diisi jika ada perubahan nopol/armada yang terverifikasi" :disabled="!$canCheckIn" />
                </x-ui.field>

                <div class="grid gap-3 pt-2 sm:grid-cols-2">
                    <x-ui.button type="button" variant="destructive" size="lg" class="w-full" data-modal-open="#modal-hold-gate-in" :disabled="!$canCheckIn">
                        <x-ui.icon name="ban" />
                        Tahan / Tolak Masuk
                    </x-ui.button>
                    <x-ui.button type="submit" variant="success" size="lg" class="w-full" :disabled="!$canCheckIn">
                        <x-ui.icon name="check-circle" />
                        Setujui Masuk
                    </x-ui.button>
                </div>
            </form>
        </x-ui.card>
    </div>

    <x-ui.modal id="modal-hold-gate-in" title="Tahan / Tolak Kendaraan Masuk" icon="alert-triangle" tone="danger" size="sm">
        <form action="{{ route('gate.checkin.store', $orderSlug) }}" method="POST" class="space-y-4" novalidate data-validate-form data-prevent-double-submit>
            @csrf
            <input type="hidden" name="decision" value="HELD">
            <x-ui.field label="Nomor Polisi Fisik di Gerbang" name="actual_license_plate" required>
                <x-ui.input name="actual_license_plate" :value="old('actual_license_plate', $order['gate_in']['actual_plate'] ?? $order['license_plate'])" required />
            </x-ui.field>
            <x-ui.field label="Nama Driver di Gerbang" name="actual_driver_name" required>
                <x-ui.input name="actual_driver_name" :value="old('actual_driver_name', $order['gate_in']['actual_driver'] ?? ($order['driver_name'] ?? 'Pengemudi di Pos'))" required />
            </x-ui.field>
            <x-ui.field label="Alasan Penahanan Kendaraan" name="hold_or_note_reason" required>
                <x-ui.textarea name="hold_or_note_reason" rows="3" minlength="5" placeholder="Contoh: Nomor polisi fisik berbeda dengan nomor pesanan..." required />
            </x-ui.field>
            <div class="flex flex-wrap justify-end gap-2 border-t pt-4">
                <x-ui.button variant="outline" data-modal-close>Batal</x-ui.button>
                <x-ui.button type="submit" variant="destructive">Simpan Penahanan Masuk</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
</x-layouts.admin>
