<x-layouts.admin title="Antrean Pos Gerbang" group="Pos Gerbang" role="satpam">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <x-ui.page-header title="Pos Gerbang Kendaraan" description="Cari nomor polisi atau nomor pesanan untuk memeriksa kendaraan masuk dan keluar area pabrik." />
        <a href="{{ route('gate.history') }}" class="btn btn-outline w-full sm:w-auto">
            <x-ui.icon name="history" />
            Riwayat Masuk / Keluar
        </a>
    </div>

    <div class="card p-4 sm:p-5" data-table data-page-size="10">
        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="relative flex-1">
                <label class="sr-only" for="gate-quick-search">Cari kendaraan atau nomor pesanan</label>
                <x-ui.icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                <input id="gate-quick-search" type="search" class="input min-h-11 pl-9 text-base" placeholder="Ketik Nomor Polisi (contoh: B 9412 KQA), No. Pesanan, atau Nama PT..." data-table-search>
            </div>
            <div class="flex flex-wrap gap-2 text-xs">
                <span class="badge badge-warning">Menunggu Masuk: {{ count($waitingIn) }}</span>
                <span class="badge badge-info">Di Area Pabrik: {{ count($inFactory) }}</span>
                <span class="badge badge-success">Antrean Keluar: {{ count($waitingOut) }}</span>
            </div>
        </div>

        <x-ui.tabs id="gate-tabs" variant="pill" aria-label="Antrean kendaraan pos gerbang" :tabs="[
            'in' => 'Menunggu Masuk ('.count($waitingIn).')',
            'inside' => 'Di Area Pabrik ('.count($inFactory).')',
            'out' => 'Pemeriksaan Keluar ('.count($waitingOut).')',
        ]">
            <x-slot:tab_in>
                <div class="grid gap-3 md:grid-cols-2">
                    @forelse ($waitingIn as $order)
                        @php($st = \App\Support\PkdiDemoData::statusMeta($order['order_status']))
                        <div class="rounded-xl border bg-background p-4 space-y-3">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <p class="text-lg font-bold tracking-tight tabular-nums">{{ $order['license_plate'] }}</p>
                                    <p class="text-xs text-muted-foreground">{{ $order['vehicle_type'] }} · {{ $order['order_number'] }}</p>
                                </div>
                                <x-ui.badge :variant="$st['tone']" :dot="true">{{ $st['label'] }}</x-ui.badge>
                            </div>
                            <div class="text-sm space-y-1">
                                <p><span class="text-muted-foreground">Perusahaan:</span> <strong>{{ $order['client_company'] }}</strong></p>
                                <p><span class="text-muted-foreground">Driver pengajuan:</span> {{ $order['driver_name'] ?? 'Kosong (catat aktual di pos)' }}</p>
                            </div>
                            <a href="{{ route('gate.checkin.show', \App\Support\PkdiDemoData::orderSlug($order['order_number'])) }}" class="btn btn-primary w-full btn-lg">
                                <x-ui.icon name="clipboard-check" />
                                Periksa Kendaraan Masuk
                            </a>
                        </div>
                    @empty
                        <div class="md:col-span-2"><x-ui.empty title="Tidak ada kendaraan menunggu masuk" description="Seluruh kendaraan yang dijadwalkan sudah diperiksa." /></div>
                    @endforelse
                </div>
            </x-slot:tab_in>

            <x-slot:tab_inside>
                <div class="overflow-x-auto rounded-lg border">
                    <table class="table min-w-[640px]">
                        <thead>
                            <tr>
                                <th>No. Polisi</th>
                                <th>No. Pesanan & PT</th>
                                <th>Waktu Masuk</th>
                                <th>Status Proses</th>
                                <th class="text-right">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($inFactory as $order)
                                @php($st = \App\Support\PkdiDemoData::statusMeta($order['order_status']))
                                <tr>
                                    <td class="font-bold tabular-nums whitespace-nowrap">{{ $order['license_plate'] }}</td>
                                    <td>
                                        <p class="font-medium tabular-nums whitespace-nowrap">{{ $order['order_number'] }}</p>
                                        <p class="text-xs text-muted-foreground">{{ $order['client_company'] }}</p>
                                    </td>
                                    <td class="text-muted-foreground tabular-nums">{{ $order['gate_in']['time'] ?? '-' }}</td>
                                    <td><x-ui.badge :variant="$st['tone']" :dot="true">{{ $st['label'] }}</x-ui.badge></td>
                                    <td class="text-right">
                                        @if ($order['order_status'] === 'WAITING_GATE_OUT')
                                            <a href="{{ route('gate.checkout.show', \App\Support\PkdiDemoData::orderSlug($order['order_number'])) }}" class="btn btn-primary btn-sm">Periksa Keluar</a>
                                        @else
                                            <span class="text-xs text-muted-foreground">Masih di gudang / administrasi</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-8 text-center text-muted-foreground">Tidak ada kendaraan di area pabrik saat ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-slot:tab_inside>

            <x-slot:tab_out>
                <div class="grid gap-3 md:grid-cols-2">
                    @forelse ($waitingOut as $order)
                        @php($hasDo = !empty($order['delivery_order']) && $order['delivery_order']['status'] === 'ISSUED')
                        @php($isPaid = $order['payment_status'] === 'PAID')
                        <div class="rounded-xl border bg-background p-4 space-y-3">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <p class="text-lg font-bold tracking-tight tabular-nums">{{ $order['delivery_order']['license_plate'] ?? $order['license_plate'] }}</p>
                                    <p class="text-xs text-muted-foreground">{{ $order['delivery_order']['do_number'] ?? 'Surat Jalan Belum Terbit' }} · {{ $order['order_number'] }}</p>
                                </div>
                                <x-ui.badge :variant="($hasDo && $isPaid) ? 'success' : 'warning'" :dot="true">
                                    {{ ($hasDo && $isPaid) ? 'Siap Verifikasi Keluar' : 'Menunggu Syarat' }}
                                </x-ui.badge>
                            </div>
                            <div class="text-sm space-y-1">
                                <p><span class="text-muted-foreground">Perusahaan:</span> <strong>{{ $order['client_company'] }}</strong></p>
                                <p><span class="text-muted-foreground">Pembayaran:</span> <strong>{{ $isPaid ? 'Lunas (Terverifikasi)' : 'Belum Lunas' }}</strong></p>
                            </div>
                            <a href="{{ route('gate.checkout.show', \App\Support\PkdiDemoData::orderSlug($order['order_number'])) }}" class="btn {{ ($hasDo && $isPaid) ? 'btn-success' : 'btn-outline' }} w-full btn-lg">
                                <x-ui.icon name="logout" />
                                Buka Pemeriksaan Keluar
                            </a>
                        </div>
                    @empty
                        <div class="md:col-span-2"><x-ui.empty title="Tidak ada kendaraan menunggu keluar" description="Kendaraan yang siap keluar akan tampil di sini." /></div>
                    @endforelse
                </div>
            </x-slot:tab_out>
        </x-ui.tabs>
    </div>
</x-layouts.admin>
