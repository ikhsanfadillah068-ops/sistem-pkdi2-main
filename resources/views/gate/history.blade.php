<x-layouts.admin title="Riwayat Gerbang" group="Pos Gerbang" role="satpam">
    <x-ui.page-header title="Riwayat Kendaraan Masuk dan Keluar" description="Log pemeriksaan kendaraan beserta waktu WIB dan identitas petugas Satpam." />

    <x-ui.filter-bar :action="route('gate.history')" placeholder="Cari nopol, nomor pesanan, atau nama petugas..." :resetUrl="route('gate.history')">
        <div class="min-w-44 space-y-1.5">
            <label class="label" for="gate-type">Jenis Gerbang</label>
            <x-ui.select id="gate-type" name="type" placeholder="Semua" :options="['Masuk' => 'Masuk', 'Keluar' => 'Keluar']" :value="request('type')" />
        </div>
    </x-ui.filter-bar>

    <div class="card" data-table data-page-size="8">
        <div class="overflow-x-auto">
            <table class="table min-w-[720px]">
                <thead>
                    <tr>
                        <th>Waktu (WIB)</th>
                        <th>Jenis</th>
                        <th>No. Polisi</th>
                        <th>No. Pesanan & PT</th>
                        <th>Keputusan</th>
                        <th>Petugas Satpam</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($logs as $log)
                        <tr>
                            <td class="text-muted-foreground tabular-nums">{{ $log['time'] }}</td>
                            <td><span class="badge badge-outline">{{ $log['type'] }}</span></td>
                            <td class="font-semibold tabular-nums">{{ $log['plate'] }}</td>
                            <td>
                                <p class="font-medium tabular-nums whitespace-nowrap">{{ $log['order_number'] }}</p>
                                <p class="text-xs text-muted-foreground">{{ $log['company'] }}</p>
                            </td>
                            <td>
                                <x-ui.badge :variant="$log['tone']" :dot="true">{{ $log['decision'] }}</x-ui.badge>
                                <p class="mt-1 text-xs text-muted-foreground">{{ $log['note'] }}</p>
                            </td>
                            <td>{{ $log['officer'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.admin>
