@props([
    'items' => config('pkdi.announcements', [
        'Selamat datang di Sistem Manajemen Operasional dan Keuangan PT Pulo Kencana Drum Industri (PKDI)',
        'Layanan operasional PKDI mencakup Penjualan Drum dan Jasa Rekondisi (Laundry, Laundry + Cat, Laundry + Cat + Sablon)',
        'Setiap pesanan Client langsung masuk ke antrean peninjauan dan operasional Kepala Gudang',
    ]),
])
<div class="pkdi-ticker" data-news-ticker role="region" aria-label="Pengumuman Operasional PKDI">
    <div class="pkdi-ticker__badge" aria-hidden="true">
        <span class="pkdi-ticker__dot"></span>
        <span class="pkdi-ticker__badge-text">INFO</span>
    </div>
    <div class="pkdi-ticker__viewport">
        <div class="pkdi-ticker__track" data-news-ticker-track>
            <div class="pkdi-ticker__group" data-news-ticker-group>
                @foreach ($items as $item)
                    <span class="pkdi-ticker__item">{{ $item }}</span>
                    <span class="pkdi-ticker__sep" aria-hidden="true">&#9679;</span>
                @endforeach
            </div>
        </div>
    </div>
</div>
