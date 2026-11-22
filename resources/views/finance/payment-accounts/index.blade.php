@php
    $paymentMethodGroups = $paymentMethodGroups ?? config('pkdi.payment_methods', []);
@endphp
<x-layouts.admin title="Rekening Pembayaran" group="Keuangan" role="keuangan">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <x-ui.page-header
            title="Rekening & Kanal Pembayaran Perusahaan"
            description="Kelola nomor Virtual Account, E-Wallet, QRIS, dan rekening resmi PT PKDI yang ditampilkan kepada Client pada halaman tagihan dan PDF invoice." />
        <button type="button" class="btn btn-primary w-full sm:w-auto" data-modal-open="#modal-add-payment-account">
            <x-ui.icon name="plus" />
            Tambah Rekening Pembayaran
        </button>
    </div>

    <x-ui.alert variant="info" title="Tampil Otomatis di Tagihan Client & PDF Invoice">
        Semua rekening berstatus <strong>Aktif</strong> akan otomatis ditampilkan sebagai tujuan pembayaran pada halaman tagihan Portal Client, detail invoice Keuangan, dan dokumen PDF Invoice.
    </x-ui.alert>

    <div class="card">
        <div class="overflow-x-auto">
            <table class="table min-w-[780px]">
                <thead>
                    <tr>
                        <th>Jenis Pembayaran</th>
                        <th>Bank / Penyedia</th>
                        <th>Nomor Rekening / VA / Akun</th>
                        <th>Atas Nama</th>
                        <th>Status</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($accounts as $acc)
                        <tr>
                            <td>
                                <p class="font-semibold text-foreground">{{ $acc->payment_method }}</p>
                                <p class="text-xs text-muted-foreground">{{ $acc->category }}</p>
                            </td>
                            <td class="font-medium">{{ $acc->provider_name }}</td>
                            <td>
                                <div class="flex items-center gap-2">
                                    <span class="font-mono font-semibold tabular-nums text-foreground">{{ $acc->account_number }}</span>
                                    <button type="button"
                                        class="btn btn-outline btn-icon btn-sm"
                                        title="Salin nomor {{ $acc->account_number }}"
                                        aria-label="Salin nomor {{ $acc->account_number }}"
                                        data-copy="{{ $acc->account_number }}">
                                        <x-ui.icon name="copy" class="h-3.5 w-3.5" />
                                    </button>
                                </div>
                                @if (!empty($acc->instructions))
                                    <p class="mt-1 max-w-xs text-xs text-muted-foreground">{{ $acc->instructions }}</p>
                                @endif
                            </td>
                            <td class="font-medium">{{ $acc->account_holder }}</td>
                            <td>
                                @if ($acc->is_active)
                                    <x-ui.badge variant="success" :dot="true">Aktif</x-ui.badge>
                                @else
                                    <x-ui.badge variant="neutral" :dot="true">Nonaktif</x-ui.badge>
                                @endif
                            </td>
                            <td class="text-right whitespace-nowrap">
                                <div class="inline-flex items-center justify-end gap-1.5">
                                    <button type="button"
                                        class="btn btn-outline btn-icon btn-sm"
                                        title="Edit {{ $acc->payment_method }}"
                                        aria-label="Edit {{ $acc->payment_method }}"
                                        data-modal-open="#modal-edit-payment-account-{{ $acc->id }}">
                                        <x-ui.icon name="edit" class="h-4 w-4" />
                                    </button>
                                    <button type="button"
                                        class="btn btn-outline btn-icon btn-sm text-danger hover:border-danger/40 hover:bg-danger-soft"
                                        title="Hapus {{ $acc->payment_method }}"
                                        aria-label="Hapus {{ $acc->payment_method }}"
                                        data-modal-open="#modal-delete-payment-account-{{ $acc->id }}">
                                        <x-ui.icon name="trash" class="h-4 w-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-10 text-center text-muted-foreground">
                                Belum ada nomor rekening perusahaan yang ditambahkan. Klik <strong>Tambah Rekening Pembayaran</strong> untuk memasukkan Virtual Account, E-Wallet, atau QRIS resmi PT PKDI.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @foreach ($accounts as $acc)
        <x-ui.modal :id="'modal-edit-payment-account-'.$acc->id" :title="'Edit '.$acc->payment_method">
            <form action="{{ route('finance.payment-accounts.update', $acc->id) }}" method="POST" class="space-y-4" novalidate data-validate-form data-prevent-double-submit>
                @csrf
                @method('PUT')
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.field label="Jenis Pembayaran" name="payment_method" required>
                        <select name="payment_method" class="select" required>
                            <option value="">Pilih jenis pembayaran</option>
                            @foreach ($paymentMethodGroups as $groupLabel => $methods)
                                <optgroup label="{{ $groupLabel }}">
                                    @foreach ($methods as $method)
                                        <option value="{{ $method }}" @selected($acc->payment_method === $method)>{{ $method }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </x-ui.field>
                    <x-ui.field label="Bank / Penyedia" name="provider_name" required>
                        <x-ui.input name="provider_name" minlength="2" :value="$acc->provider_name" placeholder="BCA / Mandiri / GoPay / QRIS" required />
                    </x-ui.field>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.field label="Nomor Rekening / VA / Akun" name="account_number" required>
                        <x-ui.input name="account_number" minlength="3" :value="$acc->account_number" placeholder="Contoh: 880102003004005" required />
                    </x-ui.field>
                    <x-ui.field label="Atas Nama Perusahaan" name="account_holder" required>
                        <x-ui.input name="account_holder" minlength="3" :value="$acc->account_holder" placeholder="PT Pulo Kencana Drum Industri" required />
                    </x-ui.field>
                </div>
                <x-ui.field label="Catatan / Instruksi Tambahan (Opsional)" name="instructions">
                    <x-ui.textarea name="instructions" rows="2" placeholder="Contoh: Cantumkan nomor invoice pada berita acara pembayaran">{{ $acc->instructions }}</x-ui.textarea>
                </x-ui.field>
                <label class="inline-flex cursor-pointer items-center gap-2 text-sm text-foreground">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" class="h-4 w-4 rounded border-input accent-primary" @checked($acc->is_active)>
                    <span>Aktif (tampilkan di halaman pembayaran Client & PDF Invoice)</span>
                </label>
                <div class="flex justify-end gap-2 border-t pt-4">
                    <x-ui.button variant="outline" data-modal-close>Batal</x-ui.button>
                    <x-ui.button type="submit">Simpan Perubahan</x-ui.button>
                </div>
            </form>
        </x-ui.modal>

        <x-ui.modal :id="'modal-delete-payment-account-'.$acc->id" title="Hapus Rekening Pembayaran" icon="trash" tone="danger" size="sm">
            <form action="{{ route('finance.payment-accounts.destroy', $acc->id) }}" method="POST" class="space-y-4" data-prevent-double-submit>
                @csrf
                @method('DELETE')
                <p class="text-sm text-muted-foreground">
                    Apakah Anda yakin ingin menghapus rekening <strong class="text-foreground">{{ $acc->payment_method }} ({{ $acc->account_number }})</strong>?
                </p>
                <div class="flex justify-end gap-2 border-t pt-4">
                    <x-ui.button variant="outline" data-modal-close>Batal</x-ui.button>
                    <x-ui.button type="submit" variant="destructive">Ya, Hapus</x-ui.button>
                </div>
            </form>
        </x-ui.modal>
    @endforeach

    <x-ui.modal id="modal-add-payment-account" title="Tambah Rekening / Kanal Pembayaran">
        <form action="{{ route('finance.payment-accounts.store') }}" method="POST" class="space-y-4" novalidate data-validate-form data-prevent-double-submit>
            @csrf
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.field label="Jenis Pembayaran" name="payment_method" :error="$errors->first('payment_method')" required>
                    <select id="add-payment-method" name="payment_method" class="select @error('payment_method') input-invalid @enderror" required>
                        <option value="">Pilih jenis pembayaran</option>
                        @foreach ($paymentMethodGroups as $groupLabel => $methods)
                            <optgroup label="{{ $groupLabel }}">
                                @foreach ($methods as $method)
                                    <option value="{{ $method }}" @selected(old('payment_method') === $method)>{{ $method }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                </x-ui.field>
                <x-ui.field label="Bank / Penyedia" name="provider_name" :error="$errors->first('provider_name')" required>
                    <x-ui.input id="add-provider-name" name="provider_name" minlength="2" :value="old('provider_name')" placeholder="BCA / Mandiri / GoPay / QRIS" required />
                </x-ui.field>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.field label="Nomor Rekening / VA / Akun" name="account_number" :error="$errors->first('account_number')" required>
                    <x-ui.input name="account_number" minlength="3" :value="old('account_number')" placeholder="Contoh: 880102003004005 / 0812xxxx" required />
                </x-ui.field>
                <x-ui.field label="Atas Nama Perusahaan" name="account_holder" :error="$errors->first('account_holder')" required>
                    <x-ui.input name="account_holder" minlength="3" :value="old('account_holder', 'PT Pulo Kencana Drum Industri')" placeholder="PT Pulo Kencana Drum Industri" required />
                </x-ui.field>
            </div>
            <x-ui.field label="Catatan / Instruksi Tambahan (Opsional)" name="instructions" :error="$errors->first('instructions')">
                <x-ui.textarea name="instructions" rows="2" placeholder="Contoh: Cantumkan nomor invoice pada catatan pembayaran">{{ old('instructions') }}</x-ui.textarea>
            </x-ui.field>
            <label class="inline-flex cursor-pointer items-center gap-2 text-sm text-foreground">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" class="h-4 w-4 rounded border-input accent-primary" checked>
                <span>Langsung aktifkan untuk pembayaran Client</span>
            </label>
            <div class="flex justify-end gap-2 border-t pt-4">
                <x-ui.button variant="outline" data-modal-close>Batal</x-ui.button>
                <x-ui.button type="submit">Simpan Rekening</x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var methodSelect = document.getElementById('add-payment-method');
            var providerInput = document.getElementById('add-provider-name');
            if (!methodSelect || !providerInput) return;
            var autoFilled = false;
            providerInput.addEventListener('input', function () {
                autoFilled = false;
            });
            methodSelect.addEventListener('change', function () {
                var val = methodSelect.value || '';
                if (!val) return;
                if (!providerInput.value || autoFilled) {
                    providerInput.value = val.replace(/^Virtual Account\s+/i, '');
                    autoFilled = true;
                }
            });
        });
    </script>
</x-layouts.admin>
