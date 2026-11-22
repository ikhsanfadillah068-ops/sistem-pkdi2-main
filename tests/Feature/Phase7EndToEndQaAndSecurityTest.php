<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CashbookEntry;
use App\Models\ClientCompany;
use App\Models\DrumProduct;
use App\Models\Invoice;
use App\Models\OperationalExpense;
use App\Models\Payment;
use App\Models\ReconditioningService;
use App\Models\ServiceOrder;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\TestScenarioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase7EndToEndQaAndSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_end_to_end_journey_across_four_active_roles_without_satpam(): void
    {
        $this->seed(MasterDataSeeder::class);
        $this->artisan('pkdi:setup-internal-accounts')
            ->assertExitCode(0);

        $gudang = User::query()->where('role', User::ROLE_KEPALA_GUDANG)->firstOrFail();
        $keuangan = User::query()->where('role', User::ROLE_KEUANGAN)->firstOrFail();
        $owner = User::query()->where('role', User::ROLE_OWNER)->firstOrFail();
        $drumBesi = DrumProduct::query()->where('code', 'PRD-DRUM-BESI')->firstOrFail();
        $paketCat = ReconditioningService::query()->where('code', 'REC-LAUNDRY-CAT')->firstOrFail();

        // Client registrasi mandiri
        $this->post('/register', [
            'company_name' => 'PT Krakatau Drum Logistik',
            'pic_name' => 'Darmawan Putra',
            'phone' => '081288990011',
            'address' => 'Kawasan Industri Krakatau Cilegon, Banten',
            'email' => 'darmawan@krakataudrum.co.id',
            'password' => 'ClientPass123!',
            'password_confirmation' => 'ClientPass123!',
        ])->assertRedirect('/client/dashboard');

        $client = User::query()->where('email', 'darmawan@krakataudrum.co.id')->firstOrFail();

        // Client mengajukan Jasa Rekondisi langsung ke antrean Kepala Gudang
        $this->actingAs($client)->post('/client/orders', [
            'transaction_type' => ServiceOrder::TYPE_RECONDITIONING,
            'drum_product_id' => $drumBesi->id,
            'reconditioning_service_id' => $paketCat->id,
            'cargo_type' => 'Bekas Pelumas Sintetis',
            'requested_quantity' => 50,
            'requested_unit' => 'Drum',
            'po_number' => 'PO/KDL/2026/11/001',
            'origin_company_name' => 'PT Krakatau Drum Logistik',
            'specification_notes' => 'Cat biru tua.',
        ])->assertRedirect();

        $order = ServiceOrder::query()->where('po_number', 'PO/KDL/2026/11/001')->firstOrFail();
        $this->assertSame('UNDER_REVIEW', $order->order_status);
        $this->assertSame('NOT_REQUIRED', $order->gate_status);

        // Kepala Gudang memeriksa muatan, mencatat progres, dan menyelesaikan hasil kerja
        $this->actingAs($gudang)->post("/warehouse/orders/{$order->order_slug}/inspect", [
            'decision' => 'APPROVED',
            'actual_cargo_type' => 'Bekas Pelumas Sintetis',
            'actual_quantity' => 50,
            'actual_unit' => 'Drum',
            'cargo_condition' => '48 drum layak rekondisi, 2 drum penyok ringan.',
            'discrepancy_notes' => 'Jumlah sesuai 50 drum.',
        ])->assertRedirect();

        $this->actingAs($gudang)->post("/warehouse/orders/{$order->order_slug}/processes", [
            'process_stage' => 'Pencucian & Pengecatan Biru Tua',
            'progress_notes' => 'Seluruh 48 drum selesai dicat dan dikeringkan.',
            'processed_qty' => 48,
        ])->assertRedirect();

        $this->actingAs($gudang)->post("/warehouse/orders/{$order->order_slug}/result", [
            'actual_service_type' => 'Laundry + Cat',
            'completed_qty' => 48,
            'rejected_qty' => 2,
            'result_notes' => '48 drum selesai direkondisi, 2 drum bocor halus tidak ditagihkan.',
        ])->assertRedirect();

        // Client menerima pembaruan status siap diserahkan
        $this->actingAs($client)
            ->get("/client/orders/{$order->order_slug}")
            ->assertOk()
            ->assertSee('Siap Diserahkan')
            ->assertSee('48 Drum');

        // Keuangan menerbitkan invoice dari hasil kerja aktual
        $this->actingAs($keuangan)->post("/finance/billable-orders/{$order->order_slug}/invoices", [
            'invoice_action' => 'ISSUE',
            'description' => 'Jasa Rekondisi Drum Besi (Laundry + Cat)',
            'quantity' => 48,
            'unit_price' => 85000,
            'adjustment_amount' => 0,
            'tax_amount' => 0,
        ])->assertRedirect();

        $invoice = Invoice::query()->where('service_order_id', $order->id)->firstOrFail();
        $this->assertSame('ISSUED', $invoice->status);
        $this->assertSame(4080000.0, (float) $invoice->total_amount);

        // Client mengunggah bukti pembayaran transfer manual
        $this->actingAs($client)->post("/client/invoices/{$invoice->invoice_slug}/payments", [
            'amount' => 4080000,
            'payment_date' => '2026-11-17',
            'payment_method' => 'Transfer Bank Mandiri',
            'reference_number' => 'MDR-KDL-20261117',
        ])->assertRedirect();

        $payment = Payment::query()->where('reference_number', 'MDR-KDL-20261117')->firstOrFail();
        $this->assertSame('PENDING_VERIFICATION', $payment->verification_status);

        // Keuangan memverifikasi pembayaran
        $this->actingAs($keuangan)->post("/finance/invoices/{$invoice->invoice_slug}/payments", [
            'payment_id' => $payment->id,
            'decision' => 'VERIFIED',
        ])->assertRedirect();

        $order->refresh();
        $invoice->refresh();
        $this->assertSame('PAID', $invoice->status);
        $this->assertSame('PAID', $order->payment_status);
        $this->assertSame('INVOICED', $order->billing_status);

        // Owner memantau transaksi, laporan keuangan, dan jejak audit
        $this->actingAs($owner)
            ->get("/owner/transactions/{$order->order_slug}")
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee($invoice->invoice_number);

        $this->actingAs($owner)
            ->get('/owner/financial-summary')
            ->assertOk()
            ->assertSee('Rp 4.080.000');

        $this->assertGreaterThanOrEqual(7, AuditLog::query()->count());
    }

    public function test_authorization_matrix_and_direct_endpoint_protection_across_all_active_roles(): void
    {
        $this->seed(TestScenarioSeeder::class);

        $clientA = User::query()->where('email', 'client@pkdi.co.id')->firstOrFail();
        $companyB = ClientCompany::factory()->create(['company_name' => 'PT Mitra Kedua']);
        $clientB = User::factory()->client($companyB)->create(['email' => 'client2@mitrakedua.co.id']);
        $gudang = User::query()->where('role', User::ROLE_KEPALA_GUDANG)->firstOrFail();
        $keuangan = User::query()->where('role', User::ROLE_KEUANGAN)->firstOrFail();
        $owner = User::query()->where('role', User::ROLE_OWNER)->firstOrFail();

        $invA = Invoice::query()->where('invoice_number', 'INV/PKDI/2026/11/0001')->firstOrFail();
        $orderA = ServiceOrder::query()->where('order_number', 'selasa-17/11/26-3')->firstOrFail();

        $this->actingAs($gudang)->get("/invoices/{$invA->invoice_slug}/download-pdf")->assertForbidden();
        $this->actingAs($clientB)->get("/invoices/{$invA->invoice_slug}/download-pdf")->assertForbidden();

        $this->actingAs($clientA)->get("/invoices/{$invA->invoice_slug}/download-pdf")->assertOk();
        $this->actingAs($keuangan)->get("/invoices/{$invA->invoice_slug}/download-pdf")->assertOk();
        $this->actingAs($owner)->get("/invoices/{$invA->invoice_slug}/download-pdf")->assertOk();

        foreach ([$clientA, $gudang] as $unauthorizedUser) {
            $this->actingAs($unauthorizedUser)->get('/finance/reports/export?format=pdf')->assertForbidden();
            $this->actingAs($unauthorizedUser)->get('/owner/reports/export?format=xlsx')->assertForbidden();
        }

        // Endpoint POST langsung lintas role ditolak
        $this->actingAs($clientA)->post("/warehouse/orders/{$orderA->order_slug}/inspect", ['decision' => 'APPROVED'])->assertForbidden();
        $this->actingAs($gudang)->post("/finance/invoices/{$invA->invoice_slug}/payments", ['decision' => 'VERIFIED'])->assertForbidden();
        $this->actingAs($keuangan)->post("/warehouse/orders/{$orderA->order_slug}/inspect", ['decision' => 'APPROVED'])->assertForbidden();
        $this->actingAs($owner)->post("/warehouse/orders/{$orderA->order_slug}/inspect", ['decision' => 'APPROVED'])->assertForbidden();

        // Kode dokumen yang tidak ada harus mengembalikan 404 Not Found
        $this->actingAs($gudang)->get('/warehouse/orders/tidak-ada-999')->assertNotFound();
        $this->actingAs($keuangan)->get('/finance/invoices/INV-TIDAK-ADA-999')->assertNotFound();
        $this->actingAs($owner)->get('/owner/transactions/tidak-ada-999')->assertNotFound();
    }

    public function test_financial_edge_cases_negative_extreme_values_overpayment_and_reconciliation(): void
    {
        $this->seed(TestScenarioSeeder::class);

        $clientA = User::query()->where('email', 'client@pkdi.co.id')->firstOrFail();
        $keuangan = User::query()->where('role', User::ROLE_KEUANGAN)->firstOrFail();

        $billableOrder = ServiceOrder::query()->where('order_number', 'selasa-17/11/26-3')->firstOrFail();

        $this->actingAs($keuangan)->post("/finance/billable-orders/{$billableOrder->order_slug}/invoices", [
            'invoice_action' => 'ISSUE',
            'description' => 'Tes Negatif',
            'quantity' => -5,
            'unit_price' => -10000,
        ])->assertSessionHasErrors(['quantity', 'unit_price']);

        $this->actingAs($keuangan)->post("/finance/billable-orders/{$billableOrder->order_slug}/invoices", [
            'invoice_action' => 'ISSUE',
            'description' => 'Tes Ekstrem',
            'quantity' => 50,
            'unit_price' => 9999999999999,
        ])->assertSessionHasErrors(['unit_price']);

        $this->actingAs($keuangan)->post("/finance/billable-orders/{$billableOrder->order_slug}/invoices", [
            'invoice_action' => 'ISSUE',
            'description' => 'Jasa Rekondisi Drum Besi',
            'quantity' => 48,
            'unit_price' => 65000,
        ])->assertRedirect();

        $unpaidInvoice = Invoice::query()->where('service_order_id', $billableOrder->id)->firstOrFail();

        $this->actingAs($clientA)->post("/client/invoices/{$unpaidInvoice->invoice_slug}/payments", [
            'amount' => 5000000,
            'payment_date' => '2026-11-17',
            'payment_method' => 'Transfer Bank BCA',
            'reference_number' => 'OVERPAY-001',
        ])->assertSessionHasErrors(['amount']);

        $this->actingAs($keuangan)->post('/finance/expenses', [
            'expense_category' => 'Pembelian Bahan',
            'expense_date' => '2026-11-17',
            'description' => 'Tes negatif',
            'amount' => -50000,
            'payment_method' => 'Tunai',
        ])->assertSessionHasErrors(['amount']);

        $this->assertSame(
            (float) Payment::query()->where('verification_status', 'VERIFIED')->sum('amount'),
            (float) CashbookEntry::query()->where('status', 'ACTIVE')->where('entry_type', 'IN')->sum('amount')
        );
        $this->assertSame(
            (float) OperationalExpense::query()->where('status', 'RECORDED')->sum('amount'),
            (float) CashbookEntry::query()->where('status', 'ACTIVE')->where('entry_type', 'OUT')->sum('amount')
        );
    }

    public function test_responsive_accessibility_and_antislop_delivery_gate_invariants(): void
    {
        $this->seed();

        $owner = User::query()->where('role', User::ROLE_OWNER)->firstOrFail();
        $response = $this->actingAs($owner)->get('/owner/dashboard');
        $response->assertOk();

        $html = (string) $response->getContent();

        $this->assertStringContainsString('<html lang="id">', $html);
        $this->assertStringContainsString('href="#page-root"', $html);
        $this->assertStringContainsString('id="page-root"', $html);
        $this->assertStringContainsString('text-brand-accent', $html);
        $this->assertStringContainsString('overflow-x-auto', $html);
        $this->assertStringNotContainsString('—', $html);
        $this->assertStringNotContainsString('data-demo-form', $html);
        $this->assertStringNotContainsString('[DATA DEMO]', $html);

        $css = (string) file_get_contents(base_path('resources/css/app.css'));
        $this->assertStringContainsString('prefers-reduced-motion: reduce', $css);
        $this->assertStringContainsString('cursor: pointer', $css);
        $this->assertStringContainsString('focus-visible', $css);
    }
}
