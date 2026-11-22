<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\BillableTransaction;
use App\Models\CashbookEntry;
use App\Models\ClientCompany;
use App\Models\DrumProduct;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\ReconditioningService;
use App\Models\ServiceOrder;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase4FinalPrdV2AcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_complete_prd_v2_company_lifecycle_across_all_four_active_roles(): void
    {
        Storage::fake('local');

        $gudang = User::query()->where('email', 'kepalagudang@pkdi.co.id')->firstOrFail();
        $keuangan = User::query()->where('email', 'keuangan@pkdi.co.id')->firstOrFail();
        $owner = User::query()->where('email', 'owner@pkdi.co.id')->firstOrFail();

        $drumBesi = DrumProduct::query()->where('name', 'Drum besi')->firstOrFail();
        $jerigen = DrumProduct::query()->where('name', 'Jerigen')->firstOrFail();
        $paketLengkap = ReconditioningService::query()->where('name', 'Laundry + Cat + Sablon')->firstOrFail();

        // 1. Client baru mendaftar mandiri & melengkapi NPWP + NIB
        $this->post('/register', [
            'company_name' => 'PT Sinar Petro Industri',
            'pic_name' => 'Haryanto Kusuma',
            'phone' => '081234560099',
            'address' => 'Kawasan Industri MM2100 Cikarang Barat, Bekasi',
            'email' => 'haryanto@sinarpetro.co.id',
            'password' => 'PetroSecure2026!',
            'password_confirmation' => 'PetroSecure2026!',
        ])->assertRedirect('/client/dashboard');

        $client = User::query()->where('email', 'haryanto@sinarpetro.co.id')->firstOrFail();

        $this->actingAs($client)->from(route('client.company'))->put(route('client.company.update'), [
            'company_name' => 'PT Sinar Petro Industri',
            'pic_name' => 'Haryanto Kusuma',
            'phone' => '081234560099',
            'email' => 'haryanto@sinarpetro.co.id',
            'address' => 'Kawasan Industri MM2100 Cikarang Barat, Bekasi',
            'npwp' => '01.234.567.8-413.000',
            'nib_number' => '1234567890123',
            'nib_file' => UploadedFile::fake()->create('nib-sinar-petro.pdf', 120, 'application/pdf'),
        ])->assertRedirect(route('client.company'));

        $this->assertSame('COMPLETE', $client->clientCompany->fresh()->documents_status);

        // 2. Client membuat 2 pesanan: Jasa Rekondisi (Laundry + Cat + Sablon) & Penjualan Drum (Jerigen)
        $this->actingAs($client)->post(route('client.orders.store'), [
            'transaction_type' => ServiceOrder::TYPE_RECONDITIONING,
            'drum_product_id' => $drumBesi->id,
            'reconditioning_service_id' => $paketLengkap->id,
            'requested_quantity' => 30,
            'requested_unit' => 'Drum',
            'origin_company_name' => 'PT Sinar Petro Industri',
            'po_number' => 'PO/SPI/2026/11/01',
            'needed_date' => '2026-12-10',
            'specification_notes' => 'Cat hijau industri + sablon logo SPI putih.',
            'po_attachment' => UploadedFile::fake()->create('po-rec.pdf', 100, 'application/pdf'),
        ])->assertRedirect();

        $orderRec = ServiceOrder::query()->where('po_number', 'PO/SPI/2026/11/01')->firstOrFail();

        $this->actingAs($client)->post(route('client.orders.store'), [
            'transaction_type' => ServiceOrder::TYPE_DRUM_SALE,
            'drum_product_id' => $jerigen->id,
            'requested_quantity' => 40,
            'requested_unit' => 'Unit',
            'origin_company_name' => 'PT Sinar Petro Industri',
            'po_number' => 'PO/SPI/2026/11/02',
            'needed_date' => '2026-12-10',
        ])->assertRedirect();

        $orderSale = ServiceOrder::query()->where('po_number', 'PO/SPI/2026/11/02')->firstOrFail();

        // 3. Kepala Gudang memproses penuh pesanan Rekondisi (Tinjau -> Terima Fisik -> Sortir -> Progres -> Hasil)
        $this->actingAs($gudang)->post(route('warehouse.orders.review', $orderRec->order_slug), ['decision' => 'ACCEPT'])->assertRedirect();
        $this->actingAs($gudang)->post(route('warehouse.orders.receipt.store', $orderRec->order_slug), [
            'actual_received_quantity' => 29,
            'unit' => 'Drum',
            'initial_condition' => '27 drum standar, 2 drum penyok berat.',
            'discrepancy_notes' => 'Tiba 29 drum dari rencana 30 drum.',
            'photo' => UploadedFile::fake()->image('rec-arrive.jpg'),
        ])->assertRedirect();
        $this->actingAs($gudang)->post(route('warehouse.orders.sorting.store', $orderRec->order_slug), [
            'qty_passed' => 25,
            'qty_special_repair' => 2,
            'qty_rejected' => 2,
            'cargo_condition' => '25 layak standar, 2 perlu ketok penyok, 2 bocor parah.',
            'rejected_reason' => 'Kebocoran dasar tidak layak las.',
            'photo' => UploadedFile::fake()->image('rec-sort.jpg'),
        ])->assertRedirect();
        $this->actingAs($gudang)->post(route('warehouse.orders.processes.store', $orderRec->order_slug), [
            'process_stage' => 'Penyablonan Logo / Identitas',
            'progress_notes' => '27 drum selesai dicat hijau dan disablon logo SPI.',
            'processed_qty' => 27,
            'is_visible_to_client' => 1,
        ])->assertRedirect();
        $this->actingAs($gudang)->post(route('warehouse.orders.result.store', $orderRec->order_slug), [
            'reconditioning_service_id' => $paketLengkap->id,
            'completed_qty' => 27,
            'rejected_qty' => 2,
            'result_notes' => '27 drum lulus QC akhir, 2 drum tidak layak dikembalikan.',
            'photo' => UploadedFile::fake()->image('rec-final.jpg'),
        ])->assertRedirect();

        // 4. Kepala Gudang memproses penuh pesanan Penjualan Drum
        $this->actingAs($gudang)->post(route('warehouse.orders.review', $orderSale->order_slug), ['decision' => 'ACCEPT'])->assertRedirect();
        $this->actingAs($gudang)->post(route('warehouse.orders.result.store', $orderSale->order_slug), [
            'actual_service_type' => 'Penjualan Drum',
            'completed_qty' => 40,
            'rejected_qty' => 0,
            'result_notes' => '40 jerigen siap kirim.',
        ])->assertRedirect();

        // Verifikasi 2 BillableTransaction siap-tagih terbentuk unik
        $txRec = BillableTransaction::query()->where('service_order_id', $orderRec->id)->firstOrFail();
        $txSale = BillableTransaction::query()->where('service_order_id', $orderSale->id)->firstOrFail();
        $this->assertSame('READY_TO_BILL', $txRec->billing_status);
        $this->assertSame('READY_TO_BILL', $txSale->billing_status);

        // 5. Keuangan menggabungkan kedua transaksi menjadi 1 Invoice resmi
        $this->actingAs($keuangan)->post(route('finance.billable.combined.store'), [
            'billable_transaction_ids' => [$txRec->id, $txSale->id],
            'invoice_action' => 'ISSUE',
            'unit_prices' => [$txRec->id => 120000, $txSale->id => 45000],
            'adjustment_amount' => 0,
            'tax_amount' => 0,
        ])->assertRedirect();

        // 27 * 120.000 = 3.240.000; 40 * 45.000 = 1.800.000; Total = 5.040.000
        $invoice = Invoice::query()->latest('id')->firstOrFail();
        $this->assertSame(5040000.0, (float) $invoice->total_amount);

        // 6. Client unggah bukti transfer -> ditolak -> unggah ulang -> diverifikasi Keuangan
        $this->actingAs($client)->post(route('client.invoices.payments.store', $invoice->invoice_slug), [
            'amount' => 5040000,
            'payment_date' => '2026-11-19',
            'payment_method' => 'Transfer Bank Mandiri',
            'reference_number' => 'MDR-SPI-ERR',
            'proof_file' => UploadedFile::fake()->image('bukti-salah.jpg'),
        ])->assertRedirect();

        $pay1 = Payment::query()->where('reference_number', 'MDR-SPI-ERR')->firstOrFail();
        $this->actingAs($keuangan)->post(route('finance.invoices.payments.store', $invoice->invoice_slug), [
            'payment_id' => $pay1->id,
            'decision' => 'REJECTED',
            'reject_reason' => 'Bukti terpotong dan nominal transfer tidak tampak.',
        ])->assertRedirect();

        $this->actingAs($client)->post(route('client.invoices.payments.store', $invoice->invoice_slug), [
            'amount' => 5040000,
            'payment_date' => '2026-11-19',
            'payment_method' => 'Transfer Bank Mandiri',
            'reference_number' => 'MDR-SPI-OK',
            'proof_file' => UploadedFile::fake()->image('bukti-valid.png'),
        ])->assertRedirect();

        $pay2 = Payment::query()->where('reference_number', 'MDR-SPI-OK')->firstOrFail();
        $this->actingAs($keuangan)->post(route('finance.invoices.payments.store', $invoice->invoice_slug), [
            'payment_id' => $pay2->id,
            'decision' => 'VERIFIED',
        ])->assertRedirect();

        $this->assertSame('PAID', $invoice->fresh()->status);
        $this->assertSame('PAID', $orderRec->fresh()->payment_status);
        $this->assertSame('PAID', $orderSale->fresh()->payment_status);
        $this->assertSame(1, CashbookEntry::query()->where('payment_id', $pay2->id)->count());

        // 7. Kepala Gudang menyelesaikan penyerahan kedua pesanan secara terpisah dari jalur pembayaran
        $this->actingAs($gudang)->post(route('warehouse.orders.delivery.store', $orderRec->order_slug), [
            'delivery_action' => 'MARK_COMPLETED',
            'delivery_method' => 'DIANTAR_PKDI',
            'delivered_quantity' => 27,
            'vehicle_type' => 'Truk Box PKDI',
            'license_plate' => 'B 7721 PKD',
            'driver_name' => 'Wahyu',
        ])->assertRedirect();

        $this->actingAs($gudang)->post(route('warehouse.orders.delivery.store', $orderSale->order_slug), [
            'delivery_action' => 'MARK_COMPLETED',
            'delivery_method' => 'DIAMBIL_CLIENT',
            'delivered_quantity' => 40,
        ])->assertRedirect();

        $this->assertSame('COMPLETED', $orderRec->fresh()->order_status);
        $this->assertSame('COMPLETED', $orderSale->fresh()->order_status);

        // 8. Owner memantau seluruh laporan read-only, audit trail & mengekspor laporan PDF/Excel
        $this->actingAs($owner)->get(route('owner.dashboard'))->assertOk()->assertSee('PT Sinar Petro Industri');
        $this->actingAs($owner)->get(route('owner.transactions.show', $orderRec->order_slug))
            ->assertOk()
            ->assertSee($invoice->invoice_number)
            ->assertSee('27 selesai, 2 tidak layak');
        $this->actingAs($owner)->get(route('owner.reports.export', ['format' => 'pdf', 'report_type' => 'cashbook']))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->actingAs($owner)->get(route('owner.audit-logs'))->assertOk();

        $this->assertTrue(AuditLog::query()->where('action', 'COMBINED_INVOICE_ISSUED')->exists());
        $this->assertTrue(AuditLog::query()->where('action', 'PAYMENT_VERIFIED')->exists());
    }

    public function test_role_security_boundaries_and_antislop_responsive_invariants_on_all_new_pages(): void
    {
        $client = User::query()->where('email', 'client@pkdi.co.id')->firstOrFail();
        $gudang = User::query()->where('email', 'kepalagudang@pkdi.co.id')->firstOrFail();
        $keuangan = User::query()->where('email', 'keuangan@pkdi.co.id')->firstOrFail();
        $owner = User::query()->where('email', 'owner@pkdi.co.id')->firstOrFail();

        // Owner murni read-only: ditolak pada seluruh endpoint tulis operasional/keuangan
        $this->actingAs($owner)->post(route('finance.billable.combined.store'), [])->assertForbidden();
        $this->actingAs($owner)->post(route('finance.expenses.store'), [])->assertForbidden();
        $this->actingAs($owner)->post(route('client.orders.store'), [])->assertForbidden();

        // Kepala Gudang tidak boleh mengakses modul penagihan/tarif Keuangan
        $this->actingAs($gudang)->get(route('finance.billable.index'))->assertForbidden();
        $this->actingAs($gudang)->post(route('finance.billable.combined.store'), [])->assertForbidden();

        // Keuangan tidak boleh mengakses aksi operasional Gudang
        $this->actingAs($keuangan)->get(route('warehouse.reports.index'))->assertForbidden();
        $this->actingAs($keuangan)->get(route('warehouse.dashboard'))->assertForbidden();

        // Client tidak boleh mengakses modul internal mana pun
        $this->actingAs($client)->get(route('warehouse.dashboard'))->assertForbidden();
        $this->actingAs($client)->get(route('finance.dashboard'))->assertForbidden();
        $this->actingAs($client)->get(route('owner.dashboard'))->assertForbidden();

        // Halaman utama PRD 2.0 bebas em dash, bebas label demo/Satpam, dan responsif
        $pagesByRole = [
            [$client, ['/client/dashboard', '/client/orders/create', '/client/invoices', '/client/company']],
            [$gudang, ['/warehouse/dashboard', '/warehouse/inspections', '/warehouse/processes', '/warehouse/reports']],
            [$keuangan, ['/finance/dashboard', '/finance/billable-orders', '/finance/invoices', '/finance/tariffs', '/finance/reports']],
            [$owner, ['/owner/dashboard', '/owner/financial-summary', '/owner/transactions', '/owner/audit-logs']],
        ];

        foreach ($pagesByRole as [$user, $urls]) {
            foreach ($urls as $url) {
                $response = $this->actingAs($user)->get($url);
                $response->assertOk();
                $response->assertSee('viewport', false);
                $response->assertDontSee('—', false);
                $response->assertDontSee('[DEMO]');
                $response->assertDontSee('Pindah Akun Demo');
                $response->assertDontSee('Pos Gerbang');
            }
        }

        // Pastikan tidak ada role satpam aktif di database hasil seed
        $this->assertSame(0, User::query()->where('role', User::ROLE_SATPAM)->where('is_active', true)->count());
        $this->assertSame(4, ClientCompany::query()->count() >= 1 ? User::query()->where('is_active', true)->count() : 0);
    }
}
