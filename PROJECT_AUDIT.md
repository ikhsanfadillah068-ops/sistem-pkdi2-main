# PROJECT AUDIT (PHASE 0)
## Revisi Sistem Manajemen Operasional PKDI Menuju PRD 2.0 dan Scope Tugas Akhir Modul Gudang

- **Tanggal Audit:** 19 November 2026
- **Direktori Proyek:** `c:\sistem_pkdi`
- **Status Kode Aplikasi:** Belum ada perubahan kode aplikasi pada Phase 0 (tahap audit saja)
- **Mode Anti-Slop:** `during` (preferensi global)

---

## 1. Status Pembacaan Skill Anti-Slop dan Dokumen Proyek

### 1.1 Enam Skill Anti-Slop
Seluruh enam skill Anti-Slop pada direktori konfigurasi lokal telah dibaca penuh dan ditetapkan sebagai standar wajib untuk seluruh fase revisi:
- `antislop` (aturan inti R-01 s/d R-40, Craftsmanship Standard C-1 s/d C-5, dan Delivery Gate)
- `antislop-ui` (identitas visual, palet, hierarki, komponen operasional, anti-template dashboard)
- `antislop-human` (kontras WCAG AA 4.5:1 / 3:1, navigasi keyboard, fokus, empat state UI, aksesibilitas interaksi)
- `antislop-layoutmobile` (reflow 3 rentang lebar ponsel, tablet/laptop kecil, desktop; bebas overflow horizontal; tap target minimum 44px)
- `antislop-code` (kebersihan komentar kode, hanya komentar bernilai bisnis/teknis, tanpa narasi dekoratif)
- `antislop-copywriting` (bahasa alami, spesifik, tanpa em dash, tanpa buzzword, tanpa data atau klaim fiktif)

### 1.2 Audit Ketersediaan Dokumen Proyek
Pemeriksaan seluruh direktori `c:\sistem_pkdi` dan `c:\sistem_pkdi\docs` menemukan kondisi berikut:

| Dokumen yang Diminta | Status di Workspace | Keterangan Aktual |
|---|---|---|
| `PRD_PKDI_v2.0_revisi_live_chat.md` | Tidak ditemukan | Dokumen PRD 2.0 yang tersedia adalah `docs/PRD_PKDI_v2.0.md` (1.270 baris, versi 2.0 Draft berdasarkan riset 18 November 2026). Sesuai arahan pengguna, fitur Live Chat dan Super Admin ditunda ke pengembangan lanjutan (bukan MVP). |
| `SCOPE_TUGAS_AKHIR_MODUL_GUDANG_PKDI.md` | Tidak ditemukan sebagai file terpisah | Batasan tugas akhir mahasiswa tertulis lengkap pada **Bab 21 `docs/PRD_PKDI_v2.0.md`** ("Batasan Khusus Tugas Akhir Mahasiswa"), yang menegaskan fokus implementasi pada Modul Gudang serta batas integrasinya dengan sistem perusahaan. |
| `AGENTS.md` | Tidak ditemukan di root workspace | Panduan arah visual proyek saat ini berada di `DESIGN.md` pada root workspace. |
| Dokumentasi PRD 1.0 & fase sebelumnya | Tersedia lengkap | `docs/PRD_PKDI_Sistem_Operasional_PT.md`, `docs/project-audit.md`, `docs/data-dictionary.md`, `docs/role-permission-matrix.md`, `docs/status-lifecycle.md`, `docs/system-architecture.md`, `docs/qa-test-results.md`, dan `DESIGN.md`. |

---

## 2. Audit Lingkungan Teknis dan Struktur Folder

### 2.1 Stack dan Lingkungan Lokal
- **Backend:** PHP 8.4.27 (XAMPP), Laravel 13 (`laravel/framework: ^13.0`), PHPUnit 12.5, Laravel Pint 1.27.
- **Frontend:** Blade templates, Tailwind CSS v4 (`@tailwindcss/vite`), Vite 7, `@fontsource/inter`, `choices.js`, `flatpickr`, dan sprite ikon SVG lokal (`public/icons.svg`).
- **Database:** MySQL / MariaDB XAMPP (`sistem_pkdi` di `.env`) untuk operasional lokal, serta SQLite `:memory:` pada `phpunit.xml` untuk pengujian otomatis.
- **Catatan Layanan MySQL Saat Audit:** Layanan `mysqld` XAMPP pada port `3306` sedang tidak menyala saat pemeriksaan dilakukan (`ERROR 2002`). Layanan MySQL perlu dijalankan sebelum mengeksekusi migrasi non-destruktif pada Phase 1.

### 2.2 Struktur Folder Aplikasi Saat Ini
- `app/Models/` (17 model Eloquent): `User`, `ClientCompany`, `ServiceOrder`, `OrderStatusHistory`, `VehicleGateLog`, `WarehouseInspection`, `ServiceProcess`, `ServiceResult`, `ServiceTariff`, `Invoice`, `InvoiceItem`, `Payment`, `DeliveryOrder`, `OperationalExpense`, `CashbookEntry`, `AppNotification`, `AuditLog`.
- `app/Http/Controllers/` (10 controller):
  - Autentikasi & Umum: `Auth/AuthenticatedSessionController`, `Auth/RegisteredClientController`, `SettingsController`, `StatusHeartbeatController`.
  - Modul Peran (`app/Http/Controllers/Preview/`): `ClientPreviewController`, `GatePreviewController`, `WarehousePreviewController`, `FinancePreviewController`, `OwnerPreviewController`.
- `app/Services/` (3 service): `OrderWorkflowService`, `BillingAndExitWorkflowService`, `ReportExportService`.
- `app/Policies/` (5 policy): `ClientCompanyPolicy`, `DeliveryOrderPolicy`, `InvoicePolicy`, `OperationalExpensePolicy`, `ServiceOrderPolicy`.
- `app/Http/Middleware/`: `EnsureUserRole`, `EnsureUserIsActive`.
- `app/Support/`: `AuditLogger`, `OrderPresenter`, `PkdiDemoData`.
- `app/Console/Commands/`: `SetupInternalAccountsCommand`.
- `database/migrations/`: 4 file migrasi (termasuk `0001_01_01_000003_create_pkdi_operational_and_financial_tables.php` yang memuat seluruh 15 tabel operasional & keuangan PRD 1.0).
- `database/seeders/`: `DatabaseSeeder` (membuat 5 akun role termasuk Satpam + 1 perusahaan client tanpa data transaksi dummy) dan `TestScenarioSeeder` (data skenario pengujian otomatis).
- `resources/views/`: `auth/` (2 view), `client/` (7 view), `gate/` (6 view), `warehouse/` (6 view), `finance/` (8 view), `owner/` (5 view), `settings/` (1 view), `components/` (34 komponen Blade reusable).
- `public/Assets/img/`: `logo.png` dan `background.jpeg` (sudah diterapkan pada login, registrasi, sidebar dashboard, favicon, dan pratinjau surat jalan).
- `tests/Feature/`: 27 skenario pengujian otomatis lintas Phase 3 sampai Phase 7.

---

## 3. Audit Database dan Relasi (Kesenjangan terhadap PRD 2.0 & Scope Gudang)

### 3.1 Struktur yang Sudah Baik dan Relevan
- Relasi tenant `client_company_id` pada `users`, `service_orders`, `invoices`, dan `payments` sudah berjalan dengan scope `ServiceOrder::scopeVisibleTo()`.
- Data permintaan awal Client (`service_orders`) sudah dipisahkan dari pemeriksaan aktual (`warehouse_inspections`), log tahapan pengerjaan (`service_processes`), dan hasil akhir pekerjaan (`service_results`).
- Tabel pendukung audit dan pelacakan (`order_status_histories`, `notifications`, `audit_logs`) sudah terpasang rapi dengan transaksi database (`DB::transaction`).

### 3.2 Masalah dan Kesenjangan Skema Database
1. **Belum ada Master Produk Drum (`drum_products`):**
   - `service_orders.service_category` dan `cargo_type` masih berupa string teks bebas.
   - PRD 2.0 mewajibkan dropdown master produk yang minimal mencakup: `Drum besi`, `Poligen 150 liter`, `Poligen 120 liter`, dan `Jerigen`.
2. **Belum ada Master Paket Layanan Rekondisi (`reconditioning_services`):**
   - `service_orders.service_type` masih menggunakan teks bebas (`Diperbarui` / `Diperbaiki`).
   - PRD 2.0 mewajibkan dropdown tunggal berisi tepat 3 paket: `Laundry`, `Laundry + Cat`, dan `Laundry + Cat + Sablon` (bukan checkbox terpisah).
3. **Belum ada pemisahan jenis transaksi (`transaction_type`) pada `service_orders`:**
   - Belum ada pembedaan antara `DRUM_SALE` (Penjualan Drum) dan `RECONDITIONING` (Jasa Rekondisi Drum).
4. **Kolom kendaraan masih wajib (`NOT NULL`) pada `service_orders`:**
   - Kolom `vehicle_type` dan `license_plate` pada `service_orders` tidak nullable dan divalidasi wajib saat Client mengajukan pesanan.
   - PRD 2.0 menyatakan informasi kendaraan/driver tidak wajib diisi Client; bila diperlukan, data dilengkapi pihak internal.
5. **Kolom dokumen & jadwal kebutuhan pesanan PRD 2.0 belum tersedia pada `service_orders`:**
   - Belum ada kolom `po_number`, `po_attachment_path`, `needed_date`, `specification_notes`, serta jalur status penagihan terpisah `billing_status` (`NOT_READY`, `READY_TO_BILL`, `INVOICED`, `VOIDED`).
6. **Belum ada tabel/entitas khusus Penerimaan Fisik Drum (`warehouse_receipts`) dan Penyortiran Drum (`warehouse_sortings`):**
   - Scope Tugas Akhir Modul Gudang mewajibkan pemisahan jelas untuk tahap: penerimaan drum, pemeriksaan kuantitas & kondisi, penyortiran drum (layak proses, perlu perbaikan/penanganan khusus, tidak layak/rusak berat), proses pekerjaan rekondisi, pembaruan progres, pencatatan hasil akhir pekerjaan, dan persiapan/penyerahan penjualan drum.
   - Belum tersedia kolom/lampiran dokumentasi foto penerimaan, pemeriksaan/penyortiran, dan hasil kerja Gudang.
7. **Struktur penagihan belum mendukung antrean transaksi siap-tagih (`billable_transactions`) untuk invoice gabungan:**
   - Saat ini `invoices.service_order_id` bersifat `NOT NULL` (mengunci 1 pesanan = 1 invoice).
   - PRD 2.0 mensyaratkan satu transaksi operasional menghasilkan satu entri antrean siap ditagihkan (`billable_transactions`) dengan snapshot kuantitas, produk, paket layanan, dan jenis transaksi agar modul Keuangan dapat menggabungkan beberapa transaksi milik Client yang sama ke satu invoice tanpa risiko penagihan ganda.
8. **Kolom administrasi Client belum lengkap:**
   - `client_companies` sudah memiliki kolom `npwp`, tetapi belum memiliki `nib_number`, `nib_file_path`, dan `documents_status` untuk kesiapan integrasi administrasi Client PRD 2.0.
9. **Tabel warisan PRD 1.0 (`vehicle_gate_logs`, `delivery_orders`):**
   - Masih ada di database. Pada Phase 1 tabel ini **tidak akan dihapus secara destruktif** agar data lama tetap terjaga, tetapi dikeluarkan dari ketergantungan wajib alur operasional Gudang.

---

## 4. Audit Role, Permission, Routes, dan Controller

### 4.1 Role dan Hak Akses Saat Ini
- `User::ROLES` saat ini mendefinisikan 5 role: `client`, `satpam`, `kepala_gudang`, `keuangan`, `owner`.
- Sesuai PRD 2.0, role aktif tahap awal hanya **4 role**: `client`, `kepala_gudang`, `keuangan`, `owner`. Role `satpam` dikeluarkan dari alur digital; `super_admin` dan `live_chat` tidak diimplementasikan pada tahap ini.
- `EnsureUserRole` middleware dan 5 Policy Laravel (`ServiceOrderPolicy`, `DeliveryOrderPolicy`, `InvoicePolicy`, `ClientCompanyPolicy`, `OperationalExpensePolicy`) sudah menerapkan pemeriksaan otorisasi di sisi server (`403 Forbidden`), namun `ServiceOrderPolicy` dan `DeliveryOrderPolicy` masih memasukkan `User::ROLE_SATPAM`.

### 4.2 Routes dan Controller Saat Ini
- `routes/web.php` memiliki 5 grup route:
  1. `/client/*` (`ClientPreviewController`, 10 route)
  2. `/gate/*` (`GatePreviewController`, 8 route khusus `role:satpam`) -> **Harus dinonaktifkan dari alur aktif**.
  3. `/warehouse/*` (`WarehousePreviewController`, 10 route khusus `role:kepala_gudang`) -> **Fokus utama revisi Modul Gudang**.
  4. `/finance/*` (`FinancePreviewController`, 17 route khusus `role:keuangan`) -> **Dipertahankan tanpa memperluas di luar scope Tugas Akhir, namun disiapkan membaca struktur integrasi hasil Gudang**.
  5. `/owner/*` (`OwnerPreviewController`, 6 route khusus `role:owner`) -> **Dipertahankan sebagai monitoring read-only dan dibersihkan dari ketergantungan Satpam**.
- Catatan penamaan: seluruh controller masih berada di namespace `App\Http\Controllers\Preview\*` meskipun seluruh halaman sudah terhubung penuh ke database MySQL/Eloquent, dan `WarehousePreviewController` masih memiliki fallback ke `PkdiDemoData::findOrderOrNull()`. Fallback demo pada alur produksi perlu dibersihkan agar tidak pernah memunculkan data dummy seolah-olah data perusahaan.

---

## 5. Audit Ketergantungan Fitur Satpam (33 File Terdampak)

Hasil pemindaian rekursif pada `app/`, `config/`, `routes/`, `database/`, dan `resources/views/` mengidentifikasi **33 file** yang memiliki ketergantungan terhadap Satpam / Pos Gerbang:

### 5.1 Ketergantungan Kritis yang Memblokir Alur Gudang
1. `app/Services/OrderWorkflowService.php` (30 referensi):
   - `createOrder()` menetapkan status awal `order_status = 'WAITING_GATE_IN'`, `gate_status = 'EXPECTED_IN'`, `warehouse_status = 'WAITING_ARRIVAL'` dan hanya mengirim notifikasi ke `User::ROLE_SATPAM`.
   - `inspectWarehouse()`, `recordProcess()`, dan `completeService()` **menolak proses Gudang** jika `gate_status !== 'CHECKED_IN_APPROVED'`.
2. `app/Http/Controllers/Preview/WarehousePreviewController.php` (3 referensi):
   - `inspectionsIndex()` hanya menampilkan pesanan dengan `gate_status === 'CHECKED_IN_APPROVED'`.
   - `deliveryOrderStore()` meneruskan status pesanan ke Pos Gerbang Keluar Satpam.
3. `resources/views/warehouse/orders/show.blade.php` (3 referensi) & `resources/views/warehouse/inspections/index.blade.php` (1 referensi):
   - Memeriksa `$gateInApproved = $order['gate_status'] === 'CHECKED_IN_APPROVED'`, menonaktifkan seluruh tombol aksi Gudang, dan menampilkan peringatan "Menunggu Persetujuan Masuk dari Satpam".
4. `app/Services/BillingAndExitWorkflowService.php` (21 referensi):
   - Mengarahkan pesanan lunas ke `WAITING_EXIT_DOCUMENT` -> `WAITING_GATE_OUT` -> `COMPLETED` yang menuntut aksi `inspectGateOut()` dari Satpam.

### 5.2 Ketergantungan Struktur, Role, Konfigurasi, dan Tampilan
- **Model & Policy:** `app/Models/User.php`, `app/Models/ServiceOrder.php`, `app/Policies/ServiceOrderPolicy.php`, `app/Policies/DeliveryOrderPolicy.php`.
- **Route, Controller, Presenter, Heartbeat:** `routes/web.php`, `app/Http/Controllers/Preview/GatePreviewController.php`, `app/Http/Controllers/Preview/ClientPreviewController.php`, `app/Http/Controllers/StatusHeartbeatController.php`, `app/Support/OrderPresenter.php`.
- **Konfigurasi, Seeder, Factory, Command:** `config/pkdi.php` (role `satpam`, navigasi `satpam`, 4 status gerbang, teks News Ticker), `database/seeders/DatabaseSeeder.php`, `database/seeders/TestScenarioSeeder.php`, `database/factories/UserFactory.php`, `database/factories/ServiceOrderFactory.php`, `app/Console/Commands/SetupInternalAccountsCommand.php`.
- **Tampilan Blade:** `resources/views/gate/*` (6 file), `resources/views/client/dashboard.blade.php`, `resources/views/client/orders/show.blade.php`, `resources/views/components/ui/news-ticker.blade.php`, `resources/views/components/ui/order-stepper.blade.php`, `resources/views/owner/dashboard.blade.php`, `resources/views/owner/audit-logs.blade.php`, `resources/views/owner/transactions/index.blade.php`, `resources/views/warehouse/delivery-orders/show.blade.php`.

---

## 6. Audit Dashboard, Modul Gudang, dan Temuan Anti-Slop

Pemeriksaan mendalam terhadap tampilan dan interaksi Modul Gudang saat ini menemukan beberapa ketidaksesuaian terhadap PRD 2.0, Scope Tugas Akhir, dan 6 skill Anti-Slop:

1. **Ketidaksesuaian Alur Modul Gudang (`warehouse/*`):**
   - `warehouse/dashboard.blade.php` hanya menampilkan 4 tab berbasis PRD 1.0 (`Menunggu Periksa`, `Sedang Diproses`, `Siap Surat Jalan`, `Selesai / Ke Keuangan`) dengan kolom Nopol Kendaraan Masuk. Belum mendukung antrean **Pesanan Baru / Ditinjau**, **Menunggu Penerimaan Fisik (Rekondisi)**, **Penyortiran & Rekondisi Aktif**, **Persiapan Penjualan Drum**, serta **Siap Diserahkan/Dikirim**.
   - `warehouse/orders/show.blade.php` belum membedakan alur **Penjualan Drum** (cek kesiapan, persiapan produk, pencatatan barang disiapkan & diserahkan) dengan **Jasa Rekondisi** (peninjauan/klarifikasi, penerimaan fisik drum, pemeriksaan kuantitas & kondisi, penyortiran drum, proses rekondisi sesuai paket `Laundry` / `Laundry + Cat` / `Laundry + Cat + Sablon`, pencatatan hasil akhir & foto dokumentasi).
   - Belum ada fitur **Riwayat Pekerjaan & Laporan Gudang** (filter jenis transaksi, paket layanan, status, periode, serta cetak/ekspor laporan operasional Gudang sesuai Bab 21.2 & 21.5 PRD 2.0).
   - Belum ada tombol/endpoint yang aman untuk mengunduh/melihat lampiran dokumen PO & foto pesanan Client maupun foto dokumentasi Gudang dengan otorisasi Policy.
2. **Temuan Pelanggaran Anti-Slop pada Modul Gudang & Dokumentasi Visual:**
   - **R-23 / R-38 (Filler Data in Fields):** `resources/views/warehouse/orders/show.blade.php` baris 53 menyisipkan teks default hardcoded `'Kondisi drum standar, sebagian penyok ringan.'` pada textarea kondisi drum. Nilai form baru harus kosong atau menggunakan placeholder yang jelas.
   - **R-27 (Missing Empty / Filter / Error States):** `warehouse/inspections/index.blade.php` dan `warehouse/processes/index.blade.php` belum memiliki empty state yang informatif (hanya loop `@foreach` tanpa `@forelse` dan tanpa pesan penyebab serta aksi lanjut).
   - **R-24 (Dead / Irrelevant Navigation):** Navigasi Kepala Gudang di `config/pkdi.php` masih mencantumkan menu `Surat Jalan` yang terkait alur Gate-Out Satpam, sementara menu inti Scope Tugas Akhir seperti **Penerimaan & Penyortiran**, **Pekerjaan Gudang**, dan **Laporan Gudang** belum tersusun sesuai alur PRD 2.0.
   - **R-05 / C-3 (Content-Driven Composition):** Halaman `warehouse/orders/show.blade.php` menampilkan seluruh form (pemeriksaan, progres, hasil akhir) secara serentak dalam kondisi disabled sehingga berat dipindai di ponsel maupun desktop. Perlu ditata sesuai tahap aktif pesanan dan jenis transaksinya.
   - **R-37 (`DESIGN.md` Usang):** `DESIGN.md` masih mencantumkan 5 role (termasuk Satpam) dan aturan input teks bebas untuk kategori/layanan, sedangkan PRD 2.0 menetapkan 4 role dan dropdown master untuk Produk Drum serta 3 Paket Layanan Rekondisi.
   - **R-39 (Reduced Motion):** Pada `resources/css/app.css`, animasi `.pkdi-dot-pulse` dan `.pkdi-ticker__track` perlu dipastikan ikut berhenti pada `@media (prefers-reduced-motion: reduce)`.

---

## 7. Klasifikasi Fitur: Dipertahankan, Direvisi, Dikeluarkan, dan Ditunda

| Kategori | Fitur / Komponen | Keputusan |
|---|---|---|
| **Dipertahankan** | Autentikasi Laravel, hashing password, middleware `auth`, `active.user`, `role:*`, Policy server-side, isolasi tenant `client_company_id`, `AuditLogger`, `AppNotification`, `StatusHeartbeatController`, komponen Blade `<x-ui.*>`, halaman Login & aset logo/background PKDI, serta struktur dasar modul Keuangan & Owner yang sudah berjalan. | Dipertahankan tanpa dibangun ulang dari nol. |
| **Dikeluarkan dari Alur Aktif** | Role `satpam` dari alur operasional, route aktif `/gate/*`, blokir `gate_status === CHECKED_IN_APPROVED`, notifikasi ke Satpam, ketergantungan wajib Gate-Out untuk menyelesaikan pesanan, serta fallback `PkdiDemoData` pada controller produksi. | Dinonaktifkan dari workflow aktif tanpa menghapus tabel/data lama secara destruktif. |
| **Direvisi & Diprioritaskan (Scope Tugas Akhir)** | 4 role aktif (`client`, `kepala_gudang`, `keuangan`, `owner`); master dropdown Produk Drum (4 produk awal) dan Paket Layanan Rekondisi (tepat 3 paket); 2 jenis transaksi (`DRUM_SALE` & `RECONDITIONING`); pemisahan 3 jalur status (`order_status`, `billing_status`, `payment_status`); **Modul Gudang penuh** (Dashboard Kepala Gudang, daftar & detail pesanan Client, penerimaan drum, pemeriksaan kuantitas & kondisi, penyortiran drum, proses rekondisi & pembaruan progres, pencatatan hasil akhir + foto dokumentasi, persiapan & penyerahan penjualan drum, riwayat & laporan Gudang); serta struktur integrasi `billable_transactions` siap-tagih. | Fokus implementasi Phase 1 sampai Phase 4. |
| **Ditunda (Di Luar MVP / Scope TA)** | Role Super Admin & UI manajemen seluruh akun internal, Customer Service Live Chat, Payment Gateway / QRIS dinamis / Virtual Account, modul stok/inventaris penuh, dan pembangunan ulang fitur Keuangan di luar kebutuhan integrasi `billable_transactions`. | Tidak diimplementasikan pada tahap ini. |

---

## 8. Daftar Kebutuhan Bisnis `PERLU_VALIDASI_PKDI` dan Baseline Alur Sementara

Sesuai PRD 2.0 Bab 19 dan prinsip tidak membuat asumsi permanen sebelum konfirmasi perusahaan, ketujuh poin berikut tetap ditandai `PERLU_VALIDASI_PKDI`. Agar alur kerja sistem berjalan utuh, logis, dan tidak ada tahapan yang terlewat atau datanya asal-asalan selama tahap pengerjaan dan demonstrasi Tugas Akhir, ditetapkan **Baseline Alur Sementara** yang fleksibel (mudah diubah lewat master data atau konfigurasi tanpa membongkar skema database):

| No. | Poin `PERLU_VALIDASI_PKDI` | Baseline Alur Sementara (Operasional & Integrasi) |
|---|---|---|
| 1 | **Master Produk & Spesifikasi** (daftar produk selain 4 jenis awal serta detail ukuran/tutup/warna) | Menggunakan tabel master `drum_products` berisi 4 produk hasil riset (`Drum besi`, `Poligen 150 liter`, `Poligen 120 liter`, `Jerigen`) ditambah kolom `specification_notes` pada pesanan agar Client dapat mengisi catatan tutup/warna tanpa mengarang katalog fiktif. |
| 2 | **Stok Penjualan Drum** (cukup status kesiapan atau perlu kartu stok penuh) | Kepala Gudang memeriksa kesiapan stok secara operasional (`Tersedia / Siap Disiapkan` atau `Perlu Klarifikasi`) lalu mencatat jumlah diminta, jumlah disiapkan, dan jumlah diserahkan secara akurat tanpa mengklaim modul inventaris penuh. |
| 3 | **Pemicu Antrean Penagihan (`READY_TO_BILL`)** (saat `READY` atau setelah `DELIVERED`) | Transaksi masuk antrean `billable_transactions` (`READY_TO_BILL`) tepat saat **Hasil Akhir Pekerjaan Rekondisi** atau **Hasil Persiapan/Penyerahan Penjualan Drum** dikonfirmasi Kepala Gudang (`READY`), karena kuantitas aktual yang layak sudah final pada titik tersebut. |
| 4 | **Prosedur Penyerahan / Pengiriman & Data Armada** (diantar PKDI, diambil Client, dan pencatat nopol/driver) | Menyediakan pilihan metode penyerahan (`DIANTAR_PKDI` atau `DIAMBIL_CLIENT`) pada tahap serah terima Gudang, dengan kolom opsional `vehicle_type`, `license_plate`, `driver_name`, dan `delivery_notes` yang diisi oleh **Kepala Gudang (internal)** saat pengiriman/penyerahan. |
| 5 | **Penanganan Drum Tidak Layak / Rusak Berat saat Penyortiran** | Pada tahap **Penyortiran Drum**, jumlah harus seimbang secara matematis (`layak proses + penanganan khusus + tidak layak = jumlah aktual diterima`). Pada hasil akhir, hanya kuantitas yang benar-benar selesai layak (`completed_quantity`) yang menjadi `billable_quantity`, sedangkan `unprocessed_or_rejected_quantity` dicatat terpisah beserta alasannya untuk dikembalikan ke Client. |
| 6 | **Aturan Campuran Invoice Gabungan** (boleh gabung Penjualan + Rekondisi atau dipisah) | Tabel `billable_transactions` menyimpan kolom `transaction_type` (`DRUM_SALE` / `RECONDITIONING`) pada setiap baris transaksi sehingga mendukung penggabungan beberapa transaksi milik Client yang sama maupun pemisahan filter per jenis transaksi bila PKDI mewajibkannya. |
| 7 | **Verifikasi Administrasi Client (NPWP & NIB)** | Profil Client menyediakan input `npwp`, `nib_number`, unggah berkas `nib_file_path`, serta indikator status kelengkapan (`COMPLETE` / `INCOMPLETE`). Client tetap dapat mengajukan pesanan sembari melengkapi dokumen agar alur uji coba tidak terblokir. |

---

## 9. Rencana Revisi Bertahap (Phase 1 sampai Phase 4)

### Phase 1: Perbaikan Struktur Role, Route, dan Database (Non-Destruktif)
1. Menetapkan 4 role aktif (`client`, `kepala_gudang`, `keuangan`, `owner`), menonaktifkan alur Satpam dari `config/pkdi.php`, `routes/web.php`, `ServiceOrderPolicy`, `DeliveryOrderPolicy`, `DatabaseSeeder`, dan `SetupInternalAccountsCommand` (tanpa menghapus data historis di database).
2. Menambahkan migrasi baru non-destruktif untuk:
   - Tabel master `drum_products` (seed 4 produk: `Drum besi`, `Poligen 150 liter`, `Poligen 120 liter`, `Jerigen`) dan `reconditioning_services` (seed tepat 3 paket: `Laundry`, `Laundry + Cat`, `Laundry + Cat + Sablon`).
   - Penambahan kolom PRD 2.0 pada `service_orders` (`transaction_type`, `drum_product_id`, `reconditioning_service_id`, `po_number`, `po_attachment_path`, `needed_date`, `specification_notes`, `billing_status`, serta menjadikan `vehicle_type` & `license_plate` nullable).
   - Tabel baru Modul Gudang: `warehouse_receipts` (penerimaan fisik drum + foto), `warehouse_sortings` (penyortiran drum: layak proses, perlu perbaikan khusus, tidak layak/rusak berat + catatan & foto), dan `billable_transactions` (antrean transaksi siap ditagihkan dengan snapshot produk, paket, jenis transaksi, kuantitas hasil, dan status penagihan).
   - Penambahan kolom opsional `photo_path` pada `warehouse_inspections`, `service_processes`, dan `service_results`, serta kolom administrasi `nib_number`, `nib_file_path`, `documents_status` pada `client_companies`.
3. Memperbarui `OrderWorkflowService::createOrder()` agar pesanan baru langsung berstatus `SUBMITTED` / `UNDER_REVIEW` menuju antrean **Kepala Gudang** (bebas ketergantungan Satpam), serta memperbarui form pengajuan pesanan Client (`client/orders/create.blade.php`) agar mendukung pilihan 2 jenis transaksi dan dropdown master standar untuk pengujian & demo end-to-end.
4. Memperbarui `DESIGN.md` agar selaras dengan 4 role aktif dan aturan dropdown master PRD 2.0.

### Phase 2: Implementasi Lengkap Modul Gudang (Scope Tugas Akhir)
1. Merancang ulang **Dashboard Kepala Gudang** (`warehouse/dashboard.blade.php`) berbasis antrean kerja operasional nyata:
   - Pesanan Baru / Perlu Ditinjau (`SUBMITTED`, `UNDER_REVIEW`, `NEEDS_CLARIFICATION`)
   - Menunggu Penerimaan Fisik (`WAITING_RECEIPT` khusus Rekondisi)
   - Pemeriksaan, Penyortiran & Proses Rekondisi (`RECEIVED`, `IN_PROGRESS`)
   - Persiapan Penjualan Drum (`ACCEPTED`, `PREPARING`)
   - Siap Diserahkan / Dikirim & Selesai (`READY`, `IN_DELIVERY`, `DELIVERED`, `COMPLETED`)
2. Membangun halaman **Daftar Pesanan & Antrean Gudang** dengan pencarian, filter jenis transaksi (`Penjualan Drum` / `Jasa Rekondisi`), filter paket layanan, filter status, dan empat state UI (`R-27`).
3. Merombak **Halaman Detail & Eksekusi Pesanan Gudang** (`warehouse/orders/show.blade.php`) secara dinamis berdasarkan jenis transaksi dan tahap aktif:
   - **Tinjauan Pesanan:** Lihat data awal Client, PO, spesifikasi, dan unduhan aman lampiran/foto (dilindungi Policy); aksi Terima untuk Diproses, Minta Klarifikasi (wajib alasan), atau Tolak (wajib alasan).
   - **Jalur Jasa Rekondisi Drum:**
     1. Pencatatan **Penerimaan Fisik Drum** (`warehouse_receipts`: tanggal/waktu terima, jumlah fisik diterima, kondisi awal, dokumentasi foto, tanpa menimpa data awal Client).
     2. **Pemeriksaan Kuantitas, Kondisi & Penyortiran Drum** (`warehouse_inspections` & `warehouse_sortings`: jumlah layak proses, jumlah butuh penanganan khusus, jumlah tidak layak/reject, catatan selisih).
     3. **Proses Pengerjaan Rekondisi & Pembaruan Progres** (`service_processes` sesuai paket `Laundry` / `Laundry + Cat` / `Laundry + Cat + Sablon` + dokumentasi foto opsional).
     4. **Pencatatan Hasil Akhir Pekerjaan** (`service_results`: kuantitas selesai layak, kuantitas tidak selesai/tidak layak, catatan hasil, foto hasil) -> menandai status `READY` dan mengirim transaksi ke antrean penagihan.
     5. **Konfirmasi Penyerahan / Pengiriman** (`READY` -> `IN_DELIVERY` / `DELIVERED` -> `COMPLETED`, catatan pengiriman & info kendaraan opsional yang diisi internal).
   - **Jalur Penjualan Drum:**
     1. Tinjauan ketersediaan/kesiapan produk dan mulai **Persiapan Produk** (`PREPARING`).
     2. Pencatatan kuantitas disiapkan dan **Hasil Persiapan / Penyerahan** (`READY` -> `DELIVERED` -> `COMPLETED`) beserta catatan pengiriman/penyerahan.
4. Membangun halaman **Riwayat Pekerjaan & Laporan Operasional Gudang** (`/warehouse/reports`) lengkap dengan filter periode, jenis transaksi, paket layanan, serta ekspor PDF Laporan Operasional Gudang.
5. Menegakkan akses aman berkas lampiran/foto melalui endpoint controller terotorisasi Policy (tidak pernah diakses publik langsung).

### Phase 3: Integrasi Data Hasil Pekerjaan ke Antrean Penagihan
1. Saat Kepala Gudang mengonfirmasi hasil akhir pekerjaan rekondisi atau penyelesaian persiapan/penyerahan penjualan drum, sistem secara atomik (`DB::transaction`) membuat 1 record `billable_transactions` dengan `billing_status = 'READY_TO_BILL'` beserta snapshot `transaction_type`, `product_name`, `reconditioning_service_name`, `billable_quantity`, `unit`, dan `service_order_id` unik (mencegah duplikasi penagihan).
2. Memastikan modul Keuangan & Owner yang sudah ada dapat membaca `billable_transactions` dan status 3 jalur baru (`order_status`, `billing_status`, `payment_status`) secara konsisten tanpa membangun ulang fitur Keuangan di luar scope Tugas Akhir.
3. Memperbarui tracker ringkas pada Portal Client (`order-stepper.blade.php`) agar mengikuti ringkasan status PRD 2.0 (menyembunyikan detail/catatan internal Gudang dari Client).

### Phase 4: Pengujian Menyeluruh, Dokumentasi, dan Anti-Slop Delivery Gate
1. Menjalankan dan memperbarui suite pengujian otomatis PHPUnit untuk memverifikasi:
   - Validasi form (frontend & backend) termasuk dropdown master produk dan 3 paket rekondisi.
   - Relasi database Eloquent dan integritas transaksi (`DB::transaction`).
   - Hak akses 4 role aktif, isolasi tenant Client, serta pemblokiran akses lintas-role dan akses route Satpam yang sudah dinonaktifkan.
   - Alur lengkap Penjualan Drum dan Jasa Rekondisi (penerimaan drum, pemeriksaan & penyortiran, proses rekondisi, perubahan 3 jalur status, pencatatan hasil akhir, dan pembentukan `billable_transactions` tunggal).
   - Keamanan unduhan dokumen/foto terotorisasi.
   - Audit responsif desktop/tablet/mobile, kontras warna (`contrast-check.py`), dan seluruh 4 blok **Anti-Slop Delivery Gate**.
2. Mendokumentasikan seluruh perubahan di `CHANGELOG.md` dan menyusun laporan akhir `IMPLEMENTATION_REPORT.md`.
