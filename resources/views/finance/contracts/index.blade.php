<x-layouts.admin title="Tarif Kontrak Client" group="Tagihan & Pembayaran" role="keuangan">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <x-ui.page-header
            title="Manajemen Tarif Kontrak per Client"
            description="Simpan harga satuan khusus kontrak per perusahaan Client untuk Laundry, Cat, Sablon, Pengangkutan Limbah, dan biaya pemusnahan reject beserta tarif PPN & PPh 23." />
        <button type="button" class="btn btn-primary w-full sm:w-auto" data-modal-open="#modal-add-contract">
            <x-ui.icon name="plus" />
            Tambah Tarif Kontrak Client
        </button>
    </div>

    <div class="card">
        <div class="overflow-x-auto">
            <table class="table min-w-[820px]">
                <thead>
                    <tr>
                        <th>Perusahaan Client</th>
                        <th>Layanan & Kemasan</th>
                        <th class="text-right">Harga Kontrak / Unit</th>
                        <th class="text-right">Biaya Reject</th>
                        <th class="text-right">PPN / PPh 23</th>
                        <th>Status</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($contracts as $c)
                        <tr>
                            <td class="font-medium">{{ $c->clientCompany?->company_name ?? '-' }}</td>
                            <td>
                                <p class="font-medium">{{ $c->service_type }}</p>
                                <p class="text-xs text-muted-foreground">{{ $c->drumProduct?->name ?? 'Semua Jenis Kemasan' }}</p>
                            </td>
                            <td class="text-right font-semibold tabular-nums whitespace-nowrap">Rp {{ number_format((float) $c->unit_price, 0, ',', '.') }}</td>
                            <td class="text-right tabular-nums whitespace-nowrap">Rp {{ number_format((float) $c->reject_disposal_fee, 0, ',', '.') }}</td>
                            <td class="text-right tabular-nums whitespace-nowrap text-xs">
                                PPN {{ (float) $c->ppn_rate }}% · PPh 23 {{ (float) $c->pph23_rate }}%
                            </td>
                            <td>
                                <x-ui.badge :variant="$c->is_active ? 'success' : 'neutral'">{{ $c->is_active ? 'Aktif' : 'Nonaktif' }}</x-ui.badge>
                            </td>
                            <td class="text-right whitespace-nowrap">
                                <form action="{{ route('finance.contracts.destroy', $c->id) }}" method="POST" class="inline" data-prevent-double-submit>
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-outline btn-sm text-danger hover:border-danger/40 hover:bg-danger-soft">
                                        <x-ui.icon name="trash" class="h-3.5 w-3.5" />
                                        Hapus
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-10 text-center text-muted-foreground">
                                Belum ada tarif kontrak khusus per Client. Sistem akan menggunakan Referensi Tarif Jasa standar bila tarif kontrak belum diatur.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <x-ui.modal id="modal-add-contract" title="Tambah Tarif Kontrak Khusus Client">
        <form action="{{ route('finance.contracts.store') }}" method="POST" class="space-y-4" novalidate data-validate-form data-prevent-double-submit>
            @csrf
            <x-ui.field label="Perusahaan Client" name="client_company_id" required>
                <select id="f-client_company_id" name="client_company_id" class="select" required>
                    <option value="">Pilih perusahaan Client...</option>
                    @foreach ($clients as $cl)
                        <option value="{{ $cl->id }}">{{ $cl->company_name }}</option>
                    @endforeach
                </select>
            </x-ui.field>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.field label="Jenis Layanan" name="service_type" required>
                    <select id="f-service_type" name="service_type" class="select" required>
                        <option value="Laundry">Laundry</option>
                        <option value="Laundry + Cat">Laundry + Cat</option>
                        <option value="Laundry + Cat + Sablon">Laundry + Cat + Sablon</option>
                        <option value="Pengangkutan Limbah">Pengangkutan Limbah</option>
                    </select>
                </x-ui.field>
                <x-ui.field label="Jenis Drum / Kemasan (Opsional)" name="drum_product_id">
                    <select id="f-drum_product_id" name="drum_product_id" class="select">
                        <option value="">Berlaku untuk semua kemasan</option>
                        @foreach ($drumProducts as $dp)
                            <option value="{{ $dp->id }}">{{ $dp->name }}</option>
                        @endforeach
                    </select>
                </x-ui.field>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.field label="Harga Kontrak per Unit (Rp)" name="unit_price" required>
                    <x-ui.input name="unit_price" type="number" min="1" placeholder="75000" required />
                </x-ui.field>
                <x-ui.field label="Biaya Pemusnahan Reject / Unit (Rp)" name="reject_disposal_fee">
                    <x-ui.input name="reject_disposal_fee" type="number" min="0" value="0" />
                </x-ui.field>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.field label="Tarif PPN (%)" name="ppn_rate">
                    <x-ui.input name="ppn_rate" type="number" step="0.01" min="0" max="100" value="11" />
                </x-ui.field>
                <x-ui.field label="Tarif Potongan PPh 23 (%)" name="pph23_rate">
                    <x-ui.input name="pph23_rate" type="number" step="0.01" min="0" max="100" value="2" />
                </x-ui.field>
            </div>
            <x-ui.field label="Catatan Kontrak (Opsional)" name="notes">
                <x-ui.input name="notes" placeholder="Nomor kontrak / periode berlaku" />
            </x-ui.field>
            <div class="flex justify-end gap-2 border-t pt-4">
                <x-ui.button variant="outline" data-modal-close>Batal</x-ui.button>
                <x-ui.button type="submit">Simpan Tarif Kontrak</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
</x-layouts.admin>
