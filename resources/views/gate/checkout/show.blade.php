@php
    $isPaid = $order['payment_status'] === 'PAID';
    $do = $order['delivery_order'];
    $canApproveExit = $isPaid && !empty($do) && ($do['status'] ?? '') === 'ISSUED';
    $orderSlug = \App\Support\PkdiDemoData::orderSlug($order['order_number']);
@endphp
<x-layouts.admin :title="'Periksa Keluar '.$order['order_number']" group="Pos Gerbang" role="satpam">
    <div>
        <a href="{{ route('gate.checkout.index') }}" class="mb-2 inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground">
            <x-ui.icon name="arrow-left" class="h-4 w-4" />
            Kembali ke Antrean Keluar
        </a>
        <div class="flex flex-wrap items-center gap-3">
            <h1 class="text-2xl font-bold tracking-tight tabular-nums">{{ $do['do_number'] ?? 'Surat Jalan Belum Tersedia' }}</h1>
            <x-ui.badge :variant="$canApproveExit ? 'success' : 'danger'" :dot="true">
                {{ $canApproveExit ? 'Syarat Terpenuhi (Siap Keluar)' : 'Diblokir Keluar' }}
            </x-ui.badge>
        </div>
        <p class="mt-1 text-sm text-muted-foreground">Pesanan {{ $order['order_number'] }} · {{ $order['client_company'] }}</p>
    </div>

    @if (! $isPaid)
        <x-ui.alert variant="danger" title="Kendaraan Dilarang Keluar: Tagihan Belum Lunas">
            Status pembayaran masih <strong>{{ $order['payment_status'] }}</strong>. Sistem memblokir tombol Setujui Keluar sampai divisi Keuangan memverifikasi pelunasan penuh dan Kepala Gudang menerbitkan Surat Jalan.
        </x-ui.alert>
    @elseif (empty($do))
        <x-ui.alert variant="warning" title="Surat Jalan Belum Diterbitkan Kepala Gudang">
            Tagihan sudah berstatus Lunas, namun Kepala Gudang belum menerbitkan Surat Jalan resmi. Kendaraan belum dapat disetujui keluar.
        </x-ui.alert>
    @elseif (($do['status'] ?? '') === 'USED_AT_GATE_OUT')
        <x-ui.alert variant="info" title="Kendaraan Sudah Keluar">
            Surat Jalan <strong>{{ $do['do_number'] }}</strong> telah diverifikasi keluar dan pesanan berstatus Selesai.
        </x-ui.alert>
    @else
        <x-ui.alert variant="success" title="Syarat Administrasi Terpenuhi">
            Pembayaran sudah <strong>Lunas</strong> dan Surat Jalan <strong>{{ $do['do_number'] }}</strong> berstatus berlaku (belum pernah digunakan). Cocokkan fisik muatan dan kendaraan di bawah.
        </x-ui.alert>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        <x-ui.detail-list title="Rincian Surat Jalan dan Muatan Keluar" :items="[
            ['label' => 'Nomor Surat Jalan', 'value' => $do['do_number'] ?? 'Belum diterbitkan'],
            ['label' => 'Status Tagihan', 'value' => $isPaid ? 'Lunas (Terverifikasi Keuangan)' : 'Belum Lunas'],
            ['label' => 'Nomor Polisi Keluar', 'value' => $do['license_plate'] ?? $order['license_plate']],
            ['label' => 'Jenis Kendaraan', 'value' => $do['vehicle_type'] ?? $order['vehicle_type']],
            ['label' => 'Nama Driver', 'value' => $do['driver_name'] ?? ($order['driver_name'] ?? '-')],
            ['label' => 'Rincian Muatan Keluar', 'value' => $do['cargo_summary'] ?? ($order['requested_qty'].' '.$order['unit'])],
            ['label' => 'Diterbitkan Oleh', 'value' => $do['issued_by'] ?? '-'],
        ]" />

        <x-ui.card title="Keputusan Pemeriksaan Gerbang Keluar">
            <form action="{{ route('gate.checkout.store', $orderSlug) }}" method="POST" class="space-y-4" novalidate data-validate-form data-prevent-double-submit>
                @csrf
                <input type="hidden" name="decision" value="APPROVED">
                <input type="hidden" name="delivery_order_number" value="{{ $do['do_number'] ?? '' }}">
                <input type="hidden" name="actual_license_plate" value="{{ $do['license_plate'] ?? $order['license_plate'] }}">
                <input type="hidden" name="actual_vehicle_type" value="{{ $do['vehicle_type'] ?? $order['vehicle_type'] }}">
                <input type="hidden" name="actual_driver_name" value="{{ $do['driver_name'] ?? ($order['driver_name'] ?? 'Pengemudi') }}">

                <x-ui.field label="Catatan Pemeriksaan Keluar (Opsional)" name="exit_notes">
                    <x-ui.textarea name="exit_notes" rows="3" placeholder="Contoh: Jumlah fisik drum dan nopol sesuai Surat Jalan." :disabled="! $canApproveExit" />
                </x-ui.field>

                <div class="grid gap-3 pt-2 sm:grid-cols-2">
                    <x-ui.button type="button" variant="destructive" size="lg" class="w-full" data-modal-open="#modal-hold-gate-out">
                        <x-ui.icon name="ban" />
                        Tahan Keluar
                    </x-ui.button>
                    <x-ui.button type="submit" variant="success" size="lg" class="w-full" :disabled="! $canApproveExit">
                        <x-ui.icon name="check-circle" />
                        Setujui Keluar
                    </x-ui.button>
                </div>
            </form>
        </x-ui.card>
    </div>

    <x-ui.modal id="modal-hold-gate-out" title="Tahan Kendaraan di Gerbang Keluar" icon="alert-triangle" tone="danger" size="sm">
        <form action="{{ route('gate.checkout.store', $orderSlug) }}" method="POST" class="space-y-4" novalidate data-validate-form data-prevent-double-submit>
            @csrf
            <input type="hidden" name="decision" value="HELD">
            <x-ui.field label="Alasan Penahanan Keluar" name="hold_out_reason" required>
                <x-ui.textarea name="hold_out_reason" rows="3" minlength="5" placeholder="Tuliskan ketidaksesuaian yang ditemukan di gerbang..." required />
            </x-ui.field>
            <div class="flex flex-wrap justify-end gap-2 border-t pt-4">
                <x-ui.button variant="outline" data-modal-close>Batal</x-ui.button>
                <x-ui.button type="submit" variant="destructive">Simpan Penahanan Keluar</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
</x-layouts.admin>
