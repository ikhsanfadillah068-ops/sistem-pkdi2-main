<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ClientCompany;
use App\Models\ClientContract;
use App\Models\DiscrepancyReport;
use App\Models\DrumProduct;
use App\Models\Invoice;
use App\Models\ReconditionCert;
use App\Models\ReconditioningService;
use App\Models\ServiceOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class PrdV21FullAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    private ClientCompany $company;

    private User $client;

    private User $warehouse;

    private User $finance;

    private User $owner;

    private DrumProduct $product;

    private ReconditioningService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        DrumProduct::ensureDefaults();
        ReconditioningService::ensureDefaults();

        $this->product = DrumProduct::query()->firstOrFail();
        $this->service = ReconditioningService::query()->firstOrFail();

        $this->company = ClientCompany::query()->create([
            'company_code' => 'CL-PRD21',
            'company_name' => 'PT Industri Kimia Sentosa',
            'pic_name' => 'Andi Pratama',
            'email' => 'andi@kimiasentosa.co.id',
            'phone' => '081234567890',
            'address' => 'Jl. Kawasan Industri Jababeka Blok C-12, Cikarang',
            'npwp' => '01.234.567.8-901.000',
            'nib_number' => '9120102938475',
            'documents_status' => 'COMPLETE',
            'verification_status' => 'VERIFIED',
        ]);

        $this->client = $this->makeUser('client@prd21.test', User::ROLE_CLIENT, $this->company->id);
        $this->warehouse = $this->makeUser('gudang@prd21.test', User::ROLE_KEPALA_GUDANG);
        $this->finance = $this->makeUser('finance@prd21.test', User::ROLE_KEUANGAN);
        $this->owner = $this->makeUser('owner@prd21.test', User::ROLE_OWNER);
    }

    private function makeUser(string $email, string $role, ?int $companyId = null): User
    {
        $user = new User;
        $user->client_company_id = $companyId;
        $user->name = ucfirst(explode('@', $email)[0]);
        $user->email = $email;
        $user->password = Hash::make('Password123!');
        $user->role = $role;
        $user->is_active = true;
        $user->save();

        return $user;
    }

    private function createOrder(string $type = ServiceOrder::TYPE_RECONDITIONING, int $qty = 50): ServiceOrder
    {
        $payload = [
            'transaction_type' => $type,
            'drum_product_id' => $this->product->id,
            'requested_quantity' => $qty,
            'requested_unit' => 'Drum',
            'payment_method' => 'Virtual Account BCA',
        ];
        if ($type === ServiceOrder::TYPE_RECONDITIONING) {
            $payload['reconditioning_service_id'] = $this->service->id;
        }

        $this->actingAs($this->client)
            ->post(route('client.orders.store'), $payload)
            ->assertRedirect();

        return ServiceOrder::query()->latest('id')->firstOrFail();
    }

    public function test_fase_1_2_3_4_complete_end_to_end_prd_v2_1_acceptance(): void
    {
        // ============================================================
        // FASE 1: GATE-IN, BA SELISIH, COMPLIANCE DOCS & SERTIFIKAT QR
        // ============================================================
        $order = $this->createOrder(ServiceOrder::TYPE_RECONDITIONING, 50);

        // 1a. Gate-In dengan selisih/reject TANPA foto bukti harus ditolak
        $this->actingAs($this->warehouse)
            ->post(route('warehouse.orders.gate-in.store', $order->order_slug), [
                'plate_number' => 'B 9012 PKD',
                'driver_name' => 'Supriyanto',
                'qty_good' => 40,
                'qty_dent' => 5,
                'qty_reject' => 5,
            ])
            ->assertSessionHasErrors('evidence_photos');

        // 1b. Gate-In dengan foto bukti -> memicu BA Selisih & DISCREPANCY_PENDING
        $evidencePhoto = UploadedFile::fake()->create('drum-bocor.jpg', 100, 'image/jpeg');
        $this->actingAs($this->warehouse)
            ->post(route('warehouse.orders.gate-in.store', $order->order_slug), [
                'plate_number' => 'B 9012 PKD',
                'driver_name' => 'Supriyanto',
                'qty_good' => 40,
                'qty_dent' => 5,
                'qty_reject' => 5,
                'notes' => '5 drum bocor di bagian dasar',
                'evidence_photos' => [$evidencePhoto],
            ])
            ->assertRedirect();

        $order->refresh();
        $this->assertSame('DISCREPANCY_PENDING', $order->order_status);

        /** @var DiscrepancyReport $ba */
        $ba = $order->latestDiscrepancyReport()->firstOrFail();
        $this->assertStringStartsWith('BA-PKDI-', $ba->ba_number);
        $this->assertSame(DiscrepancyReport::STATUS_PENDING, $ba->status);

        // 1c. Gudang DILARANG menyerahkan ke Keuangan saat BA Selisih belum di-ACC
        $this->actingAs($this->warehouse)
            ->post(route('warehouse.orders.handover-finance', $order->order_slug))
            ->assertSessionHasErrors('discrepancy');

        // ============================================================
        // FASE 3 (4.2): CLIENT ACC DIGITAL BA SELISIH (MAGIC LINK + IP)
        // ============================================================
        $this->actingAs($this->client)
            ->get(route('client.discrepancies.show', $ba->magic_token))
            ->assertOk()
            ->assertSee($ba->ba_number);

        $this->actingAs($this->client)
            ->post(route('client.discrepancies.approve', $ba->id))
            ->assertRedirect();

        $ba->refresh();
        $order->refresh();
        $this->assertSame(DiscrepancyReport::STATUS_APPROVED, $ba->status);
        $this->assertNotNull($ba->approved_ip);
        $this->assertNotNull($ba->approved_at);
        $this->assertSame($this->client->name, $ba->approved_by_name);
        $this->assertSame('GATE_IN_VERIFIED', $order->order_status);

        // 1d. Gudang tetap DILARANG Serah ke Keuangan sebelum Surat Jalan & Sertifikat Rekondisi ada
        $this->actingAs($this->warehouse)
            ->post(route('warehouse.orders.handover-finance', $order->order_slug))
            ->assertSessionHasErrors('compliance_docs');

        // 1e. Unggah Surat Jalan + Terbitkan Sertifikat Rekondisi PDF (QR Code)
        $sjFile = UploadedFile::fake()->create('surat-jalan.pdf', 120, 'application/pdf');
        $this->actingAs($this->warehouse)
            ->post(route('warehouse.orders.compliance-docs.store', $order->order_slug), [
                'doc_type' => 'SURAT_JALAN',
                'document_number' => 'SJ-2026-001',
                'document_file' => $sjFile,
            ])
            ->assertRedirect();

        $this->actingAs($this->warehouse)
            ->post(route('warehouse.orders.recondition-cert.store', $order->order_slug))
            ->assertRedirect();

        /** @var ReconditionCert $cert */
        $cert = $order->reconditionCert()->firstOrFail();
        $this->assertStringStartsWith('CERT-PKDI-', $cert->cert_number);
        $this->assertSame(45, $cert->qc_passed_qty); // qty_good (40) + qty_dent (5)
        $this->assertNotEmpty($cert->qr_hash);

        // Unduh PDF Sertifikat Rekondisi
        $certPdfResp = $this->actingAs($this->warehouse)
            ->get(route('orders.recondition-cert.pdf', $order->order_slug));
        $certPdfResp->assertOk();
        $this->assertStringStartsWith('%PDF-1.4', (string) $certPdfResp->getContent());

        // 1f. Serah ke Keuangan (READY_FOR_BILLING) -> mengunci data kuantitas (Immutability)
        $this->actingAs($this->warehouse)
            ->post(route('warehouse.orders.handover-finance', $order->order_slug))
            ->assertRedirect();

        $order->refresh();
        $this->assertSame('READY_FOR_BILLING', $order->order_status);
        $this->assertTrue($order->is_locked);
        $this->assertSame(45, $order->billableTransaction->billable_quantity);

        // Immutability check: Gudang tidak boleh ubah Gate-In lagi setelah terkunci
        $this->actingAs($this->warehouse)
            ->post(route('warehouse.orders.gate-in.store', $order->order_slug), [
                'plate_number' => 'B 1111 XXX',
                'driver_name' => 'Ubah',
                'qty_good' => 50,
                'qty_dent' => 0,
                'qty_reject' => 0,
            ])
            ->assertSessionHasErrors('order_status');

        // ============================================================
        // FASE 2: TARIF KONTRAK, KALKULATOR PPN 11% & PPh 23 2%, e-FAKTUR, EKSPOR
        // ============================================================
        $this->actingAs($this->finance)
            ->post(route('finance.contracts.store'), [
                'client_company_id' => $this->company->id,
                'service_type' => $this->service->name,
                'drum_product_id' => $this->product->id,
                'unit_price' => 100000,
                'reject_disposal_fee' => 10000,
                'ppn_rate' => 11,
                'pph23_rate' => 2,
            ])
            ->assertRedirect(route('finance.contracts.index'));

        $this->assertDatabaseHas('client_contracts', [
            'client_company_id' => $this->company->id,
            'unit_price' => 100000,
        ]);

        $this->actingAs($this->finance)
            ->get(route('finance.contracts.index'))
            ->assertOk()
            ->assertSee('Manajemen Tarif Kontrak per Client');

        $this->actingAs($this->finance)
            ->get(route('finance.billable.show', $order->order_slug))
            ->assertOk()
            ->assertSee('Kalkulator Invoice');

        // Terbitkan Invoice: 45 drum * 100.000 + 50.000 reject fee = 4.550.000 DPP
        // PPN 11% = 500.500 | PPh 23 2% = 91.000 | Net Receivable = 4.550.000 + 500.500 - 91.000 = 4.959.500
        $this->actingAs($this->finance)
            ->post(route('finance.billable.store', $order->order_slug), [
                'use_prd_tax_calc' => 1,
                'invoice_action' => 'ISSUE',
                'description' => 'Jasa Rekondisi Kontrak',
                'quantity' => 45,
                'unit_price' => 100000,
                'additional_fees' => 50000,
                'ppn_rate' => 11,
                'pph23_rate' => 2,
            ])
            ->assertRedirect();

        /** @var Invoice $invoice */
        $invoice = $order->latestInvoice()->firstOrFail();
        $this->assertEquals(4550000.00, (float) $invoice->subtotal_amount);
        $this->assertEquals(500500.00, (float) $invoice->ppn_amount);
        $this->assertEquals(91000.00, (float) $invoice->pph23_amount);
        $this->assertEquals(4959500.00, (float) $invoice->net_receivable);
        $this->assertEquals(4959500.00, (float) $invoice->total_amount);
        $this->assertNotEmpty($invoice->qr_hash);

        // Unggah e-Faktur PDF
        $efakturFile = UploadedFile::fake()->create('efaktur.pdf', 80, 'application/pdf');
        $this->actingAs($this->finance)
            ->post(route('finance.invoices.efaktur.store', $invoice->invoice_slug), [
                'efaktur_number' => '010.000-26.00001234',
                'efaktur_file' => $efakturFile,
            ])
            ->assertRedirect();

        // Ekspor Coretax & Accurate CSV
        $coretaxResp = $this->actingAs($this->finance)
            ->get(route('finance.reports.export', ['format' => 'coretax']));
        $coretaxResp->assertOk();
        $this->assertStringContainsString('NOMOR_FAKTUR', (string) $coretaxResp->getContent());
        $this->assertStringContainsString('010.000-26.00001234', (string) $coretaxResp->getContent());

        $accResp = $this->actingAs($this->finance)
            ->get(route('finance.reports.export', ['format' => 'accounting']));
        $accResp->assertOk();
        $this->assertStringContainsString('NetReceivable', (string) $accResp->getContent());

        // ============================================================
        // FASE 3 (4.1 & 4.3): RE-ORDER, 1-CLICK ZIP BUNDLE (5 PDF), PPh 23 GATE
        // ============================================================
        $this->actingAs($this->client)
            ->post(route('client.orders.reorder', $order->order_slug))
            ->assertRedirect(route('client.orders.create', ['reorder' => $order->order_slug]));

        // Unduh 1-Click ZIP Bundle berisi 5 dokumen
        $zipResp = $this->actingAs($this->client)
            ->get(route('invoices.bundle-zip', $invoice->invoice_slug));
        $zipResp->assertOk();
        $tmpZip = tempnam(sys_get_temp_dir(), 'test_bundle_');
        file_put_contents($tmpZip, (string) $zipResp->getContent());
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($tmpZip) === true);
        $entries = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entries[] = $zip->getNameIndex($i);
        }
        $zip->close();
        @unlink($tmpZip);

        $this->assertContains('01_Invoice_PKDI.pdf', $entries);
        $this->assertContains('02_Faktur_Pajak_eFaktur.pdf', $entries);
        $this->assertContains('03_Surat_Jalan_Terkirim.pdf', $entries);
        $this->assertContains('04_Manifest_Limbah_B3.pdf', $entries);
        $this->assertContains('05_Sertifikat_Rekondisi_Drum.pdf', $entries);

        // Client unggah bukti bayar TANPA bukti potong PPh 23 -> Keuangan ditolak saat melunaskan
        $this->actingAs($this->client)
            ->post(route('client.invoices.payments.store', $invoice->invoice_slug), [
                'amount' => 4959500,
                'payment_date' => now()->toDateString(),
                'payment_method' => 'Virtual Account BCA',
                'reference_number' => 'REF-NO-PPH23',
                'proof_file' => UploadedFile::fake()->create('transfer.pdf', 50, 'application/pdf'),
            ])
            ->assertRedirect();

        $payment1 = $invoice->payments()->latest('id')->firstOrFail();
        $this->actingAs($this->finance)
            ->post(route('finance.invoices.payments.store', $invoice->invoice_slug), [
                'payment_id' => $payment1->id,
                'decision' => 'VERIFIED',
            ])
            ->assertSessionHasErrors('pph23_proof');

        // Client unggah bukti bayar BESERTA PDF Bukti Potong PPh 23 -> Keuangan verifikasi PAID sukses!
        $this->actingAs($this->client)
            ->post(route('client.invoices.payments.store', $invoice->invoice_slug), [
                'amount' => 4959500,
                'payment_date' => now()->toDateString(),
                'payment_method' => 'Virtual Account BCA',
                'reference_number' => 'REF-WITH-PPH23',
                'proof_file' => UploadedFile::fake()->create('transfer2.pdf', 50, 'application/pdf'),
                'pph23_proof_file' => UploadedFile::fake()->create('bupot-pph23.pdf', 50, 'application/pdf'),
            ])
            ->assertRedirect();

        $payment2 = $invoice->payments()->latest('id')->firstOrFail();
        $this->actingAs($this->finance)
            ->post(route('finance.invoices.payments.store', $invoice->invoice_slug), [
                'payment_id' => $payment2->id,
                'decision' => 'VERIFIED',
            ])
            ->assertRedirect();

        $invoice->refresh();
        $this->assertSame('PAID', $invoice->status);
        $this->assertTrue($invoice->pph23_verified);

        // ============================================================
        // FASE 4: PUBLIC QR VERIFICATION, SPLIT BILLING, MANAGER OVERRIDE & IMMUTABLE AUDIT
        // ============================================================
        $this->get(route('verify.document', $cert->qr_hash))
            ->assertOk()
            ->assertSee('Dokumen Ini Asli dan Terdaftar di Sistem PT Pulo Kencana Drum Industri')
            ->assertSee($cert->cert_number);

        $this->get(route('verify.document', $invoice->qr_hash))
            ->assertOk()
            ->assertSee('Dokumen Ini Asli dan Terdaftar di Sistem PT Pulo Kencana Drum Industri')
            ->assertSee($invoice->invoice_number);

        $this->get(route('verify.document', 'hash-palsu-tidak-ada'))
            ->assertOk()
            ->assertSee('Dokumen Tidak Terdaftar di Sistem PKDI');

        $this->actingAs($this->warehouse)
            ->get(route('warehouse.orders.show', $order->order_slug))
            ->assertOk()
            ->assertSee('READY_FOR_BILLING');

        $this->actingAs($this->finance)
            ->get(route('finance.invoices.index'))
            ->assertOk()
            ->assertSee('OVERDUE');

        $this->actingAs($this->finance)
            ->get(route('finance.invoices.show', $invoice->invoice_slug))
            ->assertOk()
            ->assertSee('e-Faktur');

        $this->actingAs($this->client)
            ->get(route('client.orders.show', $order->order_slug))
            ->assertOk()
            ->assertSee($cert->cert_number);

        $this->actingAs($this->client)
            ->get(route('client.invoices.show', $invoice->invoice_slug))
            ->assertOk()
            ->assertSee('Unduh Paket ZIP');

        $this->actingAs($this->owner)
            ->get(route('owner.transactions.show', $order->order_slug))
            ->assertOk()
            ->assertSee('Manager Override');

        // AuditLog Immutability check
        $latestAudit = AuditLog::query()->latest('id')->firstOrFail();
        $this->expectException(\LogicException::class);
        $latestAudit->delete();
    }

    public function test_fase_1_waste_transport_manifest_b3_gate_and_fase_4_split_billing_and_manager_override(): void
    {
        $wasteOrder = $this->createOrder(ServiceOrder::TYPE_WASTE_TRANSPORT, 50);

        // Gate-In dengan 45 baik + 5 reject (BA Selisih pending)
        $this->actingAs($this->warehouse)
            ->post(route('warehouse.orders.gate-in.store', $wasteOrder->order_slug), [
                'plate_number' => 'B 8899 PKD',
                'driver_name' => 'Dedi',
                'qty_good' => 45,
                'qty_dent' => 0,
                'qty_reject' => 5,
                'evidence_photos' => [UploadedFile::fake()->create('reject.jpg', 100, 'image/jpeg')],
            ])
            ->assertRedirect();

        // Fase 4 (5.1) Split Billing: Keuangan menagih parsial 45 drum tanpa menunggu sengketa selesai
        $this->actingAs($this->finance)
            ->post(route('finance.orders.split-billing.store', $wasteOrder->order_slug), [
                'split_qty' => 45,
                'unit_price' => 80000,
                'split_label' => 'Tagihan Parsial #1 (45 Drum Lolos)',
            ])
            ->assertRedirect();

        $splitInv = $wasteOrder->invoices()->where('is_split_invoice', true)->firstOrFail();
        $this->assertSame(45, $splitInv->billed_qty);

        // Fase 4 (5.1) Manager Override: Alasan < 20 karakter ditolak
        $this->actingAs($this->owner)
            ->post(route('owner.transactions.override', $wasteOrder->order_slug), [
                'override_reason' => 'Terlalu singkat',
            ])
            ->assertSessionHasErrors('override_reason');

        // Manager Override dengan alasan >= 20 karakter berhasil & tercatat di AuditLog
        $this->actingAs($this->owner)
            ->post(route('owner.transactions.override', $wasteOrder->order_slug), [
                'override_reason' => 'Persetujuan direksi untuk pencairan darurat sambil menunggu revisi Manifest Festronik dari KLHK.',
            ])
            ->assertRedirect();

        $wasteOrder->refresh();
        $this->assertTrue($wasteOrder->hasManagerOverride());
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'MANAGER_OVERRIDE_APPLIED',
            'auditable_id' => $wasteOrder->id,
        ]);

        // Setelah Manager Override, Gudang dapat menyerahkan ke Keuangan meskipun Manifest B3 belum diunggah
        $this->actingAs($this->warehouse)
            ->post(route('warehouse.orders.handover-finance', $wasteOrder->order_slug))
            ->assertRedirect();

        $wasteOrder->refresh();
        $this->assertSame('READY_FOR_BILLING', $wasteOrder->order_status);
        $this->assertTrue($wasteOrder->is_locked);
    }
}
