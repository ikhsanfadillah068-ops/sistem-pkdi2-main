<?php

namespace Database\Seeders;

use App\Models\AppNotification;
use App\Models\AuditLog;
use App\Models\CashbookEntry;
use App\Models\ClientCompany;
use App\Models\DrumProduct;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\OperationalExpense;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\ReconditioningService;
use App\Models\ServiceOrder;
use App\Models\ServiceProcess;
use App\Models\ServiceResult;
use App\Models\ServiceTariff;
use App\Models\User;
use App\Models\WarehouseInspection;
use Illuminate\Database\Seeder;

class TestScenarioSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(DatabaseSeeder::class);

        $gudang = User::query()->where('role', User::ROLE_KEPALA_GUDANG)->firstOrFail();
        $keuangan = User::query()->where('role', User::ROLE_KEUANGAN)->firstOrFail();
        $owner = User::query()->where('role', User::ROLE_OWNER)->firstOrFail();
        $client = User::query()->where('role', User::ROLE_CLIENT)->firstOrFail();
        $company = ClientCompany::query()->firstOrFail();

        $drumBesi = DrumProduct::query()->where('code', 'PRD-DRUM-BESI')->firstOrFail();
        $poligen150 = DrumProduct::query()->where('code', 'PRD-POLIGEN-150')->firstOrFail();
        $laundry = ReconditioningService::query()->where('code', 'REC-LAUNDRY')->firstOrFail();
        $laundryCat = ReconditioningService::query()->where('code', 'REC-LAUNDRY-CAT')->firstOrFail();
        $laundryCatSablon = ReconditioningService::query()->where('code', 'REC-LAUNDRY-CAT-SABLON')->firstOrFail();

        $tariffRenewB200 = ServiceTariff::query()->updateOrCreate(
            ['tariff_code' => 'TRF-RENEW-B200'],
            [
                'name' => 'Rekondisi Drum Besi (Laundry + Cat)',
                'service_category' => 'Drum besi',
                'cargo_type' => 'Bekas Oli / Cat / Kimia Standar',
                'service_type' => 'Laundry + Cat',
                'unit' => 'Drum',
                'unit_price' => 85000,
                'effective_from' => '2026-01-01',
                'is_active' => true,
                'created_by_user_id' => $keuangan->id,
            ]
        );

        $tariffRepairB200 = ServiceTariff::query()->updateOrCreate(
            ['tariff_code' => 'TRF-REPAIR-B200'],
            [
                'name' => 'Rekondisi Drum Besi (Laundry)',
                'service_category' => 'Drum besi',
                'cargo_type' => 'Minyak / Resin / Pelarut',
                'service_type' => 'Laundry',
                'unit' => 'Drum',
                'unit_price' => 65000,
                'effective_from' => '2026-01-01',
                'is_active' => true,
                'created_by_user_id' => $keuangan->id,
            ]
        );

        ServiceTariff::query()->updateOrCreate(
            ['tariff_code' => 'TRF-RENEW-HDPE'],
            [
                'name' => 'Rekondisi Poligen 150 Liter (Laundry + Cat + Sablon)',
                'service_category' => 'Poligen 150 liter',
                'cargo_type' => 'Cairan Pembersih / Non-Korosif',
                'service_type' => 'Laundry + Cat + Sablon',
                'unit' => 'Unit',
                'unit_price' => 70000,
                'effective_from' => '2026-03-01',
                'is_active' => true,
                'created_by_user_id' => $keuangan->id,
            ]
        );

        $ordersConfig = [
            [
                'order_number' => 'senin-16/11/26-1',
                'transaction_type' => ServiceOrder::TYPE_RECONDITIONING,
                'drum_product_id' => $drumBesi->id,
                'reconditioning_service_id' => $laundryCat->id,
                'service_category' => 'Drum besi',
                'cargo_type' => 'Bekas Minyak Hidrolik',
                'service_type' => 'Laundry + Cat',
                'requested_quantity' => 50,
                'order_status' => 'COMPLETED',
                'warehouse_status' => 'COMPLETED',
                'billing_status' => 'INVOICED',
                'payment_status' => 'PAID',
                'submitted_at' => '2026-11-16 08:20:00',
                'completed_at' => '2026-11-16 16:05:00',
                'client_notes' => 'Pekerjaan rekondisi rutin bulanan.',
            ],
            [
                'order_number' => 'senin-16/11/26-2',
                'transaction_type' => ServiceOrder::TYPE_RECONDITIONING,
                'drum_product_id' => $drumBesi->id,
                'reconditioning_service_id' => $laundry->id,
                'service_category' => 'Drum besi',
                'cargo_type' => 'Bekas Pelarut Industri',
                'service_type' => 'Laundry',
                'requested_quantity' => 40,
                'order_status' => 'READY',
                'warehouse_status' => 'COMPLETED',
                'billing_status' => 'INVOICED',
                'payment_status' => 'PAID',
                'submitted_at' => '2026-11-16 10:15:00',
                'client_notes' => 'Pencucian bersih dan pengecekan tutup ring.',
            ],
            [
                'order_number' => 'selasa-17/11/26-3',
                'transaction_type' => ServiceOrder::TYPE_RECONDITIONING,
                'drum_product_id' => $drumBesi->id,
                'reconditioning_service_id' => $laundry->id,
                'service_category' => 'Drum besi',
                'cargo_type' => 'Bekas Oli Pelumas Mesin',
                'service_type' => 'Laundry',
                'requested_quantity' => 50,
                'order_status' => 'READY',
                'warehouse_status' => 'COMPLETED',
                'billing_status' => 'READY_TO_BILL',
                'payment_status' => 'UNPAID',
                'submitted_at' => '2026-11-17 07:30:00',
                'client_notes' => 'Pekerjaan selesai dan siap ditagihkan.',
            ],
            [
                'order_number' => 'selasa-17/11/26-4',
                'transaction_type' => ServiceOrder::TYPE_RECONDITIONING,
                'drum_product_id' => $drumBesi->id,
                'reconditioning_service_id' => $laundryCatSablon->id,
                'service_category' => 'Drum besi',
                'cargo_type' => 'Bekas Cat & Thinner',
                'service_type' => 'Laundry + Cat + Sablon',
                'requested_quantity' => 60,
                'order_status' => 'IN_PROGRESS',
                'warehouse_status' => 'IN_PROGRESS',
                'billing_status' => 'NOT_READY',
                'payment_status' => 'UNPAID',
                'submitted_at' => '2026-11-17 08:00:00',
                'client_notes' => 'Sablon logo perusahaan warna putih.',
            ],
            [
                'order_number' => 'selasa-17/11/26-5',
                'transaction_type' => ServiceOrder::TYPE_DRUM_SALE,
                'drum_product_id' => $poligen150->id,
                'reconditioning_service_id' => null,
                'service_category' => 'Poligen 150 liter',
                'cargo_type' => 'Produk Baru / Siap Jual',
                'service_type' => 'Penjualan Drum',
                'requested_quantity' => 80,
                'order_status' => 'UNDER_REVIEW',
                'warehouse_status' => 'PENDING_REVIEW',
                'billing_status' => 'NOT_READY',
                'payment_status' => 'UNPAID',
                'submitted_at' => '2026-11-17 08:45:00',
                'client_notes' => 'Mohon pastikan tutup ulir terpasang rapat.',
            ],
        ];

        /** @var array<string, ServiceOrder> $orders */
        $orders = [];

        foreach ($ordersConfig as $cfg) {
            $order = ServiceOrder::query()->updateOrCreate(
                ['order_number' => $cfg['order_number']],
                [
                    'order_slug' => str_replace('/', '-', $cfg['order_number']),
                    'client_company_id' => $company->id,
                    'created_by_user_id' => $client->id,
                    'transaction_type' => $cfg['transaction_type'],
                    'drum_product_id' => $cfg['drum_product_id'],
                    'reconditioning_service_id' => $cfg['reconditioning_service_id'],
                    'service_category' => $cfg['service_category'],
                    'cargo_type' => $cfg['cargo_type'],
                    'service_type' => $cfg['service_type'],
                    'requested_quantity' => $cfg['requested_quantity'],
                    'requested_unit' => 'Drum',
                    'origin_company_name' => 'PT Maju Jaya Kimia',
                    'client_notes' => $cfg['client_notes'],
                    'order_status' => $cfg['order_status'],
                    'gate_status' => 'NOT_REQUIRED',
                    'warehouse_status' => $cfg['warehouse_status'],
                    'billing_status' => $cfg['billing_status'],
                    'payment_status' => $cfg['payment_status'],
                    'submitted_at' => $cfg['submitted_at'],
                    'completed_at' => $cfg['completed_at'] ?? null,
                ]
            );
            $orders[$cfg['order_number']] = $order;
        }

        foreach (['senin-16/11/26-1', 'senin-16/11/26-2', 'selasa-17/11/26-3', 'selasa-17/11/26-4'] as $ordNum) {
            $ord = $orders[$ordNum];
            WarehouseInspection::query()->updateOrCreate(
                ['service_order_id' => $ord->id],
                [
                    'inspected_by_user_id' => $gudang->id,
                    'actual_cargo_type' => $ord->cargo_type ?? 'Drum standar',
                    'actual_quantity' => $ord->requested_quantity,
                    'actual_unit' => 'Drum',
                    'cargo_condition' => 'Muatan drum dalam kondisi layak proses operasional.',
                    'discrepancy_notes' => 'Jumlah fisik sesuai dengan pengajuan.',
                    'decision' => 'APPROVED',
                    'decision_reason' => null,
                    'inspected_at' => '2026-11-16 09:40:00',
                ]
            );
        }

        ServiceProcess::query()->updateOrCreate(
            ['service_order_id' => $orders['selasa-17/11/26-4']->id, 'process_stage' => 'Pencucian & Pelurusan Bibir Drum'],
            [
                'recorded_by_user_id' => $gudang->id,
                'progress_notes' => 'Proses pencucian bagian dalam dan pelurusan bibir drum sedang berjalan.',
                'processed_qty' => 60,
                'recorded_at' => '2026-11-17 09:30:00',
            ]
        );

        $resultsConfig = [
            'senin-16/11/26-1' => ['qty' => 50, 'rej' => 0, 'type' => 'Laundry + Cat', 'notes' => '50 drum selesai direkondisi dan lolos pemeriksaan kualitas.'],
            'senin-16/11/26-2' => ['qty' => 40, 'rej' => 0, 'type' => 'Laundry', 'notes' => '40 drum selesai dicuci dan siap diserahkan kembali.'],
            'selasa-17/11/26-3' => ['qty' => 48, 'rej' => 2, 'type' => 'Laundry', 'notes' => '48 drum selesai direkondisi. 2 drum keropos parah tidak diproses.'],
        ];

        foreach ($resultsConfig as $ordNum => $resCfg) {
            ServiceResult::query()->updateOrCreate(
                ['service_order_id' => $orders[$ordNum]->id],
                [
                    'completed_by_user_id' => $gudang->id,
                    'actual_service_type' => $resCfg['type'],
                    'completed_quantity' => $resCfg['qty'],
                    'unprocessed_or_rejected_quantity' => $resCfg['rej'],
                    'unit' => 'Drum',
                    'result_notes' => $resCfg['notes'],
                    'completed_at' => '2026-11-16 14:30:00',
                ]
            );
        }

        $inv1 = Invoice::query()->updateOrCreate(
            ['invoice_number' => 'INV/PKDI/2026/11/0001'],
            [
                'invoice_slug' => 'INV-PKDI-2026-11-0001',
                'service_order_id' => $orders['senin-16/11/26-1']->id,
                'client_company_id' => $company->id,
                'revision_number' => 0,
                'subtotal_amount' => 4250000,
                'adjustment_amount' => 0,
                'tax_amount' => 0,
                'total_amount' => 4250000,
                'paid_amount' => 4250000,
                'balance_due' => 0,
                'status' => 'PAID',
                'issue_date' => '2026-11-16',
                'due_date' => '2026-11-23',
                'payment_instructions' => 'Transfer ke Rekening Operasional Bank Mandiri PT Pulo Kencana Drum Industri.',
                'notes' => 'Pembayaran lunas terverifikasi.',
                'created_by_user_id' => $keuangan->id,
                'issued_at' => '2026-11-16 14:45:00',
                'paid_at' => '2026-11-16 15:15:00',
            ]
        );

        InvoiceItem::query()->updateOrCreate(
            ['invoice_id' => $inv1->id, 'description' => 'Jasa Rekondisi Drum Besi (Laundry + Cat)'],
            [
                'service_tariff_id' => $tariffRenewB200->id,
                'transaction_type' => ServiceOrder::TYPE_RECONDITIONING,
                'quantity' => 50,
                'unit' => 'Drum',
                'unit_price' => 85000,
                'line_total' => 4250000,
            ]
        );

        $pay1 = Payment::query()->updateOrCreate(
            ['payment_number' => 'PAY-20261116-0001'],
            [
                'invoice_id' => $inv1->id,
                'client_company_id' => $company->id,
                'submitted_by_user_id' => $client->id,
                'verified_by_user_id' => $keuangan->id,
                'payment_date' => '2026-11-16',
                'payment_method' => 'Transfer Bank Mandiri',
                'reference_number' => 'MDR-119024',
                'amount' => 4250000,
                'proof_file_path' => 'bukti-lunas-0001.pdf',
                'verification_status' => 'VERIFIED',
                'verification_notes' => 'Dana masuk sesuai mutasi rekening.',
                'verified_at' => '2026-11-16 15:15:00',
            ]
        );

        $inv2 = Invoice::query()->updateOrCreate(
            ['invoice_number' => 'INV/PKDI/2026/11/0002'],
            [
                'invoice_slug' => 'INV-PKDI-2026-11-0002',
                'service_order_id' => $orders['senin-16/11/26-2']->id,
                'client_company_id' => $company->id,
                'revision_number' => 0,
                'subtotal_amount' => 2600000,
                'adjustment_amount' => 0,
                'tax_amount' => 0,
                'total_amount' => 2600000,
                'paid_amount' => 2600000,
                'balance_due' => 0,
                'status' => 'PAID',
                'issue_date' => '2026-11-16',
                'due_date' => '2026-11-23',
                'payment_instructions' => 'Transfer ke Rekening Operasional Bank BCA PT Pulo Kencana Drum Industri.',
                'notes' => 'Pembayaran lunas terverifikasi.',
                'created_by_user_id' => $keuangan->id,
                'issued_at' => '2026-11-16 15:00:00',
                'paid_at' => '2026-11-16 15:30:00',
            ]
        );

        InvoiceItem::query()->updateOrCreate(
            ['invoice_id' => $inv2->id, 'description' => 'Jasa Rekondisi Drum Besi (Laundry)'],
            [
                'service_tariff_id' => $tariffRepairB200->id,
                'transaction_type' => ServiceOrder::TYPE_RECONDITIONING,
                'quantity' => 40,
                'unit' => 'Drum',
                'unit_price' => 65000,
                'line_total' => 2600000,
            ]
        );

        $pay2 = Payment::query()->updateOrCreate(
            ['payment_number' => 'PAY-20261116-0002'],
            [
                'invoice_id' => $inv2->id,
                'client_company_id' => $company->id,
                'submitted_by_user_id' => $client->id,
                'verified_by_user_id' => $keuangan->id,
                'payment_date' => '2026-11-16',
                'payment_method' => 'Transfer Bank BCA',
                'reference_number' => 'BCA-5521009',
                'amount' => 2600000,
                'proof_file_path' => 'bukti-lunas-0002.pdf',
                'verification_status' => 'VERIFIED',
                'verification_notes' => 'Dana masuk sesuai mutasi rekening.',
                'verified_at' => '2026-11-16 15:30:00',
            ]
        );

        $exp1 = OperationalExpense::query()->updateOrCreate(
            ['expense_number' => 'EXP-20261116-001'],
            [
                'expense_date' => '2026-11-16',
                'expense_category' => 'Pembelian Bahan',
                'description' => 'Pembelian cat epoxy abu-abu industri dan thinner pencuci',
                'recipient_or_vendor' => 'CV Warna Jaya Abadi',
                'payment_method' => 'Transfer Bank BCA',
                'amount' => 2250000,
                'receipt_file_path' => 'nota-cat-epoxy-001.pdf',
                'recorded_by_user_id' => $keuangan->id,
                'status' => 'RECORDED',
            ]
        );

        $exp2 = OperationalExpense::query()->updateOrCreate(
            ['expense_number' => 'EXP-20261116-002'],
            [
                'expense_date' => '2026-11-16',
                'expense_category' => 'Utilitas',
                'description' => 'Tagihan listrik dan air operasional workshop',
                'recipient_or_vendor' => 'PLN & PDAM',
                'payment_method' => 'Transfer Bank Mandiri',
                'amount' => 1500000,
                'receipt_file_path' => 'struk-listrik-nov.pdf',
                'recorded_by_user_id' => $keuangan->id,
                'status' => 'RECORDED',
            ]
        );

        CashbookEntry::query()->updateOrCreate(
            ['entry_number' => 'CB-20261116-0001'],
            [
                'transaction_date' => '2026-11-16',
                'entry_type' => 'IN',
                'source_type' => 'INVOICE_PAYMENT',
                'payment_id' => $pay1->id,
                'description' => 'Pembayaran Invoice INV/PKDI/2026/11/0001 (Lunas)',
                'amount' => 4250000,
                'recorded_by_user_id' => $keuangan->id,
                'status' => 'ACTIVE',
            ]
        );

        CashbookEntry::query()->updateOrCreate(
            ['entry_number' => 'CB-20261116-0002'],
            [
                'transaction_date' => '2026-11-16',
                'entry_type' => 'IN',
                'source_type' => 'INVOICE_PAYMENT',
                'payment_id' => $pay2->id,
                'description' => 'Pembayaran Invoice INV/PKDI/2026/11/0002 (Lunas)',
                'amount' => 2600000,
                'recorded_by_user_id' => $keuangan->id,
                'status' => 'ACTIVE',
            ]
        );

        CashbookEntry::query()->updateOrCreate(
            ['entry_number' => 'CB-20261116-0003'],
            [
                'transaction_date' => '2026-11-16',
                'entry_type' => 'OUT',
                'source_type' => 'OPERATIONAL_EXPENSE',
                'operational_expense_id' => $exp1->id,
                'description' => 'Biaya Operasional: Pembelian Bahan (Cat & Thinner)',
                'amount' => 2250000,
                'recorded_by_user_id' => $keuangan->id,
                'status' => 'ACTIVE',
            ]
        );

        CashbookEntry::query()->updateOrCreate(
            ['entry_number' => 'CB-20261116-0004'],
            [
                'transaction_date' => '2026-11-16',
                'entry_type' => 'OUT',
                'source_type' => 'OPERATIONAL_EXPENSE',
                'operational_expense_id' => $exp2->id,
                'description' => 'Biaya Operasional: Utilitas Listrik & Air',
                'amount' => 1500000,
                'recorded_by_user_id' => $keuangan->id,
                'status' => 'ACTIVE',
            ]
        );

        foreach ($orders as $ord) {
            OrderStatusHistory::query()->updateOrCreate(
                ['service_order_id' => $ord->id, 'to_status' => $ord->order_status],
                [
                    'track_type' => 'ORDER',
                    'from_status' => 'SUBMITTED',
                    'changed_by_user_id' => $client->id,
                    'notes' => "Pesanan {$ord->order_number} terdaftar pada sistem operasional PKDI.",
                    'created_at' => $ord->submitted_at,
                ]
            );
        }

        AppNotification::query()->updateOrCreate(
            ['recipient_user_id' => $gudang->id, 'title' => 'Pesanan baru menunggu peninjauan Gudang'],
            [
                'message' => 'Pesanan selasa-17/11/26-5 (Penjualan Drum Poligen 150 liter) menunggu peninjauan Kepala Gudang.',
                'action_url' => '/warehouse/orders/selasa-17-11-26-5',
            ]
        );

        AuditLog::query()->updateOrCreate(
            [
                'action' => 'SYSTEM_INITIALIZED',
                'auditable_type' => User::class,
                'auditable_id' => $owner->id,
            ],
            [
                'user_id' => $owner->id,
                'user_role' => User::ROLE_OWNER,
                'old_values' => null,
                'new_values' => [
                    'order_number' => 'SISTEM-PKDI',
                    'summary' => 'Inisialisasi 4 akun utama PRD 2.0 dan data skenario pengujian PKDI.',
                ],
                'reason' => 'Setup awal skenario pengujian sistem operasional PKDI',
                'ip_address' => '127.0.0.1',
                'created_at' => now(),
            ]
        );
    }
}
