<x-layouts.admin title="Jejak Audit Sistem" group="Pemantauan PKDI" role="owner">
    <x-ui.page-header title="Jejak Audit Aktivitas (Audit Trail)" description="Catatan permanen (append-only) seluruh persetujuan, penahanan, verifikasi pembayaran, dan perubahan status." />

    <div class="card" data-table data-page-size="10">
        <div class="flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6">
            <h2 class="card-title">Log Aktivitas Pengguna</h2>
            <div class="flex flex-col gap-2 sm:flex-row">
                <input type="search" class="input sm:w-60" placeholder="Cari aktor, aksi, atau kode dokumen..." aria-label="Cari audit log" data-table-search>
                <select class="select sm:w-44" aria-label="Filter peran" data-table-filter>
                    <option value="">Semua Peran</option>
                    <option value="Client">Client</option>
                    <option value="Kepala Gudang">Kepala Gudang</option>
                    <option value="Keuangan">Keuangan</option>
                </select>
            </div>
        </div>
        <div class="overflow-x-auto border-t">
            <table class="table min-w-[760px]">
                <thead>
                    <tr><th>Waktu (WIB)</th><th>Pengguna</th><th>Peran</th><th>Tindakan</th><th>Referensi</th><th>Detail & Alasan</th></tr>
                </thead>
                <tbody>
                    @foreach ($logs as $log)
                        <tr data-status="{{ $log['role'] }}">
                            <td class="text-xs text-muted-foreground tabular-nums">{{ $log['time'] }}</td>
                            <td class="font-medium">{{ $log['user'] }}</td>
                            <td><span class="badge badge-soft">{{ $log['role'] }}</span></td>
                            <td class="font-mono text-xs">{{ $log['action'] }}</td>
                            <td class="font-medium tabular-nums">{{ $log['entity'] }}</td>
                            <td class="text-xs">
                                <p>{{ $log['detail'] }}</p>
                                @if ($log['reason'] !== '-')
                                    <p class="mt-0.5 text-danger">Alasan: {{ $log['reason'] }}</p>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div data-table-empty hidden><x-ui.table-state state="filtered" /></div>
        </div>
    </div>
</x-layouts.admin>
