@php
    $ro = $reorderOrder ?? null;
    $selectedType = old('transaction_type', $ro?->transaction_type ?? \App\Models\ServiceOrder::TYPE_RECONDITIONING);
    $isReconditioning = ! in_array($selectedType, [\App\Models\ServiceOrder::TYPE_WASTE_TRANSPORT, 'DRUM_SALE'], true);
    $paymentMethodGroups = $paymentMethodGroups ?? config('pkdi.payment_methods', []);
    $selectedPaymentMethod = old('payment_method', $ro?->payment_method ?? '');
    $accountsByMethod = collect($paymentAccounts ?? [])->keyBy('payment_method');
    $selectedAccount = $selectedPaymentMethod !== '' ? $accountsByMethod->get($selectedPaymentMethod) : null;
    $resolvedCompany = $clientCompany ?? auth()->user()?->clientCompany;
    $resolvedAddress = $resolvedCompany?->address ?: 'Alamat perusahaan belum dilengkapi di profil.';
    $resolvedMapsUrl = $resolvedCompany?->resolvedGoogleMapsUrl();
@endphp
<x-layouts.admin title="Buat Permintaan Layanan" group="Portal Client" role="client">
    <div>
        <a href="{{ route('client.orders.index') }}" class="mb-2 inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground">
            <x-ui.icon name="arrow-left" class="h-4 w-4" />
            Kembali ke daftar permintaan
        </a>
        <x-ui.page-header title="Pengajuan Permintaan Layanan Baru" description="Lengkapi rincian kemasan, tanggal penyerahan, dan metode pembayaran untuk diajukan ke PT PKDI." />
    </div>

    @if ($ro)
        <x-ui.alert variant="info" title="Pesan Ulang (1-Click Re-Order) dari {{ $ro->order_number }}">
            Rincian layanan, kemasan, kuantitas, dan catatan dari pesanan sebelumnya telah disalin ke draf ini. Periksa lalu kirim pesanan.
        </x-ui.alert>
    @endif

    <form action="{{ route('client.orders.store') }}" method="POST" class="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(18rem,1fr)]" novalidate data-validate-form data-prevent-double-submit data-transaction-form>
        @csrf
        <input type="hidden" name="origin_company_name" value="{{ old('origin_company_name', $companyName ?: (auth()->user()?->companyDisplay() ?? '')) }}">
        @if ($ro)
            <input type="hidden" name="reordered_from_order_id" value="{{ $ro->id }}">
        @endif
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

            <x-ui.card title="2. Rincian Kemasan dan Paket Layanan" description="Pilih jenis drum/kemasan, kuantitas, jadwal penyerahan, serta metode pembayaran resmi PKDI.">
                {{-- Baris 1: Jenis Drum / Kemasan * (Kiri) | Paket Layanan Rekondisi * (Kanan) --}}
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.field label="Jenis Drum / Kemasan" name="drum_product_id" :error="$errors->first('drum_product_id')" required>
                        <select id="f-drum_product_id" name="drum_product_id" class="select @error('drum_product_id') input-invalid @enderror" data-product-select required>
                            <option value="">Pilih jenis drum / kemasan</option>
                            @foreach ($drumProducts as $product)
                                <option value="{{ $product->id }}" data-default-unit="{{ $product->default_unit }}" data-product-name="{{ $product->name }}" @selected((string) old('drum_product_id', $ro?->drum_product_id) === (string) $product->id)>
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
                                    <option value="{{ $service->id }}" data-service-name="{{ $service->name }}" @selected((string) old('reconditioning_service_id', $ro?->reconditioning_service_id) === (string) $service->id)>
                                        {{ $service->name }}
                                    </option>
                                @endforeach
                            </select>
                        </x-ui.field>
                    </div>
                </div>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    {{-- Baris 2: Kuantitas Estimasi * & Satuan * (Kiri) | Tanggal Penyerahan / Terima (Kanan) --}}
                    <div class="grid grid-cols-2 gap-3">
                        <x-ui.field label="Kuantitas Estimasi" name="requested_quantity" :error="$errors->first('requested_quantity')" required>
                            <x-ui.input name="requested_quantity" type="number" min="1" :value="old('requested_quantity', $ro?->requested_quantity)" placeholder="50" :invalid="$errors->has('requested_quantity')" required />
                        </x-ui.field>
                        <x-ui.field label="Satuan" name="requested_unit" :error="$errors->first('requested_unit')" required>
                            <x-ui.input name="requested_unit" :value="old('requested_unit', $ro?->requested_unit ?? 'Drum')" placeholder="Drum / Unit" :invalid="$errors->has('requested_unit')" data-unit-input required />
                        </x-ui.field>
                    </div>

                    <x-ui.field label="Tanggal Penyerahan / Terima" name="needed_date" :error="$errors->first('needed_date')">
                        <x-ui.input name="needed_date" type="date" :value="old('needed_date')" :invalid="$errors->has('needed_date')" />
                    </x-ui.field>

                    {{-- Baris 3: Jenis Limbah / Bekas Isi Kemasan (Opsional) (Kiri) | Jenis Pembayaran * + Kotak Detail VA & Salin (Kanan) --}}
                    <x-ui.field label="Jenis Limbah / Bekas Isi Kemasan (Opsional)" name="cargo_type" :error="$errors->first('cargo_type')">
                        <x-ui.input name="cargo_type" :value="old('cargo_type', $ro?->cargo_type)" placeholder="Contoh: Residu pelumas mesin, resin, atau bahan industri" :invalid="$errors->has('cargo_type')" />
                    </x-ui.field>

                    <x-ui.field label="Jenis Pembayaran" name="payment_method" :error="$errors->first('payment_method')" required>
                        <select id="f-payment_method" name="payment_method" class="select @error('payment_method') input-invalid @enderror" required>
                            <option value="">Pilih jenis pembayaran (Virtual Account, E-Wallet, QRIS, dll.)</option>
                            @foreach ($paymentMethodGroups as $groupLabel => $methods)
                                <optgroup label="{{ $groupLabel }}">
                                    @foreach ($methods as $method)
                                        @php
                                            $acc = $accountsByMethod->get($method);
                                        @endphp
                                        <option value="{{ $method }}"
                                            data-account-number="{{ $acc?->account_number }}"
                                            data-account-holder="{{ $acc?->account_holder }}"
                                            data-provider="{{ $acc?->provider_name }}"
                                            data-instructions="{{ $acc?->instructions }}"
                                            @selected($selectedPaymentMethod === $method)>
                                            {{ $method }}@if ($acc) - {{ $acc->account_number }} (a.n. {{ $acc->account_holder }})@endif
                                        </option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>

                        <div id="selected-payment-account-box" class="mt-2.5 rounded-xl border p-3.5 bg-soft" @if (! $selectedAccount) hidden @endif>
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div class="flex items-center gap-1.5">
                                    <x-ui.icon name="card" class="h-4 w-4 text-brand-accent" />
                                    <span class="text-xs font-semibold text-foreground">Rekening / Virtual Account Resmi</span>
                                </div>
                                <x-ui.badge variant="info">Aktif</x-ui.badge>
                            </div>
                            <div class="mt-2 flex flex-wrap items-center justify-between gap-2 rounded-lg border bg-background px-3 py-2">
                                <div>
                                    <p class="text-xs text-muted-foreground">
                                        <span id="selected-account-provider" class="font-medium text-foreground">{{ $selectedAccount?->provider_name }}</span>
                                        · a.n. <strong id="selected-account-holder" class="text-foreground">{{ $selectedAccount?->account_holder }}</strong>
                                    </p>
                                    <p id="selected-account-number" class="mt-0.5 font-mono text-sm font-semibold tracking-tight tabular-nums text-foreground">{{ $selectedAccount?->account_number }}</p>
                                </div>
                                <button type="button" id="selected-account-copy" class="btn btn-outline btn-sm shrink-0" data-copy="{{ $selectedAccount?->account_number }}">
                                    <x-ui.icon name="copy" class="h-3.5 w-3.5" />
                                    Salin
                                </button>
                            </div>
                            <p id="selected-account-instructions" class="mt-1.5 text-xs leading-relaxed text-muted-foreground" @if (empty($selectedAccount?->instructions)) hidden @endif>{{ $selectedAccount?->instructions }}</p>
                        </div>
                    </x-ui.field>

                    {{-- Baris 4 (Full Width): Catatan Tambahan, Warna & Penanganan (Opsional) --}}
                    <div class="sm:col-span-2">
                        <x-ui.field label="Catatan Tambahan, Warna & Penanganan (Opsional)" name="specification_notes" :error="$errors->first('specification_notes')">
                            <x-ui.textarea name="specification_notes" rows="2" placeholder="Tuliskan detail warna cat, sablon, instruksi penanganan, atau catatan tambahan untuk Gudang" :invalid="$errors->has('specification_notes')">{{ old('specification_notes') }}</x-ui.textarea>
                        </x-ui.field>
                    </div>
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
                    <div class="rounded-lg border bg-muted/20 p-3 text-xs space-y-1.5">
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-semibold text-foreground inline-flex items-center gap-1">
                                <x-ui.icon name="map-pin" class="h-3.5 w-3.5 text-brand-accent" />
                                Alamat Lokasi Penjemputan
                            </span>
                            <a href="{{ route('client.company') }}" class="font-medium text-primary hover:underline">Ubah di Profil</a>
                        </div>
                        <p class="leading-relaxed text-muted-foreground">{{ $resolvedAddress }}</p>
                        @if ($resolvedMapsUrl)
                            <div class="pt-0.5">
                                <a href="{{ $resolvedMapsUrl }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 font-semibold text-brand-accent hover:underline">
                                    <x-ui.icon name="external-link" class="h-3 w-3" />
                                    Lihat Titik Lokasi
                                </a>
                            </div>
                        @endif
                    </div>
                    <div class="flex items-center justify-between gap-2 border-b pb-2.5">
                        <dt class="text-muted-foreground">Tujuan Antrean</dt>
                        <dd class="font-medium text-foreground">Perusahaan</dd>
                    </div>
                    <div class="flex items-center justify-between gap-2 border-b pb-2.5">
                        <dt class="text-muted-foreground">Status Awal</dt>
                        <dd><x-ui.badge variant="info" :dot="true">Ditinjau Gudang</x-ui.badge></dd>
                    </div>
                    <div class="flex items-center justify-between gap-2 border-b pb-2.5">
                        <dt class="text-muted-foreground">Jenis Pembayaran</dt>
                        <dd id="summary-payment-method" class="text-right text-xs font-medium text-foreground">
                            {{ $selectedPaymentMethod !== '' ? $selectedPaymentMethod : 'Belum dipilih' }}
                        </dd>
                    </div>
                    <div id="summary-account-row" class="flex items-center justify-between gap-2 border-b pb-2.5" @if (! $selectedAccount) hidden @endif>
                        <dt class="text-muted-foreground">No. Rekening / VA</dt>
                        <dd id="summary-account-number" class="font-mono text-right text-xs font-semibold tabular-nums text-foreground">
                            {{ $selectedAccount?->account_number }}
                        </dd>
                    </div>
                    <div class="flex items-center justify-between gap-2 border-b pb-2.5">
                        <dt class="text-muted-foreground">Penagihan</dt>
                        <dd class="text-right text-xs text-muted-foreground">Diterbitkan Keuangan setelah pekerjaan selesai</dd>
                    </div>
                    <div class="rounded-xl border border-primary/30 bg-soft p-3.5 space-y-1.5">
                        <div class="flex items-center justify-between gap-2 text-xs">
                            <span class="font-semibold text-foreground">Estimasi Total Harga</span>
                            <span id="summary-tariff-badge" class="text-[11px] text-muted-foreground tabular-nums">Tarif Resmi PKDI</span>
                        </div>
                        <p id="summary-price-breakdown" class="text-xs text-muted-foreground leading-relaxed">
                            Pilih jenis layanan, kemasan &amp; kuantitas untuk melihat estimasi harga.
                        </p>
                        <div class="flex items-baseline justify-between gap-2 border-t border-primary/20 pt-2">
                            <span class="text-xs text-muted-foreground">Total Estimasi</span>
                            <span id="summary-total-price" class="text-lg font-bold tracking-tight text-brand-accent tabular-nums">Rp 0</span>
                        </div>
                        <p class="text-[11px] leading-snug text-muted-foreground">
                            *Estimasi mengikuti tarif standar PKDI; nominal final mengacu pada hasil verifikasi aktual Gudang &amp; Keuangan.
                        </p>
                    </div>
                </dl>
                <div class="mt-5 space-y-2 border-t pt-4">
                    <x-ui.button type="submit" class="w-full btn-lg">
                        <x-ui.icon name="check" />
                        Kirim Pesanan ke Perusahaan
                    </x-ui.button>
                    <a href="{{ route('client.orders.index') }}" class="btn btn-outline w-full">Batal</a>
                </div>
            </x-ui.card>
        </div>
    </form>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var tariffs = @json($tariffs ?? []);

            // 1. Sinkronisasi Jenis Pembayaran & Rekening / VA
            var select = document.getElementById('f-payment_method');
            var summary = document.getElementById('summary-payment-method');
            var summaryAccRow = document.getElementById('summary-account-row');
            var summaryAccNum = document.getElementById('summary-account-number');
            var box = document.getElementById('selected-payment-account-box');
            var numEl = document.getElementById('selected-account-number');
            var holderEl = document.getElementById('selected-account-holder');
            var providerEl = document.getElementById('selected-account-provider');
            var instrEl = document.getElementById('selected-account-instructions');
            var copyBtn = document.getElementById('selected-account-copy');

            if (select && summary) {
                var syncPayment = function () {
                    var val = select.value || '';
                    var opt = select.options[select.selectedIndex];
                    var accNum = opt ? (opt.getAttribute('data-account-number') || '') : '';
                    var accHolder = opt ? (opt.getAttribute('data-account-holder') || '') : '';
                    var accProvider = opt ? (opt.getAttribute('data-provider') || '') : '';
                    var accInstr = opt ? (opt.getAttribute('data-instructions') || '') : '';

                    summary.textContent = val || 'Belum dipilih';

                    if (summaryAccRow && summaryAccNum) {
                        if (val && accNum) {
                            summaryAccNum.textContent = accNum;
                            summaryAccRow.hidden = false;
                        } else {
                            summaryAccRow.hidden = true;
                        }
                    }

                    if (box) {
                        if (val && accNum) {
                            if (numEl) numEl.textContent = accNum;
                            if (holderEl) holderEl.textContent = accHolder;
                            if (providerEl) providerEl.textContent = accProvider;
                            if (copyBtn) copyBtn.setAttribute('data-copy', accNum);
                            if (instrEl) {
                                instrEl.textContent = accInstr;
                                instrEl.hidden = !accInstr;
                            }
                            box.hidden = false;
                        } else {
                            box.hidden = true;
                        }
                    }
                };
                select.addEventListener('change', syncPayment);
                syncPayment();
            }

            // 2. Kalkulator Estimasi Total Harga Otomatis sesuai Jenis Pemesanan, Kemasan, Paket & Kuantitas
            var typeRadios = document.querySelectorAll('input[name="transaction_type"]');
            var productSelect = document.getElementById('f-drum_product_id');
            var serviceSelect = document.getElementById('f-reconditioning_service_id');
            var qtyInput = document.querySelector('input[name="requested_quantity"]');
            var unitInput = document.querySelector('input[name="requested_unit"]');
            var priceBreakdownEl = document.getElementById('summary-price-breakdown');
            var totalPriceEl = document.getElementById('summary-total-price');
            var tariffBadgeEl = document.getElementById('summary-tariff-badge');

            function formatRp(amount) {
                var n = Math.round(Number(amount) || 0);
                return 'Rp ' + n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
            }

            function norm(str) {
                return String(str || '').trim().toLowerCase();
            }

            function resolveTariff(txType, productName, serviceName, unitName) {
                if (!Array.isArray(tariffs) || tariffs.length === 0) return null;
                var pNorm = norm(productName);
                var sNorm = norm(serviceName);
                var uNorm = norm(unitName);

                if (txType === 'WASTE_TRANSPORT') {
                    var wasteTariffs = tariffs.filter(function (t) {
                        return norm(t.service_type) === 'pengangkutan limbah';
                    });
                    if (wasteTariffs.length === 0) return null;
                    return wasteTariffs.find(function (t) { return norm(t.unit) === uNorm; })
                        || wasteTariffs.find(function (t) { return norm(t.unit) === 'drum'; })
                        || wasteTariffs[0];
                }

                if (!sNorm) return null;
                var svcTariffs = tariffs.filter(function (t) {
                    return norm(t.service_type) === sNorm;
                });
                if (svcTariffs.length === 0) return null;

                // 1) Cocok persis nama produk/kategori
                var exact = svcTariffs.find(function (t) {
                    return pNorm && norm(t.service_category) === pNorm;
                });
                if (exact) return exact;

                // 2) Cocok sebagian nama produk/kategori (mis. "Drum besi" -> "Drum Besi 200L ...")
                var partial = svcTariffs.find(function (t) {
                    var cat = norm(t.service_category);
                    return pNorm && cat && (cat.indexOf(pNorm) !== -1 || pNorm.indexOf(cat) !== -1);
                });
                if (partial) return partial;

                // 3) Fallback ke tarif pertama untuk paket rekondisi terpilih
                return svcTariffs[0];
            }

            function syncEstimate() {
                if (!priceBreakdownEl || !totalPriceEl) return;

                var checkedRadio = document.querySelector('input[name="transaction_type"]:checked');
                var txType = checkedRadio ? checkedRadio.value : 'RECONDITIONING';
                var prodOpt = productSelect && productSelect.selectedIndex >= 0 ? productSelect.options[productSelect.selectedIndex] : null;
                var svcOpt = serviceSelect && serviceSelect.selectedIndex >= 0 ? serviceSelect.options[serviceSelect.selectedIndex] : null;

                var productName = prodOpt ? (prodOpt.getAttribute('data-product-name') || '') : '';
                var serviceName = txType === 'WASTE_TRANSPORT'
                    ? 'Pengangkutan Limbah'
                    : (svcOpt ? (svcOpt.getAttribute('data-service-name') || '') : '');
                var qty = qtyInput ? parseInt(qtyInput.value, 10) : 0;
                var unit = (unitInput && unitInput.value.trim()) ? unitInput.value.trim() : 'Drum';

                var matched = resolveTariff(txType, productName, serviceName, unit);

                if (!productName || (txType === 'RECONDITIONING' && !serviceName)) {
                    priceBreakdownEl.textContent = 'Pilih jenis layanan, kemasan & kuantitas untuk melihat estimasi harga.';
                    totalPriceEl.textContent = 'Rp 0';
                    if (tariffBadgeEl) tariffBadgeEl.textContent = 'Tarif Resmi PKDI';
                    return;
                }

                if (!matched) {
                    priceBreakdownEl.textContent = (txType === 'WASTE_TRANSPORT' ? 'Pengangkutan Limbah' : serviceName) + ' · ' + productName + ' (tarif dihitung Keuangan)';
                    totalPriceEl.textContent = 'Dihitung Keuangan';
                    if (tariffBadgeEl) tariffBadgeEl.textContent = 'Tarif Khusus';
                    return;
                }

                var unitPrice = parseFloat(matched.unit_price) || 0;
                if (tariffBadgeEl) {
                    tariffBadgeEl.textContent = formatRp(unitPrice) + ' / ' + (matched.unit || unit);
                }

                if (!qty || qty < 1) {
                    priceBreakdownEl.textContent = serviceName + ' · ' + productName + ' - masukkan kuantitas untuk hitung total.';
                    totalPriceEl.textContent = 'Rp 0';
                    return;
                }

                var total = unitPrice * qty;
                priceBreakdownEl.textContent = serviceName + ' · ' + productName + ' (' + qty + ' ' + unit + ' × ' + formatRp(unitPrice) + ')';
                totalPriceEl.textContent = formatRp(total);
            }

            typeRadios.forEach(function (r) { r.addEventListener('change', syncEstimate); });
            if (productSelect) productSelect.addEventListener('change', syncEstimate);
            if (serviceSelect) serviceSelect.addEventListener('change', syncEstimate);
            if (qtyInput) qtyInput.addEventListener('input', syncEstimate);
            if (unitInput) unitInput.addEventListener('input', syncEstimate);
            syncEstimate();
        });
    </script>
</x-layouts.admin>
