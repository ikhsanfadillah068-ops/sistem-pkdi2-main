<?php

return [
    'name' => 'Sistem Operasional PKDI',
    'brand' => [
        'short' => 'PKDI',
        'company' => 'PT Pulo Kencana Drum Industri',
        'accent_subtitle' => 'Operasional Gudang & Layanan Drum',
        'footer' => 'PT Pulo Kencana Drum Industri · Sistem Manajemen Operasional & Keuangan',
    ],

    'announcements' => [
        'Selamat datang di Sistem Manajemen Pengangkutan Limbah, Pengolahan Drum, dan Keuangan PT Pulo Kencana Drum Industri (PKDI)',
        'Layanan operasional PKDI mencakup Pengangkutan Limbah dan Pengolahan / Rekondisi Drum (Laundry, Laundry + Cat, Laundry + Cat + Sablon)',
        'Setiap permintaan Client langsung masuk ke antrean operasional Kepala Gudang tanpa perantara persetujuan gerbang',
        'Unggah dokumen PO dan bukti transfer melalui Portal Client untuk memperlancar pemrosesan dan verifikasi',
    ],

    'transaction_types' => [
        'RECONDITIONING' => 'Rekondisi Drum',
        'WASTE_TRANSPORT' => 'Pengangkutan Limbah',
        'DRUM_SALE' => 'Pengangkutan Limbah',
    ],

    'delivery_methods' => [
        'DIANTAR_PKDI' => 'Diantar Armada PKDI',
        'DIAMBIL_CLIENT' => 'Diambil oleh Client',
    ],

    'reconditioning_stage_presets' => [
        'Laundry' => [
            'Pencucian Interior & Eksterior',
            'Pembilasan & Netralisasi Residu',
            'Pengeringan & Uji Kebocoran',
            'Quality Control Akhir',
        ],
        'Laundry + Cat' => [
            'Pencucian & Pembersihan Karat',
            'Pengeringan & Perbaikan Penyok',
            'Pengecatan Primer & Warna',
            'Pengeringan Cat & Quality Control',
        ],
        'Laundry + Cat + Sablon' => [
            'Pencucian & Pembersihan Karat',
            'Perbaikan Fisik & Pengecatan',
            'Penyablonan Logo / Identitas',
            'Finishing & Quality Control Akhir',
        ],
        'Pengangkutan Limbah' => [
            'Penimbangan & Verifikasi Kemasan',
            'Penataan Area Penampungan Gudang',
            'Pemeriksaan Akhir Penerimaan Limbah',
        ],
    ],

    'roles' => [
        'client' => [
            'key' => 'client',
            'label' => 'Client',
            'user_name' => 'Budi Santoso',
            'user_initials' => 'BS',
            'user_email' => 'client@pkdi.co.id',
            'company' => 'PT Maju Jaya Kimia',
            'home_route' => 'client.dashboard',
        ],
        'kepala_gudang' => [
            'key' => 'kepala_gudang',
            'label' => 'Kepala Gudang',
            'user_name' => 'Hendra Wijaya',
            'user_initials' => 'HW',
            'user_email' => 'kepalagudang@pkdi.co.id',
            'company' => 'Divisi Gudang & Produksi PKDI',
            'home_route' => 'warehouse.dashboard',
        ],
        'keuangan' => [
            'key' => 'keuangan',
            'label' => 'Keuangan',
            'user_name' => 'Siti Lestari',
            'user_initials' => 'SL',
            'user_email' => 'keuangan@pkdi.co.id',
            'company' => 'Divisi Keuangan PKDI',
            'home_route' => 'finance.dashboard',
        ],
        'owner' => [
            'key' => 'owner',
            'label' => 'Owner',
            'user_name' => 'Kencana Gunawan',
            'user_initials' => 'KG',
            'user_email' => 'owner@pkdi.co.id',
            'company' => 'Direksi PT PKDI',
            'home_route' => 'owner.dashboard',
        ],
    ],

    'navigation' => [
        'client' => [
            [
                'label' => 'Portal Client',
                'items' => [
                    ['label' => 'Ringkasan Portal', 'route' => 'client.dashboard', 'active' => 'client.dashboard', 'icon' => 'dashboard'],
                    ['label' => 'Pesanan Saya', 'route' => 'client.orders.index', 'active' => 'client.orders.*', 'icon' => 'package'],
                    ['label' => 'Buat Pesanan Baru', 'route' => 'client.orders.create', 'active' => 'client.orders.create', 'icon' => 'plus'],
                    ['label' => 'Tagihan & Pembayaran', 'route' => 'client.invoices.index', 'active' => 'client.invoices.*', 'icon' => 'receipt'],
                ],
            ],
            [
                'label' => 'Perusahaan & Akun',
                'items' => [
                    ['label' => 'Profil Perusahaan', 'route' => 'client.company', 'active' => 'client.company', 'icon' => 'building'],
                    ['label' => 'Pengaturan Akun', 'route' => 'settings.edit', 'active' => 'settings.*', 'icon' => 'settings'],
                ],
            ],
        ],
        'kepala_gudang' => [
            [
                'label' => 'Operasional Gudang',
                'items' => [
                    ['label' => 'Antrean Gudang', 'route' => 'warehouse.dashboard', 'active' => 'warehouse.dashboard', 'icon' => 'warehouse'],
                    ['label' => 'Penerimaan & Penyortiran', 'route' => 'warehouse.inspections.index', 'active' => 'warehouse.inspections.*', 'icon' => 'clipboard-check'],
                    ['label' => 'Proses & Hasil Kerja', 'route' => 'warehouse.processes.index', 'active' => 'warehouse.processes.*', 'icon' => 'sliders'],
                    ['label' => 'Laporan Gudang', 'route' => 'warehouse.reports.index', 'active' => 'warehouse.reports.*', 'icon' => 'file-spreadsheet'],
                ],
            ],
            [
                'label' => 'Akun Petugas',
                'items' => [
                    ['label' => 'Pengaturan Akun', 'route' => 'settings.edit', 'active' => 'settings.*', 'icon' => 'settings'],
                ],
            ],
        ],
        'keuangan' => [
            [
                'label' => 'Tagihan & Pembayaran',
                'items' => [
                    ['label' => 'Ringkasan Keuangan', 'route' => 'finance.dashboard', 'active' => 'finance.dashboard', 'icon' => 'dashboard'],
                    ['label' => 'Antrean Hitung Tagihan', 'route' => 'finance.billable.index', 'active' => 'finance.billable.*', 'icon' => 'clipboard-check'],
                    ['label' => 'Invoice & Verifikasi Bayar', 'route' => 'finance.invoices.index', 'active' => 'finance.invoices.*', 'icon' => 'receipt'],
                    ['label' => 'Referensi Tarif Jasa', 'route' => 'finance.tariffs.index', 'active' => 'finance.tariffs.*', 'icon' => 'tag'],
                ],
            ],
            [
                'label' => 'Kas & Pelaporan',
                'items' => [
                    ['label' => 'Biaya Operasional', 'route' => 'finance.expenses.index', 'active' => 'finance.expenses.*', 'icon' => 'wallet'],
                    ['label' => 'Buku Transaksi Keuangan', 'route' => 'finance.cashbook.index', 'active' => 'finance.cashbook.*', 'icon' => 'table'],
                    ['label' => 'Laporan & Ekspor', 'route' => 'finance.reports.index', 'active' => 'finance.reports.*', 'icon' => 'file-spreadsheet'],
                ],
            ],
            [
                'label' => 'Akun Petugas',
                'items' => [
                    ['label' => 'Pengaturan Akun', 'route' => 'settings.edit', 'active' => 'settings.*', 'icon' => 'settings'],
                ],
            ],
        ],
        'owner' => [
            [
                'label' => 'Pemantauan PKDI',
                'items' => [
                    ['label' => 'Ringkasan Eksekutif', 'route' => 'owner.dashboard', 'active' => 'owner.dashboard', 'icon' => 'dashboard'],
                    ['label' => 'Pendapatan & Biaya', 'route' => 'owner.financial', 'active' => 'owner.financial', 'icon' => 'chart'],
                    ['label' => 'Transaksi Lintas Bagian', 'route' => 'owner.transactions.index', 'active' => 'owner.transactions.*', 'icon' => 'layers'],
                    ['label' => 'Jejak Audit Sistem', 'route' => 'owner.audit-logs', 'active' => 'owner.audit-logs', 'icon' => 'history'],
                ],
            ],
            [
                'label' => 'Akun Owner',
                'items' => [
                    ['label' => 'Pengaturan Akun', 'route' => 'settings.edit', 'active' => 'settings.*', 'icon' => 'settings'],
                ],
            ],
        ],
    ],

    'statuses' => [
        // Status operasional PRD 2.1
        'SUBMITTED' => ['label' => 'Diajukan', 'tone' => 'info'],
        'UNDER_REVIEW' => ['label' => 'Ditinjau Gudang', 'tone' => 'info'],
        'NEEDS_CLARIFICATION' => ['label' => 'Perlu Klarifikasi', 'tone' => 'warning'],
        'APPROVED' => ['label' => 'Disetujui Gudang', 'tone' => 'info'],
        'ACCEPTED' => ['label' => 'Disetujui Gudang', 'tone' => 'info'],
        'TRANSPORT_SCHEDULED' => ['label' => 'Pengangkutan Dijadwalkan', 'tone' => 'info'],
        'IN_TRANSIT' => ['label' => 'Dalam Perjalanan', 'tone' => 'warning'],
        'WAITING_RECEIPT' => ['label' => 'Menunggu Penerimaan Fisik', 'tone' => 'warning'],
        'RECEIVED' => ['label' => 'Diterima di PKDI', 'tone' => 'info'],
        'SORTING' => ['label' => 'Penyortiran Gudang', 'tone' => 'soft'],
        'PREPARING' => ['label' => 'Penanganan Limbah', 'tone' => 'soft'],
        'PROCESSING' => ['label' => 'Dalam Pengolahan', 'tone' => 'soft'],
        'IN_PROGRESS' => ['label' => 'Diproses Gudang', 'tone' => 'soft'],
        'PROCESS_COMPLETED' => ['label' => 'Pengolahan Selesai', 'tone' => 'success'],
        'READY_FOR_HANDOVER' => ['label' => 'Siap Diserahkan', 'tone' => 'success'],
        'READY' => ['label' => 'Siap Diserahkan', 'tone' => 'success'],
        'IN_DELIVERY' => ['label' => 'Dalam Pengiriman', 'tone' => 'info'],
        'DELIVERED' => ['label' => 'Diterima Client', 'tone' => 'success'],
        'COMPLETED' => ['label' => 'Selesai', 'tone' => 'success'],
        'REJECTED' => ['label' => 'Ditolak', 'tone' => 'danger'],
        'CANCELLED' => ['label' => 'Dibatalkan', 'tone' => 'neutral'],

        // Status warisan PRD 1.0 (dipertahankan untuk kompatibilitas baca data lama)
        'WAITING_GATE_IN' => ['label' => 'Menunggu Gudang', 'tone' => 'warning'],
        'GATE_IN_HELD' => ['label' => 'Perlu Klarifikasi', 'tone' => 'danger'],
        'WAITING_WAREHOUSE' => ['label' => 'Menunggu Gudang', 'tone' => 'info'],
        'WAREHOUSE_REVIEW' => ['label' => 'Ditinjau Gudang', 'tone' => 'info'],
        'SERVICE_COMPLETED' => ['label' => 'Siap Diserahkan', 'tone' => 'success'],
        'WAITING_FINANCE' => ['label' => 'Siap Ditagihkan', 'tone' => 'warning'],
        'INVOICE_ISSUED' => ['label' => 'Tagihan Terbit', 'tone' => 'soft'],
        'PAYMENT_PARTIAL' => ['label' => 'Bayar Sebagian', 'tone' => 'warning'],
        'PAID' => ['label' => 'Lunas', 'tone' => 'success'],
        'WAITING_EXIT_DOCUMENT' => ['label' => 'Siap Diserahkan', 'tone' => 'info'],
        'WAITING_GATE_OUT' => ['label' => 'Siap Diserahkan', 'tone' => 'info'],
        'GATE_OUT_HELD' => ['label' => 'Penyerahan Ditahan', 'tone' => 'danger'],
    ],

    'billing_statuses' => [
        'NOT_READY' => ['label' => 'Belum Ditagihkan', 'tone' => 'neutral'],
        'READY_TO_BILL' => ['label' => 'Siap Ditagihkan', 'tone' => 'warning'],
        'INVOICED' => ['label' => 'Sudah Ditagihkan', 'tone' => 'soft'],
        'VOIDED' => ['label' => 'Dibatalkan', 'tone' => 'danger'],
    ],

    'payment_statuses' => [
        'UNBILLED' => ['label' => 'Belum Ditagihkan', 'tone' => 'neutral'],
        'UNPAID' => ['label' => 'Menunggu Bayar', 'tone' => 'warning'],
        'PROOF_SUBMITTED' => ['label' => 'Menunggu Verifikasi', 'tone' => 'info'],
        'PROOF_REJECTED' => ['label' => 'Bukti Ditolak', 'tone' => 'danger'],
        'PARTIALLY_PAID' => ['label' => 'Bayar Sebagian', 'tone' => 'warning'],
        'PAID' => ['label' => 'Lunas', 'tone' => 'success'],
        'VOIDED' => ['label' => 'Dibatalkan', 'tone' => 'neutral'],
    ],
];
