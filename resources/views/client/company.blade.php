@php
    $companyName = old('company_name', $company?->company_name ?? '');
    $picName = old('pic_name', $company?->pic_name ?? '');
    $phone = old('phone', $company?->phone ?? '');
    $email = old('email', $company?->email ?? '');
    $address = old('address', $company?->address ?? '');
    $latitude = old('latitude', $company?->latitude);
    $longitude = old('longitude', $company?->longitude);
    $googleMapsUrl = old('google_maps_url', $company?->resolvedGoogleMapsUrl());
    $npwp = old('npwp', $company?->npwp ?? '');
    $nibNumber = old('nib_number', $company?->nib_number ?? '');
    $docsComplete = $company?->documents_status === 'COMPLETE';
@endphp
<x-layouts.admin title="Profil Perusahaan" group="Perusahaan & Akun" role="client">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <x-ui.page-header title="Profil & Lokasi Pabrik Perusahaan" description="Kelola identitas perusahaan, titik koordinat lokasi penjemputan, NPWP, dan dokumen NIB untuk operasional PKDI." />
        <x-ui.badge :variant="$docsComplete ? 'success' : 'warning'" :dot="true">
            Dokumen Administrasi: {{ $docsComplete ? 'Lengkap' : 'Belum Lengkap' }}
        </x-ui.badge>
    </div>

    <x-ui.card title="Data & Titik Lokasi Perusahaan Pelanggan">
        <form action="{{ route('client.company.update') }}" method="POST" enctype="multipart/form-data" class="space-y-5 max-w-3xl" novalidate data-validate-form data-prevent-double-submit>
            @csrf
            @method('PUT')
            <x-ui.field label="Nama Perusahaan (PT / CV)" name="company_name" :error="$errors->first('company_name')" required>
                <x-ui.input name="company_name" :value="$companyName" :invalid="$errors->has('company_name')" required />
            </x-ui.field>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.field label="Nama Penanggung Jawab (PIC)" name="pic_name" :error="$errors->first('pic_name')" required>
                    <x-ui.input name="pic_name" :value="$picName" :invalid="$errors->has('pic_name')" required />
                </x-ui.field>
                <x-ui.field label="Nomor Telepon Perusahaan" name="phone" :error="$errors->first('phone')" required>
                    <x-ui.input name="phone" :value="$phone" :invalid="$errors->has('phone')" required />
                </x-ui.field>
            </div>
            <x-ui.field label="Email Kontak Perusahaan" name="email" :error="$errors->first('email')" required>
                <x-ui.input name="email" type="email" :value="$email" :invalid="$errors->has('email')" required />
            </x-ui.field>

            <div class="border-t pt-5 space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h2 class="text-sm font-semibold text-foreground">Lokasi Pabrik & Alamat Penjemputan Resmi</h2>
                        <p class="text-xs text-muted-foreground">Perubahan lokasi hanya berlaku untuk pesanan baru; pesanan lama tetap menyimpan snapshot lokasi historisnya.</p>
                    </div>
                    @if (!empty($googleMapsUrl))
                        <a href="{{ $googleMapsUrl }}" target="_blank" rel="noopener" class="btn btn-outline btn-sm">
                            <x-ui.icon name="map-pin" class="h-3.5 w-3.5" />
                            Lihat Titik Lokasi di Google Maps
                        </a>
                    @endif
                </div>

                <x-ui.location-picker
                    id="company-profile-map"
                    targetSelector="textarea[name='address']"
                    :latitude="$latitude"
                    :longitude="$longitude"
                    :googleMapsUrl="$googleMapsUrl"
                    height="250px"
                />

                <x-ui.field
                    label="Alamat Lengkap Perusahaan"
                    name="address"
                    help="Klik peta di atas atau tombol Gunakan Lokasi Saat Ini untuk auto-fill, lalu tambahkan detail blok pabrik, kavling, atau patokan gerbang bila perlu."
                    :error="$errors->first('address')"
                    required>
                    <x-ui.textarea name="address" rows="3" :invalid="$errors->has('address')" required>{{ $address }}</x-ui.textarea>
                </x-ui.field>
            </div>

            <div class="border-t pt-5 space-y-4">
                <div>
                    <h2 class="text-sm font-semibold text-foreground">Administrasi Legalitas Perusahaan</h2>
                    <p class="text-xs text-muted-foreground">Isi NPWP serta NIB perusahaan sesuai ketentuan administrasi PKDI.</p>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.field label="Nomor NPWP" name="npwp" :error="$errors->first('npwp')">
                        <x-ui.input name="npwp" :value="$npwp" placeholder="00.000.000.0-000.000" :invalid="$errors->has('npwp')" />
                    </x-ui.field>
                    <x-ui.field label="Nomor Induk Berusaha (NIB)" name="nib_number" :error="$errors->first('nib_number')">
                        <x-ui.input name="nib_number" :value="$nibNumber" placeholder="Nomor NIB perusahaan" :invalid="$errors->has('nib_number')" />
                    </x-ui.field>
                </div>
                <x-ui.field label="Unggah Dokumen NIB (PDF / JPG / PNG, maks 5 MB)" name="nib_file" :error="$errors->first('nib_file')">
                    <x-ui.file-preview id="company-nib-file" name="nib_file" label="{{ $company?->nib_file_path ? 'Dokumen NIB sudah terunggah. Pilih berkas baru untuk mengganti.' : 'Pilih berkas NIB perusahaan (PDF/JPG/PNG)' }}" />
                </x-ui.field>
            </div>

            <div class="flex justify-end border-t pt-4">
                <x-ui.button type="submit">
                    <x-ui.icon name="check" />
                    Simpan Profil Perusahaan
                </x-ui.button>
            </div>
        </form>
    </x-ui.card>
</x-layouts.admin>
