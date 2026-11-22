<x-layouts.admin title="Biaya Operasional" group="Kas & Pelaporan" role="keuangan">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <x-ui.page-header title="Pencatatan Biaya Operasional" description="Catat pengeluaran bahan, perlengkapan, gaji karyawan, utilitas, dan biaya pabrik lainnya." />
        <button type="button" class="btn btn-primary w-full sm:w-auto" data-modal-open="#modal-add-expense">
            <x-ui.icon name="plus" />
            Catat Pengeluaran Baru
        </button>
    </div>

    <div class="card" data-table data-page-size="8">
        <div class="flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6">
            <div>
                <h2 class="card-title">Daftar Pengeluaran Operasional</h2>
                <p class="card-desc mt-1">Setiap pengeluaran tercatat otomatis menjadi arus kas keluar di Buku Keuangan.</p>
            </div>
            <input type="search" class="input sm:w-64" placeholder="Cari kategori, uraian, atau vendor..." aria-label="Cari biaya operasional" data-table-search>
        </div>
        <div class="overflow-x-auto border-t">
            <table class="table min-w-[740px]">
                <thead>
                    <tr><th>No. Transaksi</th><th>Tanggal</th><th>Kategori</th><th>Uraian & Vendor</th><th class="text-right">Nominal</th><th>Status</th></tr>
                </thead>
                <tbody>
                    @foreach ($expenses as $exp)
                        <tr>
                            <td class="font-semibold tabular-nums">{{ $exp['number'] }}</td>
                            <td class="text-muted-foreground">{{ $exp['date'] }}</td>
                            <td><span class="badge badge-outline">{{ $exp['category'] }}</span></td>
                            <td>
                                <p class="font-medium">{{ $exp['description'] }}</p>
                                <p class="text-xs text-muted-foreground">{{ $exp['vendor'] }} · {{ $exp['method'] }} · Bukti: {{ $exp['proof'] }}</p>
                            </td>
                            <td class="text-right font-semibold tabular-nums">Rp {{ number_format($exp['amount'], 0, ',', '.') }}</td>
                            <td><x-ui.badge :variant="$exp['tone']">{{ $exp['status'] }}</x-ui.badge></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <x-ui.modal id="modal-add-expense" title="Catat Biaya Operasional Baru">
        <form action="{{ route('finance.expenses.store') }}" method="POST" enctype="multipart/form-data" class="space-y-4" novalidate data-validate-form data-prevent-double-submit>
            @csrf
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.field label="Kategori Biaya" name="expense_category" required>
                    <x-ui.input name="expense_category" minlength="2" placeholder="Pembelian Bahan / Gaji Karyawan / Utilitas" required />
                </x-ui.field>
                <x-ui.field label="Tanggal Pengeluaran" name="expense_date" required>
                    <x-ui.input name="expense_date" value="2026-11-17" data-date-picker required />
                </x-ui.field>
            </div>
            <x-ui.field label="Uraian Pengeluaran" name="description" required>
                <x-ui.textarea name="description" rows="2" minlength="3" placeholder="Jelaskan barang/jasa yang dibayarkan..." required />
            </x-ui.field>
            <div class="grid gap-4 sm:grid-cols-3">
                <x-ui.field label="Nominal (Rp)" name="amount" required>
                    <x-ui.input name="amount" type="number" min="1" placeholder="500000" required />
                </x-ui.field>
                <x-ui.field label="Penerima / Vendor" name="vendor">
                    <x-ui.input name="vendor" placeholder="Nama toko/penerima" />
                </x-ui.field>
                <x-ui.field label="Metode Bayar" name="payment_method" required>
                    <x-ui.input name="payment_method" value="Tunai" required />
                </x-ui.field>
            </div>
            <x-ui.field label="Lampiran Nota / Kuitansi" name="receipt_file">
                <x-ui.file-preview id="expense-receipt" name="receipt_file" label="Unggah foto nota atau PDF kuitansi" />
            </x-ui.field>
            <div class="flex justify-end gap-2 border-t pt-4">
                <x-ui.button variant="outline" data-modal-close>Batal</x-ui.button>
                <x-ui.button type="submit">Simpan Pengeluaran</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
</x-layouts.admin>
