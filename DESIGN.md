# DESIGN.md: Sistem Desain dan Arah Visual PKDI
## PT Pulo Kencana Drum Industri (PKDI)

> **Design Read:** Reading this as an industrial B2B operations, warehouse management, and finance workspace for **PT Pulo Kencana Drum Industri (PKDI)** across four active user roles (Client, Kepala Gudang, Keuangan, Owner), in a structured Maroon & Charcoal industrial ledger style adapted from `docs/JWB-Dashboard-V2`, dial **ENERGY 2 / RHYTHM 2 / MOTION 1**.

---

## 1. Hasil Audit Aset Brand & Identitas Produk

1. **Aset Resmi Perusahaan:** Tersedia logo resmi perusahaan di `public/Assets/img/logo.png` dan foto pabrik PKDI di `public/Assets/img/background.jpeg` yang diterapkan pada halaman autentikasi, sidebar seluruh role, favicon browser, dan dokumen resmi.
2. **Keputusan Identitas Brand:**
   - **Logo Mark:** Logo resmi `logo.png` di dalam bingkai bulat/bersih (`bg-white`) pada sidebar dan kartu autentikasi.
   - **Nama Perusahaan:** **PT Pulo Kencana Drum Industri**.
   - **Subjudul Identitas (Aksen):** **Operasional Gudang & Layanan Drum** dengan warna aksen Marun (`--brand-accent`).
3. **Karakter Desain:** Perangkat lunak operasional pabrik dan administrasi keuangan yang tegas, mudah dipindai cepat di meja kerja maupun ponsel di area gudang, dengan pemisahan visual jelas antara Penjualan Drum dan Jasa Rekondisi Drum.
4. **Fondasi Teknis UI:** Mengadaptasi komponen Blade `<x-ui.*>`, pola radio-card (`peer sr-only`), detail-list, tabs, modal native `<dialog>`, dan struktur layout dari `docs/JWB-Dashboard-V2` (Tailwind CSS v4), yang disesuaikan ulang untuk palet PKDI dan navigasi 4 role aktif.

---

## 2. Palet Warna, Token Semantik, dan Verifikasi Kontras (WCAG 2.2 AA)

Palet dibatasi pada **2 warna netral inti (Putih/Abu Hangat & Charcoal)** + **1 aksen utama Marun PKDI** (`R-29`), ditambah warna semantik status yang dipasangkan dengan teks label. Seluruh pasangan teks dan batas komponen interaktif telah diuji menggunakan `contrast-check.py`.

### 2.1 Mode Terang (Default Operasional)

| Token CSS | Nilai Hex | Nilai HSL | Kegunaan | Rasio Kontras Terukur |
|---|---|---|---|---|
| `--background` | `#F6F5F4` | `30 11% 96%` | Kanvas latar belakang aplikasi | Dasar kanvas |
| `--card` / `--sidebar` | `#FFFFFF` | `0 0% 100%` | Permukaan kartu, tabel, sidebar, modal, dan header | Dasar komponen |
| `--foreground` | `#18181B` | `240 6% 10%` | Teks utama, judul, angka tabel (Charcoal pekat) | `17.72:1` di `#FFFFFF`, `16.27:1` di `#F6F5F4` (PASS) |
| `--muted-foreground` | `#52525B` | `240 5% 34%` | Label sekunder, timestamp, teks bantuan form | `7.73:1` di `#FFFFFF`, `7.10:1` di `#F6F5F4` (PASS) |
| `--border` | `#E4E4E7` | `240 6% 90%` | Garis pemisah struktural (baris tabel, divider kartu) | Pembatas non-interaktif |
| `--control-border` | `#8E8E95` | `240 4% 57%` | Batas kontrol interaktif (`.input`, `.textarea`, `.select`, `.btn-outline`, checkbox) | `3.25:1` di `#FFFFFF` (PASS WCAG 1.4.11 >= 3:1) |
| `--primary` | `#881337` | `342 75% 30%` | **Marun PKDI**: CTA utama, tab aktif, logo `PKDI` | `9.57:1` teks `#FFFFFF` di `#881337` (PASS) |
| `--brand-accent` | `#881337` | `342 75% 30%` | Subjudul brand, tautan aksi penting, teks sorotan | `9.57:1` di `#FFFFFF` (PASS) |
| `--soft` / `--soft-foreground` | `#FFF1F2` / `#881337` | `356 100% 97%` / `342 75% 30%` | Item sidebar aktif, lencana proses | `8.71:1` (PASS) |
| `--charcoal-surface` | `#18181B` | `240 6% 10%` | Panel brand halaman masuk | `17.72:1` teks `#FFFFFF` (PASS) |

### 2.2 Mode Gelap (Toggle Terverifikasi - `R-34`)

| Token CSS | Nilai Hex | Nilai HSL | Kegunaan | Rasio Kontras Terukur |
|---|---|---|---|---|
| `--background` | `#111113` | `240 6% 7%` | Kanvas gelap Charcoal | Dasar kanvas |
| `--card` / `--sidebar` | `#18181B` / `#141416` | `240 6% 10%` / `240 5% 8%` | Permukaan kartu & sidebar | Dasar komponen |
| `--foreground` | `#F4F4F5` | `240 5% 96%` | Teks utama mode gelap | `16.12:1` di `#18181B` (PASS) |
| `--muted-foreground` | `#A1A1AA` | `240 5% 65%` | Teks sekunder mode gelap | `6.91:1` di `#18181B` (PASS) |
| `--control-border` | `#71717A` | `240 5% 46%` | Batas kontrol interaktif mode gelap | `3.67:1` di `#18181B` (PASS WCAG 1.4.11 >= 3:1) |
| `--primary` | `#BE123C` | `347 83% 41%` | Tombol aksi utama teks putih di mode gelap | `6.29:1` teks `#FFFFFF` di `#BE123C` (PASS) |
| `--brand-accent` | `#FB7185` | `351 95% 71%` | Subjudul brand & tautan aksen mode gelap | `6.58:1` di `#18181B` (PASS) |
| `--soft` / `--soft-foreground` | `#3B1020` / `#FECDD3` | `338 57% 15%` / `353 96% 90%` | Item navigasi aktif mode gelap | `11.62:1` (PASS) |

### 2.3 Warna Semantik Status

Setiap status wajib menampilkan **teks label + warna** (bukan warna saja, sesuai `antislop-human`).

| Status Semantik | Mode Terang (Teks / Latar) | Rasio | Mode Gelap (Teks / Latar) | Rasio | Pemetaan ke Status Operasional PKDI |
|---|---|---|---|---|---|
| `success` | `#15803D` / `#DCFCE7` | `4.57:1` | `#86EFAC` / `#143322` | `9.80:1` | `PAID` (Lunas), `COMPLETED` (Selesai Keluar), `SERVICE_COMPLETED`, Disetujui Masuk/Keluar |
| `warning` | `#92400E` / `#FEF3C7` | `6.37:1` | `#FCD34D` / `#3A270B` | `9.87:1` | `WAITING_GATE_IN`, `NEEDS_CLARIFICATION`, `WAITING_FINANCE`, `PAYMENT_PARTIAL`, `WAITING_GATE_OUT` |
| `danger` | `#B91C1C` / `#FEE2E2` | `5.30:1` | `#FCA5A5` / `#3B1219` | `8.60:1` | `GATE_IN_HELD`, `GATE_OUT_HELD`, Bukti Ditolak, Diblokir Belum Lunas |
| `info` | `#075985` / `#E0F2FE` | `6.59:1` | `#7DD3FC` / `#0F2A3F` | `8.85:1` | `SUBMITTED`, `WAITING_WAREHOUSE`, `WAREHOUSE_REVIEW`, `WAITING_EXIT_DOCUMENT` |
| `soft` | `#881337` / `#FFF1F2` | `8.71:1` | `#FECDD3` / `#3B1020` | `11.62:1` | `IN_PROGRESS` (Sedang Diproses Gudang), `INVOICE_ISSUED` (Tagihan Diterbitkan) |
| `neutral` | `#3F3F46` / `#F4F4F5` | `9.55:1` | `#D4D4D8` / `#27272A` | `10.17:1` | `DRAFT` Tagihan, `CANCELLED` |

---

## 3. Tipografi, Spasi, Grid, Radius, dan Elevasi

1. **Keluarga Font (`R-06`):** `@fontsource/inter` dimuat secara lokal via Vite untuk kejelasan angka dan tabel padat, tanpa ketergantungan jaringan eksternal. Seluruh angka nominal Rupiah, nomor dokumen, kuantitas, dan jam WIB wajib menggunakan `tabular-nums`.
2. **Hierarki Heading & Skala Tipe:**
   - Satu `<h1>` per halaman: `text-lg sm:text-xl font-semibold tracking-tight` (seimbang dengan header portal dan tidak terlalu besar pada kartu atau layar menengah).
   - `<h2>` judul kartu/bagian (`card-title`): `text-[15px] font-semibold leading-snug`.
   - Nilai metrik utama `stat-card`: `text-lg xl:text-[1.2rem] font-semibold tracking-tight tabular-nums whitespace-nowrap` agar nominal Rupiah (mis. `Rp 15.975.000`) tetap rapi dalam satu baris pada grid 4 kolom.
   - `<h3>` sub-bagian / item timeline: `text-sm font-semibold`.
   - Teks badan & sel tabel: `text-sm` (`14px`) dengan fallback satuan `rem` agar mendukung zoom teks 200% tanpa terpotong (`antislop-human`).
   - Keterangan bantu, timestamp, dan label metadata: `text-xs` (`12px`) menggunakan `--muted-foreground` (`7.73:1` kontras).
   - Halaman autentikasi (`/login` dan `/register`): menggunakan mode terang tetap (`data-theme="light"`) tanpa tombol pengubah tema.
3. **Skala Spasi & Grid:**
   - Spasi antar bagian halaman: `space-y-6` (`24px`), padding kanvas `p-4` di ponsel dan `sm:p-6` di tablet/desktop.
   - Grid ringkasan: `grid-cols-1` (ponsel) -> `sm:grid-cols-2` (tablet) -> `xl:grid-cols-4` (desktop).
   - Grid halaman detail: `grid-cols-1` (ponsel/tablet) -> `lg:grid-cols-[minmax(0,2fr)_minmax(18rem,1fr)]` (desktop).
4. **Border Radius (`R-11`):**
   - Kartu & Modal: `0.75rem` (`rounded-xl`).
   - Tombol, Input Form, Select, & Dropdown Menu: `0.375rem` (`rounded-md`).
   - Badge Status: `0.375rem` (`rounded-md`) agar terbedakan secara bentuk dari tombol bulat/avatar.
   - Logo Brand `PKDI` & Avatar: `rounded-full` (bulat sempurna).
5. **Elevasi (`R-12`):**
   - Permukaan kartu dan sidebar duduk datar dengan `1px` border dan `shadow-sm` tipis.
   - Lapisan melayang (`dialog.modal`, `dialog.drawer`, `.menu`) memakai `shadow-xl` dan `backdrop:bg-black/50` untuk memisahkan konteks tugas aktif.

---

## 4. Spesifikasi Komponen Operasional

### 4.1 Tabel Operasional
- Dibungkus kontainer `overflow-x-auto` agar tidak pernah menimbulkan *horizontal scroll* pada halaman.
- Kolom pengambil keputusan (Nomor Pesanan / Nomor Polisi / Status / Tombol Aksi) ditempatkan pada posisi prioritas agar tetap terbaca cepat saat menyempit.
- Dilengkapi bilah pencarian kata kunci, filter status/rentang tanggal, pengurutan kolom (`th[data-sort]` dengan `aria-sort`), dan paginasi (`page-btn`).

### 4.2 Formulir & Validasi
- Setiap input wajib memiliki `<label for>` yang terlihat jelas di atas kolom (bukan mengandalkan `placeholder`).
- Pemilihan jenis transaksi (**Penjualan Drum** vs **Jasa Rekondisi Drum**) memakai pola kartu pilihan radio (`JWB-Dashboard-V2`).
- Pemilihan **Jenis Produk Drum** (`Drum besi`, `Poligen 150 liter`, `Poligen 120 liter`, `Jerigen`) dan **Paket Layanan Rekondisi** (`Laundry`, `Laundry + Cat`, `Laundry + Cat + Sablon`) wajib menggunakan dropdown master (`<select>`), bukan input teks bebas maupun checkbox terpisah.
- Tanda wajib `*` diberi `aria-hidden="true"` dan pesan error ditampilkan tepat di bawah field (`.field-error`, `role="alert"`) serta garis batas `.input-invalid`.
- Data kendaraan/driver tidak diwajibkan pada pengajuan pesanan Client; bila pengiriman dilakukan PKDI, pencatatan armada dilakukan oleh Kepala Gudang di tahap penyerahan/pengiriman.

### 4.3 Modal, Drawer, dan Konfirmasi Tindakan
- Menggunakan elemen HTML native `<dialog>` dengan fokus trap otomatis dan penutupan melalui tombol `Escape` maupun tombol tutup ber-`aria-label`.
- Tindakan sensitif/penahanan (Tahan Masuk, Tahan Keluar, Minta Klarifikasi, Tolak Pekerjaan, Tolak Bukti Bayar, Revisi Tagihan) membuka modal yang mewajibkan pengisian alasan tertulis sebelum submit.

### 4.4 Badge Status, Toast, dan 4 State Data (`R-27`)
- **Badge Status:** Menampilkan dot indikator status + teks Bahasa Indonesia yang konsisten di semua role (tanpa *pulse* atau *glow* dekoratif).
- **Toast Notifikasi:** Muncul di sudut kanan bawah (`aria-live="polite"`), memuat ikon semantik, judul, deskripsi singkat, tombol tutup eksplisit, dan hilang otomatis setelah `4.5 detik`.
- **Loading State:** Skeleton baris terstruktur disertai teks `Memuat daftar data...` dan `role="status"`.
- **Empty State:** Membedakan **kosong awal** (`Belum ada data` + tombol aksi utama) vs **kosong hasil filter** (`Tidak ada data yang cocok` + tombol `Atur Ulang Filter`).
- **Error State:** Menggunakan `role="alert"`, ikon bahaya, penjelasan penyebab singkat, dan tombol `Muat Ulang Data`.

---

## 5. Aturan Ikonografi, Motion, dan Responsivitas

1. **Ikonografi (`R-04`):** Wajib menggunakan sprite **Lucide SVG** lokal (`public/icons.svg`) dengan ketebalan garis `stroke-width="2"`. Dilarang keras memakai emoji atau karakter dekoratif sebagai ikon UI.
2. **Aturan Motion (`MOTION 1`, `R-19`):**
   - Hanya transisi singkat fungsional: buka/tutup sidebar (`200ms`), modal/drawer (`150ms-220ms`), dropdown (`140ms`), dan toast (`220ms`).
   - Tidak ada animasi loop tanpa henti, melayang, atau *bounce*.
   - Seluruh transisi dan animasi dimatikan otomatis di bawah `@media (prefers-reduced-motion: reduce)`.
3. **Pola Responsif 3 Rentang Lebar (`antislop-layoutmobile`):**
   - **Ponsel (`< 640px`):** Sidebar menjadi drawer tersembunyi dengan tombol toggle di header, grid 1 kolom, kontrol tombol pos gerbang dan formulir memiliki tinggi minimum `40px-44px` (`btn-lg` = `44px`) dengan jarak antar tombol minimal `8px-12px`.
   - **Tablet & Laptop Kecil (`640px - 1023px`):** Grid 2 kolom pada kartu metrik dan perbandingan form 2 kolom di area cukup lebar, namun sidebar tetap berupa drawer overlay agar tabel operasional tidak terjepit di lebar `768px`.
   - **Desktop (`>= 1024px`):** Sidebar kiri tetap terbuka (`w-64`), mendukung collapse ke mode mini (`w-16`), dan grid detail terbagi asimetris (`2fr : 1fr`) untuk menampilkan metadata kerja berdampingan dengan timeline status.
4. **Aksesibilitas Interaksi (`R-32`, `R-39`):**
   - Seluruh elemen interaktif memakai `<button>` atau `<a href>`, memiliki `cursor: pointer` pada base CSS layer, serta `:disabled` memakai `cursor: not-allowed`.
   - Cincin fokus `:focus-visible` berwarna Marun PKDI dengan kontras jelas di mode terang dan gelap.

---

## 6. Komposisi Khusus per Peran (4 Role Aktif PRD 2.0)

1. **Client:** Ringkasan pesanan aktif + CTA utama `Buat Pesanan Baru` (Penjualan Drum / Jasa Rekondisi dengan dropdown master) + tracker ringkas 5 tahap + tagihan & unggah bukti transfer + profil & administrasi NPWP/NIB.
2. **Kepala Gudang (Prioritas Scope Tugas Akhir):** Papan antrean kerja operasional berbasis status PRD 2.0 (Pesanan Baru/Ditinjau, Menunggu Penerimaan Fisik, Penyortiran & Rekondisi Aktif, Persiapan Penjualan Drum, Siap Diserahkan/Selesai) + detail pesanan lengkap dengan lampiran aman + alur penerimaan, pemeriksaan, penyortiran seimbang, progres, hasil akhir, serah terima, serta riwayat & laporan Gudang.
3. **Keuangan:** Antrean transaksi siap ditagihkan (terhubung dengan `billable_transactions`) + manajemen tarif + invoice & verifikasi bukti transfer + buku kas + biaya operasional + ekspor PDF/Excel.
4. **Owner:** Ringkasan eksekutif lintas bagian (*read-only*) + grafik tren pendapatan vs biaya operasional + daftar Aktivitas Terbaru Lintas Bagian + penelusuran transaksi + Jejak Audit Sistem.
5. **Pengaturan Akun (`/settings`):** Tersedia bagi seluruh 4 peran aktif untuk mengubah Nama Lengkap, Email, dan Kata Sandi masing-masing.
