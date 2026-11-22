@php
    $selectedType = old('transaction_type', \App\Models\ServiceOrder::TYPE_RECONDITIONING);
    $isReconditioning = ! in_array($selectedType, [\App\Models\ServiceOrder::TYPE_WASTE_TRANSPORT, 'DRUM_SALE'], true);
@endphp
<x-layouts.admin title="Buat Permintaan Layanan" group="Portal Client" role="client">
    <div>
        <a href="{{ route('client.orders.index') }}" class="mb-2 inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground">
            <x-ui.icon name="arrow-left" class="h-4 w-4" />
            Kembali ke daftar permintaan
        </a>
        <x-ui.page-header title="Pengajuan Permintaan Layanan Baru" description="Lengkapi rincian kemasan, layanan yang dibutuhkan, dan dokumen pendukung untuk diajukan ke bagian Gudang PT PKDI." />
    </div>

    <form action="{{ route('client.orders.store') }}" method="POST" enctype="multipart/form-data" class="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(18rem,1fr)]" novalidate data-validate-form data-prevent-double-submit data-transaction-form>
        @csrf
        <div class="space-y-6">
            <x-ui.card title="1. Jenis Permintaan Layanan" description="Pilih layanan operasional yang dibutuhkan perusahaan Anda.">
                <div class="grid gap-3 sm:grid-cols-2" role="radiogroup" aria-label="Pilih jenis layanan">
                    <label class="cursor-pointer">
                        <input type="radio" name="transaction_type" value="RECONDITIONING" class="peer sr-only" @checked($isReconditioning) data-transaction-radio>
                        <span class="block rounded-lg border p-4 transition-colors peer-checked:border-primary peer-checked:bg-soft peer-focus-visible:ring-2 peer-focus-visible:ring-ring/50">
                            <span class="flex items-center gap-2 font-semibold text-foreground">
                                <x-ui.icon name="sliders" class="h-4 w-4 text-brand-accent" />
                                Rekondisi Drum
                            </span>
                            <span class="mt-1 block text-xs text-muted-foreground">Layanan pencucian dan rekondisi drum dengan pilihan paket Laundry, Laundry + Cat, atau Laundry + Cat + Sablon.</span>
                        </span>
                    </label>
                    <label class="cursor-pointer">
                        <input type="radio" name="transaction_type" value="WASTE_TRANSPORT" class="peer sr-only" @checked(! $isReconditioning) data-transaction-radio>
                        <span class="block rounded-lg border p-4 transition-colors peer-checked:border-primary peer-checked:bg-soft peer-focus-visible:ring-2 peer-focus-visible:ring-ring/50">
                            <span class="flex items-center gap-2 font-semibold text-foreground">
                                <x-ui.icon name="truck" class="h-4 w-4 text-brand-accent" />
                                Pengangkutan Limbah
                            </span>
                            <span class="mt-1 block text-xs text-muted-foreground">Layanan penjemputan limbah dan penerimaan kemasan dari lokasi perusahaan Anda ke fasilitas PKDI.</span>
                        </span>
                    </label>
                </div>
                @if ($errors->has('transaction_type'))
                    <p class="field-error mt-2" data-server-error role="alert">{{ $errors->first('transaction_type') }}</p>
                @endif
            </x-ui.card>

            <x-ui.card title="2. Rincian Kemasan dan Paket Layanan" description="Pilih jenis drum/kemasan dan paket rekondisi dari master data resmi PKDI.">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.field label="Jenis Drum / Kemasan" name="drum_product_id" :error="$errors->first('drum_product_id')" required>
                        <select id="f-drum_product_id" name="drum_product_id" class="select @error('drum_product_id') input-invalid @enderror" data-product-select required>
                            <option value="">Pilih jenis drum / kemasan</option>
                            @foreach ($drumProducts as $product)
                                <option value="{{ $product->id }}" data-default-unit="{{ $product->default_unit }}" @selected((string) old('drum_product_id') === (string) $product->id)>
                                    {{ $product->name }}@if ($product->capacity_label) ({{ $product->capacity_label }}) @endif
                                </option>
                            @endforeach
                        </select>
                    </x-ui.field>

                    <div data-reconditioning-only @if (! $isReconditioning) hidden @endif>
                        <x-ui.field label="Paket Layanan Rekondisi" name="reconditioning_service_id" :error="$errors->first('reconditioning_service_id')" required>
                            <select id="f-reconditioning_service_id" name="reconditioning_service_id" class="select @error('reconditioning_service_id') input-invalid @enderror" @required($isReconditioning) @disabled(! $isReconditioning)>
                                <option value="">Pilih paket rekondisi</option>
                                @foreach ($reconditioningServices as $service)
                                    <option value="{{ $service->id }}" @selected((string) old('reconditioning_service_id') === (string) $service->id)>
                                        {{ $service->name }}
                                    </option>
                                @endforeach
                            </select>
                        </x-ui.field>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <x-ui.field label="Kuantitas Estimasi" name="requested_quantity" :error="$errors->first('requested_quantity')" required>
                            <x-ui.input name="requested_quantity" type="number" min="1" :value="old('requested_quantity')" placeholder="50" :invalid="$errors->has('requested_quantity')" required />
                        </x-ui.field>
                        <x-ui.field label="Satuan" name="requested_unit" :error="$errors->first('requested_unit')" required>
                            <x-ui.input name="requested_unit" :value="old('requested_unit', 'Drum')" placeholder="Drum / Unit" :invalid="$errors->has('requested_unit')" data-unit-input required />
                        </x-ui.field>
                    </div>

                    <x-ui.field label="Tanggal Kebutuhan Pengangkutan / Penyerahan" name="needed_date" :error="$errors->first('needed_date')">
                        <x-ui.input name="needed_date" type="date" :value="old('needed_date')" :invalid="$errors->has('needed_date')" />
                    </x-ui.field>

                    <div class="sm:col-span-2">
                        <x-ui.field label="Jenis Limbah / Bekas Isi Kemasan (Opsional)" name="cargo_type" :error="$errors->first('cargo_type')">
                            <x-ui.input name="cargo_type" :value="old('cargo_type')" placeholder="Contoh: Residu pelumas mesin, resin, atau bahan industri" :invalid="$errors->has('cargo_type')" />
                        </x-ui.field>
                    </div>

                    <div class="sm:col-span-2">
                        <x-ui.field label="Spesifikasi / Permintaan Warna & Penanganan (Opsional)" name="specification_notes" :error="$errors->first('specification_notes')">
                            <x-ui.textarea name="specification_notes" rows="2" placeholder="Tuliskan detail warna cat, sablon, atau catatan penanganan limbah" :invalid="$errors->has('specification_notes')">{{ old('specification_notes') }}</x-ui.textarea>
                        </x-ui.field>
                    </div>
                </div>
            </x-ui.card>

            <x-ui.card title="3. Dokumen PO dan Lampiran Pendukung" description="Data kendaraan, nopol, dan sopir tidak wajib diisi Client; data pengangkutan dicatat oleh pihak internal PKDI.">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.field label="Perusahaan Pemohon (PT / CV)" name="origin_company_name" :error="$errors->first('origin_company_name')" required>
                        <x-ui.input name="origin_company_name" :value="old('origin_company_name', $companyName ?? '')" :invalid="$errors->has('origin_company_name')" required />
                    </x-ui.field>

                    <x-ui.field label="Nomor Purchase Order / PO (Opsional)" name="po_number" :error="$errors->first('po_number')">
                        <x-ui.input name="po_number" :value="old('po_number')" placeholder="Contoh: PO/MJK/2026/11/001" :invalid="$errors->has('po_number')" />
                    </x-ui.field>
                </div>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <x-ui.field label="Unggah Dokumen PO (Opsional)" name="po_attachment" :error="$errors->first('po_attachment')">
                        <x-ui.file-preview id="order-po-attachment" name="po_attachment" label="Unggah berkas PO (PDF/JPG/PNG maks 5 MB)" />
                    </x-ui.field>

                    <x-ui.field label="Foto / Lampiran Pendukung (Opsional)" name="attachment" :error="$errors->first('attachment')">
                        <x-ui.file-preview id="order-attachment" name="attachment" label="Unggah foto kemasan atau dokumen pendukung (PDF/JPG/PNG maks 5 MB)" />
                    </x-ui.field>
                </div>

                <div class="mt-4">
                    <x-ui.field label="Catatan Lokasi Penjemputan / Tambahan (Opsional)" name="client_notes" :error="$errors->first('client_notes')">
                        <x-ui.textarea name="client_notes" rows="2" placeholder="Instruksi lokasi muat atau jam operasional gudang Client" :invalid="$errors->has('client_notes')">{{ old('client_notes') }}</x-ui.textarea>
                    </x-ui.field>
                </div>
            </x-ui.card>
        </div>

        <div class="space-y-6">
            <x-ui.card title="Ringkasan Pengajuan" description="Permintaan langsung diteruskan ke bagian operasional Gudang.">
                <dl class="space-y-3 text-sm">
                    <div class="flex items-center justify-between gap-2 border-b pb-2.5">
                        <dt class="text-muted-foreground">Perusahaan</dt>
                        <dd class="truncate font-medium text-foreground">{{ $companyName ?: (auth()->user()?->companyDisplay() ?? '-') }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-2 border-b pb-2.5">
                        <dt class="text-muted-foreground">Tujuan Antrean</dt>
                        <dd class="font-medium text-foreground">Kepala Gudang</dd>
                    </div>
                    <div class="flex items-center justify-between gap-2 border-b pb-2.5">
                        <dt class="text-muted-foreground">Status Awal</dt>
                        <dd><x-ui.badge variant="info" :dot="true">Ditinjau Gudang</x-ui.badge></dd>
                    </div>
                    <div class="flex items-center justify-between gap-2 border-b pb-2.5">
                        <dt class="text-muted-foreground">Metode Pembayaran</dt>
                        <dd class="text-xs font-medium text-foreground">Transfer Bank Manual</dd>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <dt class="text-muted-foreground">Penagihan</dt>
                        <dd class="text-right text-xs text-muted-foreground">Diterbitkan Keuangan setelah pekerjaan selesai</dd>
                    </div>
                </dl>
                <div class="mt-5 space-y-2 border-t pt-4">
                    <x-ui.button type="submit" class="w-full btn-lg">
                        <x-ui.icon name="check" />
                        Kirim Permintaan ke Gudang
                    </x-ui.button>
                    <a href="{{ route('client.orders.index') }}" class="btn btn-outline w-full">Batal</a>
                </div>
            </x-ui.card>
        </div>
    </form>
</x-layouts.admin>
