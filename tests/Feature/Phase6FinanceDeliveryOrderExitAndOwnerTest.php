<?php

namespace Tests\Feature;

use App\Models\CashbookEntry;
use App\Models\ClientCompany;
use App\Models\Invoice;
use App\Models\OperationalExpense;
use App\Models\Payment;
use App\Models\ServiceOrder;
use App\Models\User;
use Database\Seeders\TestScenarioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase6FinanceDeliveryOrderExitAndOwnerTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_is_created_only_from_valid_completed_warehouse_work_and_isolated_per_client(): void
    {
        $this->seed(TestScenarioSeeder::class);

        $keuangan = User::query()->where('role', User::ROLE_KEUANGAN)->firstOrFail();
        $clientA = User::query()->where('email', 'client@pkdi.co.id')->firstOrFail();
        $companyB = ClientCompany::factory()->create(['company_name' => 'PT Mitra Kedua']);
        $clientB = User::factory()->client($companyB)->create(['email' => 'client2@mitrakedua.co.id']);

        $unreadyOrder = ServiceOrder::query()->where('order_number', 'selasa-17/11/26-5')->firstOrFail();
        $this->actingAs($keuangan)->post("/finance/billable-orders/{$unreadyOrder->order_slug}/invoices", [
            'invoice_action' => 'ISSUE',
            'description' => 'Jasa Rekondisi',
            'quantity' => 80,
            'unit_price' => 85000,
        ])->assertSessionHasErrors(['order_status']);

        $billableOrder = ServiceOrder::query()->where('order_number', 'selasa-17/11/26-3')->firstOrFail();

        $this->actingAs($keuangan)->post("/finance/billable-orders/{$billableOrder->order_slug}/invoices", [
            'invoice_action' => 'DRAFT',
            'tariff_id' => 'TRF-REPAIR-B200',
            'description' => 'Jasa Perbaikan Drum Besi 200L (Hasil Aktual Gudang)',
            'quantity' => 48,
            'unit_price' => 65000,
            'adjustment_amount' => 120000,
            'tax_amount' => 0,
        ])->assertRedirect();

        $invoice = Invoice::query()->where('service_order_id', $billableOrder->id)->firstOrFail();
        $this->assertSame('DRAFT', $invoice->status);
        $this->assertSame(3000000.0, (float) $invoice->total_amount);

        $this->actingAs($clientA)->get("/client/invoices/{$invoice->invoice_slug}")->assertForbidden();
        $this->actingAs($clientA)->get("/invoices/{$invoice->invoice_slug}/download-pdf")->assertForbidden();

        $this->actingAs($keuangan)->post("/finance/billable-orders/{$billableOrder->order_slug}/invoices", [
            'invoice_action' => 'ISSUE',
            'tariff_id' => 'TRF-REPAIR-B200',
            'description' => 'Jasa Perbaikan Drum Besi 200L (Hasil Aktual Gudang)',
            'quantity' => 48,
            'unit_price' => 65000,
            'adjustment_amount' => 120000,
            'tax_amount' => 0,
        ])->assertRedirect();

        $invoice->refresh();
        $billableOrder->refresh();
        $this->assertSame('ISSUED', $invoice->status);
        $this->assertSame('INVOICED', $billableOrder->billing_status);
        $this->assertSame('UNPAID', $billableOrder->payment_status);

        $this->actingAs($keuangan)
            ->get("/finance/invoices/{$invoice->invoice_slug}")
            ->assertOk()
            ->assertSee('Rincian Tagihan Invoice')
            ->assertSee('Menunggu Pembayaran dari Client')
            ->assertDontSee('Catat Pembayaran Langsung');

        $this->actingAs($clientA)
            ->get("/client/invoices/{$invoice->invoice_slug}")
            ->assertOk()
            ->assertSee($invoice->invoice_number)
            ->assertSee('3.000.000');

        $pdfResponse = $this->actingAs($clientA)
            ->get("/invoices/{$invoice->invoice_slug}/download-pdf")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-1.4', (string) $pdfResponse->getContent());

        $this->actingAs($clientB)->get("/client/invoices/{$invoice->invoice_slug}")->assertForbidden();
        $this->actingAs($clientB)->get("/invoices/{$invoice->invoice_slug}/download-pdf")->assertForbidden();

        $this->actingAs($keuangan)->put("/finance/invoices/{$invoice->invoice_slug}/revise", [
            'revised_total' => 2900000,
            'revision_reason' => '',
        ])->assertSessionHasErrors(['revision_reason']);

        $this->actingAs($keuangan)->put("/finance/invoices/{$invoice->invoice_slug}/revise", [
            'revised_total' => 2900000,
            'revision_reason' => 'Potongan kompensasi 1 drum penyok saat bongkar muat.',
        ])->assertRedirect();

        $invoice->refresh();
        $this->assertSame(1, $invoice->revision_number);
        $this->assertSame(2900000.0, (float) $invoice->total_amount);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'INVOICE_REVISED',
            'auditable_type' => 'Invoice',
            'auditable_id' => $invoice->id,
        ]);
    }

    public function test_paid_status_only_after_finance_verification_and_prevents_duplicate_payment_recording(): void
    {
        $this->seed(TestScenarioSeeder::class);

        $clientA = User::query()->where('email', 'client@pkdi.co.id')->firstOrFail();
        $gudang = User::query()->where('role', User::ROLE_KEPALA_GUDANG)->firstOrFail();
        $keuangan = User::query()->where('role', User::ROLE_KEUANGAN)->firstOrFail();

        $billableOrder = ServiceOrder::query()->where('order_number', 'selasa-17/11/26-3')->firstOrFail();
        $this->actingAs($keuangan)->post("/finance/billable-orders/{$billableOrder->order_slug}/invoices", [
            'invoice_action' => 'ISSUE',
            'description' => 'Jasa Perbaikan Drum Besi 200L',
            'quantity' => 48,
            'unit_price' => 65000,
            'adjustment_amount' => 170000,
            'tax_amount' => 0,
        ])->assertRedirect();

        $invoice = Invoice::query()->where('service_order_id', $billableOrder->id)->firstOrFail();
        $order = $invoice->serviceOrder;

        $this->actingAs($clientA)->post("/client/invoices/{$invoice->invoice_slug}/payments", [
            'amount' => 2950000,
            'payment_date' => '2026-11-17',
            'payment_method' => 'Transfer Bank BCA',
            'reference_number' => 'TRX-UNIQUE-9901',
        ])->assertRedirect();

        $newPayment = Payment::query()->where('reference_number', 'TRX-UNIQUE-9901')->firstOrFail();

        $invoice->refresh();
        $order->refresh();
        $this->assertSame('PENDING_VERIFICATION', $newPayment->verification_status);
        $this->assertSame('ISSUED', $invoice->status);
        $this->assertSame(2950000.0, (float) $invoice->balance_due);
        $this->assertDatabaseMissing('cashbook_entries', ['payment_id' => $newPayment->id]);

        $this->actingAs($clientA)->post("/client/invoices/{$invoice->invoice_slug}/payments", [
            'amount' => 2950000,
            'payment_date' => '2026-11-17',
            'payment_method' => 'Transfer Bank BCA',
            'reference_number' => 'TRX-UNIQUE-9901',
        ])->assertSessionHasErrors(['reference_number']);

        $this->actingAs($gudang)->post("/finance/invoices/{$invoice->invoice_slug}/payments", [
            'payment_id' => $newPayment->id,
            'decision' => 'VERIFIED',
        ])->assertForbidden();

        $this->actingAs($keuangan)->post("/finance/invoices/{$invoice->invoice_slug}/payments", [
            'payment_id' => $newPayment->id,
            'decision' => 'VERIFIED',
        ])->assertRedirect();

        $newPayment->refresh();
        $invoice->refresh();
        $order->refresh();

        $this->assertSame('VERIFIED', $newPayment->verification_status);
        $this->assertSame('PAID', $invoice->status);
        $this->assertSame(0.0, (float) $invoice->balance_due);
        $this->assertSame('PAID', $order->payment_status);
        $this->assertSame('INVOICED', $order->billing_status);

        $this->assertDatabaseHas('cashbook_entries', [
            'payment_id' => $newPayment->id,
            'entry_type' => 'IN',
            'amount' => 2950000.00,
        ]);

        $this->actingAs($keuangan)->post("/finance/invoices/{$invoice->invoice_slug}/payments", [
            'payment_id' => $newPayment->id,
            'decision' => 'VERIFIED',
        ])->assertSessionHasErrors(['payment']);

        $this->assertSame(
            1,
            CashbookEntry::query()->where('payment_id', $newPayment->id)->count()
        );
    }

    public function test_cashbook_consistency_operational_expenses_pdf_excel_exports_and_owner_recap(): void
    {
        $this->seed();

        $keuangan = User::query()->where('role', User::ROLE_KEUANGAN)->firstOrFail();
        $owner = User::query()->where('role', User::ROLE_OWNER)->firstOrFail();

        $this->actingAs($keuangan)->post('/finance/expenses', [
            'expense_category' => 'Gaji Karyawan',
            'expense_date' => '2026-11-17',
            'description' => 'Upah lembur tim rekondisi drum gudang',
            'amount' => 1750000,
            'vendor' => 'Tim Operasional Gudang',
            'payment_method' => 'Transfer Bank Mandiri',
        ])->assertRedirect('/finance/expenses');

        $newExpense = OperationalExpense::query()->where('amount', 1750000)->firstOrFail();
        $this->assertDatabaseHas('cashbook_entries', [
            'operational_expense_id' => $newExpense->id,
            'entry_type' => 'OUT',
            'amount' => 1750000.00,
        ]);

        $verifiedPaymentsSum = (float) Payment::query()->where('verification_status', 'VERIFIED')->sum('amount');
        $recordedExpensesSum = (float) OperationalExpense::query()->where('status', 'RECORDED')->sum('amount');

        $cashbookInSum = (float) CashbookEntry::query()->where('status', 'ACTIVE')->where('entry_type', 'IN')->sum('amount');
        $cashbookOutSum = (float) CashbookEntry::query()->where('status', 'ACTIVE')->where('entry_type', 'OUT')->sum('amount');

        $this->assertSame($verifiedPaymentsSum, $cashbookInSum);
        $this->assertSame($recordedExpensesSum, $cashbookOutSum);

        $pdfExport = $this->actingAs($keuangan)
            ->get('/finance/reports/export?report_type=expenses&category=Gaji+Karyawan&from=2026-11-01&to=2026-11-30&format=pdf')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $pdfContent = (string) $pdfExport->getContent();
        $this->assertStringStartsWith('%PDF-1.4', $pdfContent);
        $this->assertStringContainsString('Gaji Karyawan', $pdfContent);
        $this->assertStringContainsString('Rp 1.750.000', $pdfContent);

        $xlsxExport = $this->actingAs($keuangan)
            ->get('/finance/reports/export?report_type=cashbook&entry_type=IN&format=xlsx')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->assertStringStartsWith('PK', (string) $xlsxExport->getContent());

        $expectedFormattedExpenses = 'Rp '.number_format($recordedExpensesSum, 0, ',', '.');
        $expectedFormattedPaid = 'Rp '.number_format($verifiedPaymentsSum, 0, ',', '.');

        $this->actingAs($owner)
            ->get('/owner/financial-summary')
            ->assertOk()
            ->assertSee($expectedFormattedExpenses)
            ->assertSee($expectedFormattedPaid);

        $this->actingAs($owner)
            ->get('/owner/reports/export?report_type=cashbook&format=pdf')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->actingAs($owner)->post('/finance/expenses', [
            'expense_category' => 'Lainnya',
            'expense_date' => '2026-11-17',
            'description' => 'Coba input dari Owner',
            'amount' => 100000,
            'payment_method' => 'Tunai',
        ])->assertForbidden();
    }
}
