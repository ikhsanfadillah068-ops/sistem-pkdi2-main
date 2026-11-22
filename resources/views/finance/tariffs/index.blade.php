<x-layouts.admin title="Referensi Tarif Jasa" group="Keuangan" role="keuangan">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <x-ui.page-header title="Referensi Tarif Jasa PKDI" description="Acuan tarif satuan jasa untuk mempermudah perhitungan tagihan oleh Keuangan." />
        <button type="button" class="btn btn-primary w-full sm:w-auto" data-modal-open="#modal-add-tariff">
            <x-ui.icon name="plus" />
            Tambah Tarif Baru
        </button>
    </div>

    <div class="card">
        <div class="overflow-x-auto">
            <table class="table min-w-[740px]">
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Nama Tarif</th>
                        <th>Kategori / Muatan / Jasa</th>
                        <th>Satuan</th>
                        <th class="text-right">Harga Satuan</th>
                        <th>Berlaku Mulai</th>
                        <th>Status</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($tariffs as $t)
                        <tr>
                            <td class="font-semibold tabular-nums whitespace-nowrap">{{ $t['code'] }}</td>
                            <td class="font-medium">{{ $t['name'] }}</td>
                            <td class="text-xs text-muted-foreground">{{ $t['service_category'] }} · {{ $t['service_type'] }}</td>
                            <td>{{ $t['unit'] }}</td>
                            <td class="text-right font-medium tabular-nums whitespace-nowrap">Rp {{ number_format($t['price'], 0, ',', '.') }}</td>
                            <td class="text-muted-foreground whitespace-nowrap">{{ $t['effective_from'] }}</td>
                            <td><x-ui.badge variant="success">Aktif</x-ui.badge></td>
                            <td class="text-right whitespace-nowrap">
                                <div class="inline-flex items-center justify-end gap-1.5">
                                    <button type="button"
                                        class="btn btn-outline btn-icon btn-sm"
                                        title="Edit Tarif {{ $t['name'] }}"
                                        aria-label="Edit Tarif {{ $t['name'] }}"
                                        data-modal-open="#modal-edit-tariff-{{ $t['id'] }}">
                                        <x-ui.icon name="edit" class="h-4 w-4" />
                                    </button>
                                    <button type="button"
                                        class="btn btn-outline btn-icon btn-sm text-danger hover:border-danger/40 hover:bg-danger-soft"
                                        title="Hapus Tarif {{ $t['name'] }}"
                                        aria-label="Hapus Tarif {{ $t['name'] }}"
                                        data-modal-open="#modal-delete-tariff-{{ $t['id'] }}">
                                        <x-ui.icon name="trash" class="h-4 w-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-8 text-center text-muted-foreground">Belum ada referensi tarif jasa. Klik Tambah Tarif Baru untuk menambahkan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @foreach ($tariffs as $t)
        <x-ui.modal :id="'modal-edit-tariff-'.$t['id']" :title="'Edit Tarif '.$t['code']">
            <form action="{{ route('finance.tariffs.update', $t['id']) }}" method="POST" class="space-y-4" novalidate data-validate-form data-prevent-double-submit>
                @csrf
                @method('PUT')
                <x-ui.field label="Nama Tarif" name="name" required>
                    <x-ui.input name="name" minlength="3" :value="$t['name']" placeholder="Pembaruan Drum Besi 200L" required />
                </x-ui.field>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.field label="Kategori Layanan" name="service_category">
                        <x-ui.input name="service_category" :value="$t['raw_service_category']" placeholder="Ketik kategori drum..." />
                    </x-ui.field>
                    <x-ui.field label="Jenis Jasa" name="service_type" required>
                        <x-ui.input name="service_type" :value="$t['service_type']" placeholder="Diperbarui / Diperbaiki" required />
                    </x-ui.field>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.field label="Satuan" name="unit" required>
                        <x-ui.input name="unit" :value="$t['unit']" required />
                    </x-ui.field>
                    <x-ui.field label="Harga Satuan (Rp)" name="unit_price" required>
                        <x-ui.input name="unit_price" type="number" min="1" :value="(int) $t['price']" required />
                    </x-ui.field>
                </div>
                <div class="flex justify-end gap-2 border-t pt-4">
                    <x-ui.button variant="outline" data-modal-close>Batal</x-ui.button>
                    <x-ui.button type="submit">Simpan Perubahan</x-ui.button>
                </div>
            </form>
        </x-ui.modal>

        <x-ui.modal :id="'modal-delete-tariff-'.$t['id']" title="Hapus Referensi Tarif" icon="trash" tone="danger" size="sm">
            <form action="{{ route('finance.tariffs.destroy', $t['id']) }}" method="POST" class="space-y-4" data-prevent-double-submit>
                @csrf
                @method('DELETE')
                <p class="text-sm text-muted-foreground">
                    Apakah Anda yakin ingin menghapus tarif <strong class="text-foreground">{{ $t['code'] }} ({{ $t['name'] }})</strong>?
                </p>
                <div class="flex justify-end gap-2 border-t pt-4">
                    <x-ui.button variant="outline" data-modal-close>Batal</x-ui.button>
                    <x-ui.button type="submit" variant="destructive">Ya, Hapus</x-ui.button>
                </div>
            </form>
        </x-ui.modal>
    @endforeach

    <x-ui.modal id="modal-add-tariff" title="Tambah Referensi Tarif Jasa">
        <form action="{{ route('finance.tariffs.store') }}" method="POST" class="space-y-4" novalidate data-validate-form data-prevent-double-submit>
            @csrf
            <x-ui.field label="Nama Tarif" name="name" required>
                <x-ui.input name="name" minlength="3" placeholder="Pembaruan Drum Besi 200L" required />
            </x-ui.field>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.field label="Kategori Layanan" name="service_category">
                    <x-ui.input name="service_category" placeholder="Ketik kategori drum..." />
                </x-ui.field>
                <x-ui.field label="Jenis Jasa" name="service_type" required>
                    <x-ui.input name="service_type" placeholder="Diperbarui / Diperbaiki" required />
                </x-ui.field>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.field label="Satuan" name="unit" required>
                    <x-ui.input name="unit" value="Drum" required />
                </x-ui.field>
                <x-ui.field label="Harga Satuan (Rp)" name="unit_price" required>
                    <x-ui.input name="unit_price" type="number" min="1" placeholder="85000" required />
                </x-ui.field>
            </div>
            <div class="flex justify-end gap-2 border-t pt-4">
                <x-ui.button variant="outline" data-modal-close>Batal</x-ui.button>
                <x-ui.button type="submit">Simpan Tarif</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
</x-layouts.admin>
