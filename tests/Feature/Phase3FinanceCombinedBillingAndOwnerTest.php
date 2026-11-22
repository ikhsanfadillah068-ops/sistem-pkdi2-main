<?php

namespace Tests\Feature;

use App\Models\BillableTransaction;
use App\Models\CashbookEntry;
use App\Models\ClientCompany;
use App\Models\DrumProduct;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\ReconditioningService;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Services\OrderWorkflowService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase3FinanceCombinedBillingAndOwnerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_combined_invoice_merges_multiple_transactions_prevents_double_billing_and_blocks_cross_client(): void
    {
        $workflow = app(OrderWorkflowService::class);
        $clientA = User::query()->where('email', 'client@pkdi.co.id')->firstOrFail();
        $gudang = User::query()->where('email', 'kepalagudang@pkdi.co.id')->firstOrFail();
        $keuangan = User::query()->where('email', 'keuangan@pkdi.co.id')->firstOrFail();
        $owner = User::query()->where('email', 'owner@pkdi.co.id')->firstOrFail();

        $drumBesi = DrumProduct::query()->where('name', 'Drum besi')->firstOrFail();
        $poligen = DrumProduct::query()->where('name', 'Poligen 150 liter')->firstOrFail();
        $laundryCat = ReconditioningService::query()->where('name', 'Laundry + Cat')->firstOrFail();

        // Client A: Order 1 (Rekondisi) & Order 2 (Penjualan Drum) selesai dari Gudang
        $orderRec = $workflow->createOrder($clientA, [
            'transaction_type' => ServiceOrder::TYPE_RECONDITIONING,
            'drum_product_id' => $drumBesi->id,
            'reconditioning_service_id' => $laundryCat->id,
            'requested_quantity' => 40,
            'requested_unit' => 'Drum',
            'origin_company_name' => 'PT Maju Jaya Kimia',
        ]);
        $workflow->reviewOrder($gudang, $orderRec, ['decision' => 'ACCEPT']);
        $workflow->recordReceipt($gudang, $orderRec->fresh(), [
            'actual_received_quantity' => 40,
            'unit' => 'Drum',
            'initial_condition' => 'Kondisi baik siap sortir.',
        ]);
        $workflow->recordSortingAndInspection($gudang, $orderRec->fresh(), [
            'qty_passed' => 38,
            'qty_special_repair' => 0,
            'qty_rejected' => 2,
            'cargo_condition' => '38 layak, 2 bocor berat.',
            'rejected_reason' => 'Korosi parah bagian dasar.',
        ]);
        $workflow->completeService($gudang, $orderRec->fresh(), [
            'reconditioning_service_id' => $laundryCat->id,
            'completed_qty' => 38,
            'rejected_qty' => 2,
            'result_notes' => '38 drum selesai Laundry + Cat.',
        ]);

        $orderSale = $workflow->createOrder($clientA, [
            'transaction_type' => ServiceOrder::TYPE_DRUM_SALE,
            'drum_product_id' => $poligen->id,
            'requested_quantity' => 20,
            'requested_unit' => 'Unit',
            'origin_company_name' => 'PT Maju Jaya Kimia',
        ]);
        $workflow->reviewOrder($gudang, $orderSale, ['decision' => 'ACCEPT']);
        $workflow->completeService($gudang, $orderSale->fresh(), [
            'actual_service_type' => 'Penjualan Drum',
            'completed_qty' => 20,
            'rejected_qty' => 0,
            'result_notes' => '20 unit Poligen 150L siap serah.',
        ]);

        // Client B: Order 3 selesai dari Gudang
        $companyB = ClientCompany::query()->create([
            'company_code' => 'CLT-B-002',
            'company_name' => 'PT Kimia Nusantara',
            'pic_name' => 'Rudi',
            'phone' => '081355667788',
            'email' => 'rudi@kimianusantara.co.id',
            'address' => 'Karawang Timur',
        ]);
        $clientB = User::factory()->create([
            'role' => User::ROLE_CLIENT,
            'client_company_id' => $companyB->id,
            'is_active' => true,
        ]);

        $orderB = $workflow->createOrder($clientB, [
            'transaction_type' => ServiceOrder::TYPE_DRUM_SALE,
            'drum_product_id' => $drumBesi->id,
            'requested_quantity' => 10,
            'requested_unit' => 'Drum',
            'origin_company_name' => 'PT Kimia Nusantara',
        ]);
        $workflow->reviewOrder($gudang, $orderB, ['decision' => 'ACCEPT']);
        $workflow->completeService($gudang, $orderB->fresh(), [
            'actual_service_type' => 'Penjualan Drum',
            'completed_qty' => 10,
            'rejected_qty' => 0,
        ]);

        $tx1 = BillableTransaction::query()->where('service_order_id', $orderRec->id)->firstOrFail();
        $tx2 = BillableTransaction::query()->where('service_order_id', $orderSale->id)->firstOrFail();
        $txB = BillableTransaction::query()->where('service_order_id', $orderB->id)->firstOrFail();

        // 1. Menggabungkan transaksi lintas Client wajib ditolak
        $this->actingAs($keuangan)->post(route('finance.billable.combined.store'), [
            'billable_transaction_ids' => [$tx1->id, $txB->id],
            'invoice_action' => 'ISSUE',
            'unit_prices' => [$tx1->id => 85000, $txB->id => 250000],
        ])->assertSessionHasErrors('billable_transaction_ids');

        // 2. Menggabungkan 2 transaksi milik Client A menjadi 1 Draft lalu diterbitkan
        $this->actingAs($keuangan)->post(route('finance.billable.combined.store'), [
            'billable_transaction_ids' => [$tx1->id, $tx2->id],
            'invoice_action' => 'DRAFT',
            'unit_prices' => [$tx1->id => 100000, $tx2->id => 250000],
            'adjustment_amount' => 100000,
            'tax_amount' => 0,
        ])->assertRedirect();

        /** @var Invoice $invoice */
        $invoice = Invoice::query()->latest('id')->firstOrFail();
        $this->assertSame('DRAFT', $invoice->status);
        // (38 * 100.000) + (20 * 250.000) - 100.000 = 3.800.000 + 5.000.000 - 100.000 = 8.700.000
        $this->assertSame(8700000.0, (float) $invoice->total_amount);
        $this->assertSame(2, $invoice->items()->count());

        // Draft belum dapat dilihat Client A
        $this->actingAs($clientA)->get(route('client.invoices.show', $invoice->invoice_slug))->assertForbidden();

        // Terbitkan Draft Invoice -> kedua transaksi terkunci INVOICED
        $this->actingAs($keuangan)->post(route('finance.invoices.publish', $invoice->invoice_slug))
            ->assertRedirect(route('finance.invoices.show', $invoice->invoice_slug));

        $invoice->refresh();
        $tx1->refresh();
        $tx2->refresh();
        $this->assertSame('ISSUED', $invoice->status);
        $this->assertSame('INVOICED', $tx1->billing_status);
        $this->assertSame('INVOICED', $tx2->billing_status);
        $this->assertSame('INVOICED', $orderRec->fresh()->billing_status);
        $this->assertSame('INVOICED', $orderSale->fresh()->billing_status);

        // 3. Mencegah penagihan ganda untuk transaksi yang sudah masuk invoice
        $this->actingAs($keuangan)->post(route('finance.billable.combined.store'), [
            'billable_transaction_ids' => [$tx1->id],
            'invoice_action' => 'ISSUE',
            'unit_prices' => [$tx1->id => 100000],
        ])->assertSessionHasErrors('billable_transaction_ids');

        // 4. Client A dapat melihat Invoice Gabungan & mengunduh PDF; Client B ditolak 403
        $this->actingAs($clientA)->get(route('client.invoices.index'))
            ->assertOk()
            ->assertSee($invoice->invoice_number)
            ->assertSee('2 transaksi');

        $this->actingAs($clientA)->get(route('client.invoices.show', $invoice->invoice_slug))
            ->assertOk()
            ->assertSee('Invoice Gabungan')
            ->assertSee('8.700.000');

        $pdf = $this->actingAs($clientA)->get(route('invoices.download-pdf', $invoice->invoice_slug));
        $pdf->assertOk();
        $this->assertStringStartsWith('%PDF-1.4', (string) $pdf->getContent());

        $this->actingAs($clientB)->get(route('client.invoices.show', $invoice->invoice_slug))->assertForbidden();
        $this->actingAs($clientB)->get(route('invoices.download-pdf', $invoice->invoice_slug))->assertForbidden();

        // 5. Owner dapat memantau dashboard & filter transaksi lintas bagian
        $this->actingAs($owner)->get(route('owner.dashboard'))
            ->assertOk()
            ->assertSee('Rekondisi')
            ->assertSee('Pengangkutan Limbah');

        $this->actingAs($owner)->get(route('owner.transactions.index', ['transaction_type' => 'DRUM_SALE']))
            ->assertOk()
            ->assertSee($orderSale->order_number)
            ->assertDontSee($orderRec->order_number);
    }

    public function test_payment_proof_rejection_reupload_verification_cashbook_and_void_invoice(): void
    {
        Storage::fake('local');

        $workflow = app(OrderWorkflowService::class);
        $clientA = User::query()->where('email', 'client@pkdi.co.id')->firstOrFail();
        $gudang = User::query()->where('email', 'kepalagudang@pkdi.co.id')->firstOrFail();
        $keuangan = User::query()->where('email', 'keuangan@pkdi.co.id')->firstOrFail();
        $owner = User::query()->where('email', 'owner@pkdi.co.id')->firstOrFail();

        $drumBesi = DrumProduct::query()->where('name', 'Drum besi')->firstOrFail();
        $order1 = $workflow->createOrder($clientA, [
            'transaction_type' => ServiceOrder::TYPE_DRUM_SALE,
            'drum_product_id' => $drumBesi->id,
            'requested_quantity' => 10,
            'requested_unit' => 'Drum',
            'origin_company_name' => 'PT Maju Jaya Kimia',
        ]);
        $workflow->reviewOrder($gudang, $order1, ['decision' => 'ACCEPT']);
        $workflow->completeService($gudang, $order1->fresh(), [
            'actual_service_type' => 'Penjualan Drum',
            'completed_qty' => 10,
            'rejected_qty' => 0,
        ]);

        $order2 = $workflow->createOrder($clientA, [
            'transaction_type' => ServiceOrder::TYPE_DRUM_SALE,
            'drum_product_id' => $drumBesi->id,
            'requested_quantity' => 5,
            'requested_unit' => 'Drum',
            'origin_company_name' => 'PT Maju Jaya Kimia',
        ]);
        $workflow->reviewOrder($gudang, $order2, ['decision' => 'ACCEPT']);
        $workflow->completeService($gudang, $order2->fresh(), [
            'actual_service_type' => 'Penjualan Drum',
            'completed_qty' => 5,
            'rejected_qty' => 0,
        ]);

        $tx1 = BillableTransaction::query()->where('service_order_id', $order1->id)->firstOrFail();
        $tx2 = BillableTransaction::query()->where('service_order_id', $order2->id)->firstOrFail();

        // Terbitkan Invoice pertama lalu Void (batal) agar transaksi kembali siap ditagih
        $this->actingAs($keuangan)->post(route('finance.billable.combined.store'), [
            'billable_transaction_ids' => [$tx1->id, $tx2->id],
            'invoice_action' => 'ISSUE',
            'unit_prices' => [$tx1->id => 200000, $tx2->id => 200000],
        ])->assertRedirect();

        $voidedInv = Invoice::query()->latest('id')->firstOrFail();

        // Void tanpa alasan wajib ditolak
        $this->actingAs($keuangan)->post(route('finance.invoices.void', $voidedInv->invoice_slug), [
            'void_reason' => 'no',
        ])->assertSessionHasErrors('void_reason');

        // Void dengan alasan resmi melepaskan kedua BillableTransaction kembali ke READY_TO_BILL
        $this->actingAs($keuangan)->post(route('finance.invoices.void', $voidedInv->invoice_slug), [
            'void_reason' => 'Kesalahan input harga satuan pada invoice awal.',
        ])->assertRedirect();

        $this->assertSame('VOIDED', $voidedInv->fresh()->status);
        $this->assertSame('READY_TO_BILL', $tx1->fresh()->billing_status);
        $this->assertNull($tx1->fresh()->invoice_id);
        $this->assertSame('READY_TO_BILL', $tx2->fresh()->billing_status);

        // Terbitkan ulang dengan harga yang benar (15 * 220.000 = 3.300.000)
        $this->actingAs($keuangan)->post(route('finance.billable.combined.store'), [
            'billable_transaction_ids' => [$tx1->id, $tx2->id],
            'invoice_action' => 'ISSUE',
            'unit_prices' => [$tx1->id => 220000, $tx2->id => 220000],
        ])->assertRedirect();

        $invoice = Invoice::query()->latest('id')->firstOrFail();
        $this->assertSame(3300000.0, (float) $invoice->total_amount);

        // Client unggah bukti transfer pertama (gambar salah)
        $wrongProof = UploadedFile::fake()->image('bukti-blur.jpg', 300, 200);
        $this->actingAs($clientA)->post(route('client.invoices.payments.store', $invoice->invoice_slug), [
            'amount' => 3300000,
            'payment_date' => '2026-11-19',
            'payment_method' => 'Transfer Bank BCA',
            'reference_number' => 'BCA-WRONG-001',
            'proof_file' => $wrongProof,
        ])->assertRedirect();

        $payment1 = Payment::query()->where('reference_number', 'BCA-WRONG-001')->firstOrFail();
        $this->assertSame('PROOF_SUBMITTED', $order1->fresh()->payment_status);
        $this->assertSame('PROOF_SUBMITTED', $order2->fresh()->payment_status);
        $this->assertSame(0, CashbookEntry::query()->where('payment_id', $payment1->id)->count());

        // Proteksi akses berkas bukti bayar (Client A, Keuangan, Owner OK; Client lain 403)
        $this->actingAs($clientA)->get(route('payments.proof.show', $payment1))->assertOk();
        $this->actingAs($keuangan)->get(route('payments.proof.show', $payment1))->assertOk();
        $this->actingAs($owner)->get(route('payments.proof.show', $payment1))->assertOk();

        $otherCompany = ClientCompany::query()->create([
            'company_code' => 'CLT-OTH-003',
            'company_name' => 'PT Pihak Ketiga',
            'pic_name' => 'Sari',
            'phone' => '081200009999',
            'email' => 'sari@pihakketiga.co.id',
            'address' => 'Bekasi',
        ]);
        $otherClient = User::factory()->create([
            'role' => User::ROLE_CLIENT,
            'client_company_id' => $otherCompany->id,
            'is_active' => true,
        ]);
        $this->actingAs($otherClient)->get(route('payments.proof.show', $payment1))->assertForbidden();

        // Keuangan menolak bukti bayar pertama dengan alasan
        $this->actingAs($keuangan)->post(route('finance.invoices.payments.store', $invoice->invoice_slug), [
            'payment_id' => $payment1->id,
            'decision' => 'REJECTED',
            'reject_reason' => 'Foto bukti transfer buram dan nomor rekening tidak terbaca.',
        ])->assertRedirect();

        $this->assertSame('REJECTED', $payment1->fresh()->verification_status);
        $this->assertSame('PROOF_REJECTED', $order1->fresh()->payment_status);
        $this->assertSame('PROOF_REJECTED', $order2->fresh()->payment_status);

        // Client melihat alasan penolakan lalu mengunggah ulang bukti baru yang jelas
        $this->actingAs($clientA)->get(route('client.invoices.show', $invoice->invoice_slug))
            ->assertOk()
            ->assertSee('Foto bukti transfer buram');

        $validProof = UploadedFile::fake()->image('bukti-jelas.png', 400, 300);
        $this->actingAs($clientA)->post(route('client.invoices.payments.store', $invoice->invoice_slug), [
            'amount' => 3300000,
            'payment_date' => '2026-11-19',
            'payment_method' => 'Transfer Bank BCA',
            'reference_number' => 'BCA-VALID-002',
            'proof_file' => $validProof,
        ])->assertRedirect();

        $payment2 = Payment::query()->where('reference_number', 'BCA-VALID-002')->firstOrFail();

        // Keuangan memverifikasi bukti bayar kedua -> Lunas pada kedua order & 1 entri Buku Kas
        $this->actingAs($keuangan)->post(route('finance.invoices.payments.store', $invoice->invoice_slug), [
            'payment_id' => $payment2->id,
            'decision' => 'VERIFIED',
        ])->assertRedirect();

        $invoice->refresh();
        $this->assertSame('PAID', $invoice->status);
        $this->assertSame(0.0, (float) $invoice->balance_due);
        $this->assertSame('PAID', $order1->fresh()->payment_status);
        $this->assertSame('PAID', $order2->fresh()->payment_status);
        // Status operasional tetap READY (tidak ditimpa jalur pembayaran)
        $this->assertSame('READY', $order1->fresh()->order_status);
        $this->assertSame('READY', $order2->fresh()->order_status);
        $this->assertSame(1, CashbookEntry::query()->where('payment_id', $payment2->id)->count());

        // Invoice yang sudah Lunas tidak dapat di-Void
        $this->actingAs($keuangan)->post(route('finance.invoices.void', $invoice->invoice_slug), [
            'void_reason' => 'Mencoba membatalkan invoice yang sudah lunas.',
        ])->assertSessionHasErrors('status');
    }
}
