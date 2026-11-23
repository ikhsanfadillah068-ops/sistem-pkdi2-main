<?php

namespace App\Http\Controllers\Preview;

use App\Http\Controllers\Controller;
use App\Models\ClientCompany;
use App\Models\ClientContract;
use App\Models\CompanyPaymentAccount;
use App\Models\DrumProduct;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\ServiceOrder;
use App\Models\ServiceTariff;
use App\Models\User;
use App\Services\BillingAndExitWorkflowService;
use App\Services\PrdV21WorkflowService;
use App\Services\ReportExportService;
use App\Support\AuditLogger;
use App\Support\OrderPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class FinancePreviewController extends Controller
{
    public function dashboard(Request $request): View
    {
        $summary = OrderPresenter::financialSummary();
        $operational = OrderPresenter::operationalSummary();
        $billableTransactions = OrderPresenter::billableTransactions(['billing_status' => 'READY_TO_BILL']);
        $invoices = OrderPresenter::allInvoices();

        return view('finance.dashboard', [
            'billableTransactions' => $billableTransactions,
            'invoices' => $invoices,
            'expenses' => OrderPresenter::expenses(),
            'cashbook' => OrderPresenter::cashbookEntries(),
            'summary' => $summary,
            'operational' => $operational,
        ]);
    }

    public function billableIndex(Request $request): View
    {
        $filters = [
            'client_company_id' => trim((string) $request->query('client_company_id', '')),
            'transaction_type' => strtoupper(trim((string) $request->query('transaction_type', ''))),
            'billing_status' => strtoupper(trim((string) $request->query('billing_status', 'READY_TO_BILL'))),
            'from' => trim((string) $request->query('from', '')),
            'to' => trim((string) $request->query('to', '')),
            'q' => trim((string) $request->query('q', '')),
        ];

        $transactions = OrderPresenter::billableTransactions($filters);

        // Sertakan juga pesanan selesai warisan yang belum memiliki baris billable_transactions
        $legacyReadyOrders = array_values(array_filter(
            OrderPresenter::allOrders(),
            static fn (array $o): bool => empty($o['billable_transaction'])
                && empty($o['invoice'])
                && ! empty($o['result'])
                && ($o['billing_status'] === 'READY_TO_BILL' || $o['order_status'] === 'WAITING_FINANCE')
        ));

        return view('finance.billable.index', [
            'transactions' => $transactions,
            'legacyReadyOrders' => $legacyReadyOrders,
            'clients' => ClientCompany::query()->orderBy('company_name')->get(),
            'tariffs' => OrderPresenter::tariffs(),
            'filters' => $filters,
        ]);
    }

    public function combinedInvoiceStore(
        Request $request,
        BillingAndExitWorkflowService $billing,
    ): RedirectResponse {
        /** @var User $keuangan */
        $keuangan = $request->user();

        $validated = $request->validate([
            'billable_transaction_ids' => ['required', 'array', 'min:1'],
            'billable_transaction_ids.*' => ['integer', 'exists:billable_transactions,id'],
            'invoice_action' => ['nullable', 'string', 'in:DRAFT,ISSUE'],
            'unit_prices' => ['nullable', 'array'],
            'unit_prices.*' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'tariff_ids' => ['nullable', 'array'],
            'tariff_ids.*' => ['nullable', 'string', 'max:60'],
            'descriptions' => ['nullable', 'array'],
            'descriptions.*' => ['nullable', 'string', 'max:255'],
            'adjustment_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'additional_fees' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'tax_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'ppn_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'pph23_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'use_prd_tax_calc' => ['nullable', 'boolean'],
            'issue_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'payment_instructions' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'billable_transaction_ids.required' => 'Pilih minimal satu transaksi siap ditagihkan.',
            'billable_transaction_ids.min' => 'Pilih minimal satu transaksi siap ditagihkan.',
        ]);

        $invoice = $billing->createCombinedInvoice(
            keuangan: $keuangan,
            validated: $validated,
            request: $request,
        );

        $msg = $invoice->status === 'DRAFT'
            ? "Draft invoice {$invoice->invoice_number} berhasil disimpan."
            : "Invoice {$invoice->invoice_number} berhasil diterbitkan ke Portal Client.";

        return redirect()
            ->route('finance.invoices.show', $invoice->invoice_slug)
            ->with('status', $msg);
    }

    public function billableShow(Request $request, string $code, PrdV21WorkflowService $prd): View
    {
        $dbOrder = ServiceOrder::findByCodeOrSlug($code)
            ?? (ctype_digit($code) ? ServiceOrder::query()->find((int) $code) : null);

        abort_if(! $dbOrder, 404, 'Pesanan tidak ditemukan.');
        Gate::authorize('manageBilling', $dbOrder);

        return view('finance.billable.show', [
            'order' => OrderPresenter::toArray($dbOrder),
            'tariffs' => OrderPresenter::tariffs(),
            'contractPricing' => $prd->resolveContractPricing($dbOrder),
        ]);
    }

    public function billableStore(
        Request $request,
        string $code,
        BillingAndExitWorkflowService $billing,
    ): RedirectResponse {
        /** @var User $keuangan */
        $keuangan = $request->user();

        $order = ServiceOrder::findByCodeOrSlug($code)
            ?? (ctype_digit($code) ? ServiceOrder::query()->find((int) $code) : null);

        abort_if(! $order, 404, 'Pesanan tidak ditemukan.');
        Gate::authorize('manageBilling', $order);

        $validated = $request->validate([
            'invoice_action' => ['nullable', 'string', 'in:DRAFT,ISSUE'],
            'tariff_id' => ['nullable', 'string', 'max:60'],
            'description' => ['required', 'string', 'min:3', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'unit_price' => ['required', 'numeric', 'min:1', 'max:999999999'],
            'adjustment_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'additional_fees' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'tax_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'ppn_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'pph23_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'use_prd_tax_calc' => ['nullable', 'boolean'],
            'issue_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'description.required' => 'Uraian jasa pada invoice wajib diisi.',
            'quantity.required' => 'Kuantitas yang ditagihkan wajib diisi.',
            'unit_price.required' => 'Harga satuan wajib diisi.',
        ]);

        $invoice = $billing->saveOrIssueInvoice(
            keuangan: $keuangan,
            order: $order,
            validated: $validated,
            request: $request,
        );

        $msg = $invoice->status === 'DRAFT'
            ? "Draft tagihan {$invoice->invoice_number} berhasil disimpan."
            : "Tagihan {$invoice->invoice_number} berhasil diterbitkan ke Portal Client.";

        return redirect()
            ->route('finance.invoices.show', $invoice->invoice_slug)
            ->with('status', $msg);
    }

    public function invoicesIndex(Request $request): View
    {
        return view('finance.invoices.index', [
            'invoices' => OrderPresenter::allInvoices(),
        ]);
    }

    public function invoicesShow(Request $request, string $code): View
    {
        $dbInvoice = Invoice::findByCodeOrSlug($code)
            ?? (ctype_digit($code) ? Invoice::query()->find((int) $code) : null);

        abort_if(! $dbInvoice, 404, 'Invoice tidak ditemukan.');
        Gate::authorize('view', $dbInvoice);

        return view('finance.invoices.show', [
            'invoice' => OrderPresenter::invoiceToArray($dbInvoice),
            'paymentAccounts' => CompanyPaymentAccount::query()->active()->get(),
        ]);
    }

    public function invoicesPublish(
        Request $request,
        string $code,
        BillingAndExitWorkflowService $billing,
    ): RedirectResponse {
        /** @var User $keuangan */
        $keuangan = $request->user();

        $invoice = Invoice::findByCodeOrSlug($code)
            ?? (ctype_digit($code) ? Invoice::query()->find((int) $code) : null);

        abort_if(! $invoice, 404, 'Invoice tidak ditemukan.');
        Gate::authorize('update', $invoice);

        $billing->publishDraftInvoice($keuangan, $invoice, $request);

        return redirect()
            ->route('finance.invoices.show', $invoice->invoice_slug)
            ->with('status', "Invoice {$invoice->invoice_number} resmi diterbitkan ke Portal Client.");
    }

    public function invoicesVoid(
        Request $request,
        string $code,
        BillingAndExitWorkflowService $billing,
    ): RedirectResponse {
        /** @var User $keuangan */
        $keuangan = $request->user();

        $invoice = Invoice::findByCodeOrSlug($code)
            ?? (ctype_digit($code) ? Invoice::query()->find((int) $code) : null);

        abort_if(! $invoice, 404, 'Invoice tidak ditemukan.');
        Gate::authorize('update', $invoice);

        $validated = $request->validate([
            'void_reason' => ['required', 'string', 'min:5', 'max:2000'],
        ], [
            'void_reason.required' => 'Alasan pembatalan invoice wajib diisi.',
            'void_reason.min' => 'Alasan pembatalan invoice minimal 5 karakter.',
        ]);

        $billing->voidInvoice($keuangan, $invoice, $validated, $request);

        return redirect()
            ->route('finance.invoices.show', $invoice->invoice_slug)
            ->with('status', "Invoice {$invoice->invoice_number} dibatalkan (VOIDED) dan transaksi terkait dikembalikan ke antrean penagihan.");
    }

    public function invoicesRevise(
        Request $request,
        string $code,
        BillingAndExitWorkflowService $billing,
    ): RedirectResponse {
        /** @var User $keuangan */
        $keuangan = $request->user();

        $invoice = Invoice::findByCodeOrSlug($code)
            ?? (ctype_digit($code) ? Invoice::query()->find((int) $code) : null);

        abort_if(! $invoice, 404, 'Invoice tidak ditemukan.');
        Gate::authorize('update', $invoice);

        $validated = $request->validate([
            'revised_total' => ['required', 'numeric', 'min:1', 'max:999999999999'],
            'revision_reason' => ['required', 'string', 'min:5', 'max:2000'],
        ], [
            'revised_total.required' => 'Nominal koreksi baru wajib diisi.',
            'revision_reason.required' => 'Alasan revisi tagihan wajib diisi.',
            'revision_reason.min' => 'Alasan revisi tagihan minimal 5 karakter.',
        ]);

        $billing->reviseInvoice(
            keuangan: $keuangan,
            invoice: $invoice,
            validated: $validated,
            request: $request,
        );

        return redirect()
            ->route('finance.invoices.show', $invoice->invoice_slug)
            ->with('status', "Invoice {$invoice->invoice_number} berhasil direvisi dan tercatat di Audit Log.");
    }

    public function paymentsStore(
        Request $request,
        string $code,
        BillingAndExitWorkflowService $billing,
    ): RedirectResponse {
        /** @var User $keuangan */
        $keuangan = $request->user();

        $invoice = Invoice::findByCodeOrSlug($code)
            ?? (ctype_digit($code) ? Invoice::query()->find((int) $code) : null);

        abort_if(! $invoice, 404, 'Invoice tidak ditemukan.');
        Gate::authorize('verifyPayment', $invoice);

        $validated = $request->validate([
            'payment_id' => ['nullable', 'integer'],
            'decision' => ['nullable', 'string', 'in:VERIFIED,REJECTED'],
            'amount' => ['required_without:payment_id', 'nullable', 'numeric', 'min:1', 'max:999999999999'],
            'payment_date' => ['required_without:payment_id', 'nullable', 'date'],
            'payment_method' => ['required_without:payment_id', 'nullable', 'string', 'max:50'],
            'reference_number' => ['required_without:payment_id', 'nullable', 'string', 'max:100'],
            'verification_notes' => ['nullable', 'string', 'max:2000'],
            'reject_reason' => ['nullable', 'string', 'max:2000'],
        ], [
            'amount.required_without' => 'Nominal pembayaran wajib diisi.',
            'amount.min' => 'Nominal pembayaran minimal Rp 1.',
            'payment_date.required_without' => 'Tanggal pembayaran wajib diisi.',
            'payment_method.required_without' => 'Metode pembayaran wajib diisi.',
            'reference_number.required_without' => 'Nomor referensi mutasi wajib diisi.',
        ]);

        $existingPayment = null;
        if (! empty($validated['payment_id'])) {
            $existingPayment = Payment::query()->findOrFail((int) $validated['payment_id']);
        }

        $payment = $billing->verifyOrRecordPayment(
            keuangan: $keuangan,
            invoice: $invoice,
            validated: $validated,
            payment: $existingPayment,
            request: $request,
        );

        $msg = $payment->verification_status === 'VERIFIED'
            ? "Pembayaran {$payment->payment_number} telah diverifikasi sah dan otomatis tercatat di Buku Kas."
            : "Bukti pembayaran {$payment->payment_number} ditolak dengan alasan tercatat.";

        return redirect()
            ->route('finance.invoices.show', $invoice->invoice_slug)
            ->with('status', $msg);
    }

    public function tariffsIndex(Request $request): View
    {
        return view('finance.tariffs.index', ['tariffs' => OrderPresenter::tariffs()]);
    }

    public function tariffsStore(
        Request $request,
        BillingAndExitWorkflowService $billing,
    ): RedirectResponse {
        /** @var User $keuangan */
        $keuangan = $request->user();

        $validated = $request->validate([
            'tariff_code' => ['nullable', 'string', 'max:40'],
            'name' => ['required', 'string', 'min:3', 'max:150'],
            'service_category' => ['nullable', 'string', 'max:120'],
            'cargo_type' => ['nullable', 'string', 'max:120'],
            'service_type' => ['required', 'string', 'min:2', 'max:120'],
            'unit' => ['required', 'string', 'min:1', 'max:30'],
            'unit_price' => ['required', 'numeric', 'min:1', 'max:999999999'],
            'effective_from' => ['nullable', 'date'],
        ], [
            'name.required' => 'Nama tarif wajib diisi.',
            'service_type.required' => 'Jenis jasa wajib diisi.',
            'unit_price.required' => 'Harga satuan wajib diisi.',
        ]);

        $tariff = $billing->createTariff($keuangan, $validated, $request);

        return redirect()
            ->route('finance.tariffs.index')
            ->with('status', "Referensi tarif {$tariff->tariff_code} ({$tariff->name}) berhasil disimpan.");
    }

    public function tariffsUpdate(
        Request $request,
        ServiceTariff $tariff,
        BillingAndExitWorkflowService $billing,
    ): RedirectResponse {
        /** @var User $keuangan */
        $keuangan = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:150'],
            'service_category' => ['nullable', 'string', 'max:120'],
            'service_type' => ['required', 'string', 'min:2', 'max:120'],
            'unit' => ['required', 'string', 'min:1', 'max:30'],
            'unit_price' => ['required', 'numeric', 'min:1', 'max:999999999'],
        ], [
            'name.required' => 'Nama tarif wajib diisi.',
            'service_type.required' => 'Jenis jasa wajib diisi.',
            'unit.required' => 'Satuan wajib diisi.',
            'unit_price.required' => 'Harga satuan wajib diisi.',
        ]);

        $updated = $billing->updateTariff($keuangan, $tariff, $validated, $request);

        return redirect()
            ->route('finance.tariffs.index')
            ->with('status', "Referensi tarif {$updated->tariff_code} ({$updated->name}) berhasil diperbarui.");
    }

    public function tariffsDestroy(
        Request $request,
        ServiceTariff $tariff,
        BillingAndExitWorkflowService $billing,
    ): RedirectResponse {
        /** @var User $keuangan */
        $keuangan = $request->user();

        $code = $tariff->tariff_code;
        $name = $tariff->name;
        $billing->deleteTariff($keuangan, $tariff, $request);

        return redirect()
            ->route('finance.tariffs.index')
            ->with('status', "Referensi tarif {$code} ({$name}) berhasil dihapus.");
    }

    public function expensesIndex(Request $request): View
    {
        return view('finance.expenses.index', ['expenses' => OrderPresenter::expenses()]);
    }

    public function expensesStore(
        Request $request,
        BillingAndExitWorkflowService $billing,
    ): RedirectResponse {
        /** @var User $keuangan */
        $keuangan = $request->user();

        $validated = $request->validate([
            'expense_category' => ['required', 'string', 'min:2', 'max:120'],
            'expense_date' => ['required', 'date'],
            'description' => ['required', 'string', 'min:3', 'max:2000'],
            'amount' => ['required', 'numeric', 'min:1', 'max:999999999999'],
            'vendor' => ['nullable', 'string', 'max:150'],
            'payment_method' => ['required', 'string', 'min:2', 'max:50'],
            'receipt_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ], [
            'expense_category.required' => 'Kategori biaya operasional wajib diisi.',
            'expense_date.required' => 'Tanggal pengeluaran wajib diisi.',
            'description.required' => 'Uraian pengeluaran wajib diisi.',
            'amount.required' => 'Nominal pengeluaran wajib diisi.',
        ]);

        $expense = $billing->recordOperationalExpense(
            keuangan: $keuangan,
            validated: $validated,
            receiptFile: $request->file('receipt_file'),
            request: $request,
        );

        return redirect()
            ->route('finance.expenses.index')
            ->with('status', "Biaya operasional {$expense->expense_number} ({$expense->expense_category}) berhasil disimpan dan tercatat di Buku Kas Keluar.");
    }

    public function cashbookIndex(Request $request, ReportExportService $exporter): View
    {
        $dataset = $exporter->buildReportDataset([
            'report_type' => 'cashbook',
            'from' => $request->query('from'),
            'to' => $request->query('to'),
            'entry_type' => $request->query('entry_type'),
            'category' => $request->query('category'),
            'q' => $request->query('q'),
        ]);

        return view('finance.cashbook.index', [
            'entries' => OrderPresenter::cashbookEntries(),
            'summary' => $dataset['summary'],
        ]);
    }

    public function reportsIndex(Request $request, ReportExportService $exporter): View
    {
        $dataset = $exporter->buildReportDataset([
            'report_type' => $request->query('report_type', 'cashbook'),
            'from' => $request->query('from'),
            'to' => $request->query('to'),
            'category' => $request->query('category'),
            'entry_type' => $request->query('entry_type'),
            'q' => $request->query('q'),
        ]);

        return view('finance.reports.index', [
            'dataset' => $dataset,
            'cashbook' => OrderPresenter::cashbookEntries(),
            'expenses' => OrderPresenter::expenses(),
        ]);
    }

    public function reportsExport(Request $request, ReportExportService $exporter): Response
    {
        $format = strtolower((string) $request->query('format', 'pdf'));
        $filters = [
            'report_type' => $request->query('report_type', 'cashbook'),
            'from' => $request->query('from'),
            'to' => $request->query('to'),
            'category' => $request->query('category'),
            'entry_type' => $request->query('entry_type'),
            'q' => $request->query('q'),
        ];

        if ($format === 'coretax') {
            return $exporter->exportCoretaxCsv($filters);
        }
        if ($format === 'accounting') {
            return $exporter->exportAccountingCsv($filters);
        }

        $dataset = $exporter->buildReportDataset($filters);

        if ($format === 'xlsx' || $format === 'excel') {
            return $exporter->exportReportExcel($dataset);
        }

        return $exporter->exportReportPdf($dataset);
    }

    public function efakturStore(Request $request, string $code, PrdV21WorkflowService $prd): RedirectResponse
    {
        /** @var User $keuangan */
        $keuangan = $request->user();
        $invoice = Invoice::findByCodeOrSlug($code)
            ?? (ctype_digit($code) ? Invoice::query()->find((int) $code) : null);

        abort_if(! $invoice, 404, 'Invoice tidak ditemukan.');
        Gate::authorize('update', $invoice);

        $validated = $request->validate([
            'efaktur_number' => ['nullable', 'string', 'max:80'],
            'efaktur_file' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ], [
            'efaktur_file.required' => 'Berkas PDF e-Faktur wajib diunggah.',
            'efaktur_file.mimes' => 'Dokumen e-Faktur harus berformat PDF.',
        ]);

        $prd->uploadEfaktur(
            keuangan: $keuangan,
            invoice: $invoice,
            efakturNumber: $validated['efaktur_number'] ?? null,
            file: $request->file('efaktur_file'),
            request: $request,
        );

        return redirect()
            ->route('finance.invoices.show', $invoice->invoice_slug)
            ->with('status', "PDF e-Faktur untuk {$invoice->invoice_number} berhasil diunggah.");
    }

    public function splitBillingStore(Request $request, string $code, PrdV21WorkflowService $prd): RedirectResponse
    {
        /** @var User $keuangan */
        $keuangan = $request->user();
        $order = ServiceOrder::findByCodeOrSlug($code)
            ?? (ctype_digit($code) ? ServiceOrder::query()->find((int) $code) : null);

        abort_if(! $order, 404, 'Pesanan tidak ditemukan.');

        $validated = $request->validate([
            'split_qty' => ['required', 'integer', 'min:1', 'max:100000'],
            'unit_price' => ['nullable', 'numeric', 'min:1', 'max:999999999'],
            'additional_fees' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'ppn_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'pph23_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'split_label' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $invoice = $prd->createSplitInvoice($keuangan, $order, $validated, $request);

        return redirect()
            ->route('finance.invoices.show', $invoice->invoice_slug)
            ->with('status', "Split Billing {$invoice->invoice_number} ({$invoice->billed_qty} {$order->requested_unit}) berhasil diterbitkan.");
    }

    public function contractsIndex(): View
    {
        return view('finance.contracts.index', [
            'contracts' => ClientContract::query()->with(['clientCompany', 'drumProduct'])->orderBy('client_company_id')->orderBy('service_type')->get(),
            'clients' => ClientCompany::query()->orderBy('company_name')->get(),
            'drumProducts' => DrumProduct::query()->active()->get(),
        ]);
    }

    public function contractsStore(Request $request): RedirectResponse
    {
        /** @var User $keuangan */
        $keuangan = $request->user();

        $validated = $request->validate([
            'client_company_id' => ['required', 'integer', 'exists:client_companies,id'],
            'service_type' => ['required', 'string', 'min:2', 'max:120'],
            'drum_product_id' => ['nullable', 'integer', 'exists:drum_products,id'],
            'unit_price' => ['required', 'numeric', 'min:1', 'max:999999999'],
            'reject_disposal_fee' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'ppn_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'pph23_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $contract = ClientContract::query()->create([
            'client_company_id' => (int) $validated['client_company_id'],
            'service_type' => trim($validated['service_type']),
            'drum_product_id' => ! empty($validated['drum_product_id']) ? (int) $validated['drum_product_id'] : null,
            'unit_price' => (float) $validated['unit_price'],
            'reject_disposal_fee' => (float) ($validated['reject_disposal_fee'] ?? 0),
            'ppn_rate' => (float) ($validated['ppn_rate'] ?? 11.0),
            'pph23_rate' => (float) ($validated['pph23_rate'] ?? 2.0),
            'is_active' => true,
            'notes' => isset($validated['notes']) ? trim((string) $validated['notes']) : null,
            'created_by_user_id' => $keuangan->id,
        ]);

        AuditLogger::record(
            action: 'CLIENT_CONTRACT_CREATED',
            auditable: $contract,
            newValues: $contract->only(['client_company_id', 'service_type', 'unit_price', 'reject_disposal_fee', 'ppn_rate', 'pph23_rate']),
            actor: $keuangan,
            request: $request,
        );

        return redirect()
            ->route('finance.contracts.index')
            ->with('status', "Tarif kontrak khusus layanan {$contract->service_type} berhasil disimpan.");
    }

    public function contractsDestroy(Request $request, ClientContract $contract): RedirectResponse
    {
        /** @var User $keuangan */
        $keuangan = $request->user();
        $oldValues = $contract->only(['client_company_id', 'service_type', 'unit_price', 'reject_disposal_fee']);
        $id = $contract->id;
        $contract->delete();

        AuditLogger::record(
            action: 'CLIENT_CONTRACT_DELETED',
            auditable: 'ClientContract',
            auditableId: $id,
            oldValues: $oldValues,
            actor: $keuangan,
            request: $request,
        );

        return redirect()
            ->route('finance.contracts.index')
            ->with('status', 'Tarif kontrak client berhasil dihapus.');
    }

    public function paymentAccountsIndex(Request $request): View
    {
        return view('finance.payment-accounts.index', [
            'accounts' => CompanyPaymentAccount::query()
                ->orderByDesc('is_active')
                ->orderBy('category')
                ->orderBy('payment_method')
                ->orderBy('id')
                ->get(),
            'paymentMethodGroups' => config('pkdi.payment_methods', []),
        ]);
    }

    public function paymentAccountsStore(Request $request): RedirectResponse
    {
        /** @var User $keuangan */
        $keuangan = $request->user();
        $allowedMethods = array_merge(...array_values(config('pkdi.payment_methods', [])));

        $validated = $request->validate([
            'payment_method' => ['required', 'string', 'max:50', Rule::in($allowedMethods)],
            'provider_name' => ['required', 'string', 'min:2', 'max:80'],
            'account_number' => ['required', 'string', 'min:3', 'max:80'],
            'account_holder' => ['required', 'string', 'min:3', 'max:150'],
            'instructions' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'payment_method.required' => 'Jenis pembayaran wajib dipilih.',
            'payment_method.in' => 'Jenis pembayaran tidak valid.',
            'provider_name.required' => 'Nama bank / penyedia wajib diisi.',
            'account_number.required' => 'Nomor rekening / Virtual Account / akun wajib diisi.',
            'account_holder.required' => 'Nama pemilik rekening (atas nama) wajib diisi.',
        ]);

        $account = CompanyPaymentAccount::query()->create([
            'payment_method' => $validated['payment_method'],
            'category' => CompanyPaymentAccount::categoryForMethod($validated['payment_method']),
            'provider_name' => trim($validated['provider_name']),
            'account_number' => trim($validated['account_number']),
            'account_holder' => trim($validated['account_holder']),
            'instructions' => isset($validated['instructions']) && trim((string) $validated['instructions']) !== ''
                ? trim((string) $validated['instructions'])
                : null,
            'is_active' => $request->boolean('is_active', true),
            'created_by_user_id' => $keuangan->id,
            'updated_by_user_id' => $keuangan->id,
        ]);

        AuditLogger::record(
            action: 'PAYMENT_ACCOUNT_CREATED',
            auditable: $account,
            newValues: $account->only(['payment_method', 'category', 'provider_name', 'account_number', 'account_holder', 'is_active']),
            actor: $keuangan,
            request: $request,
        );

        return redirect()
            ->route('finance.payment-accounts.index')
            ->with('status', "Rekening pembayaran {$account->payment_method} ({$account->account_number}) berhasil disimpan.");
    }

    public function paymentAccountsUpdate(Request $request, CompanyPaymentAccount $account): RedirectResponse
    {
        /** @var User $keuangan */
        $keuangan = $request->user();
        $allowedMethods = array_merge(...array_values(config('pkdi.payment_methods', [])));

        $validated = $request->validate([
            'payment_method' => ['required', 'string', 'max:50', Rule::in($allowedMethods)],
            'provider_name' => ['required', 'string', 'min:2', 'max:80'],
            'account_number' => ['required', 'string', 'min:3', 'max:80'],
            'account_holder' => ['required', 'string', 'min:3', 'max:150'],
            'instructions' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'payment_method.required' => 'Jenis pembayaran wajib dipilih.',
            'payment_method.in' => 'Jenis pembayaran tidak valid.',
            'provider_name.required' => 'Nama bank / penyedia wajib diisi.',
            'account_number.required' => 'Nomor rekening / Virtual Account / akun wajib diisi.',
            'account_holder.required' => 'Nama pemilik rekening (atas nama) wajib diisi.',
        ]);

        $oldValues = $account->only(['payment_method', 'category', 'provider_name', 'account_number', 'account_holder', 'instructions', 'is_active']);

        $account->update([
            'payment_method' => $validated['payment_method'],
            'category' => CompanyPaymentAccount::categoryForMethod($validated['payment_method']),
            'provider_name' => trim($validated['provider_name']),
            'account_number' => trim($validated['account_number']),
            'account_holder' => trim($validated['account_holder']),
            'instructions' => isset($validated['instructions']) && trim((string) $validated['instructions']) !== ''
                ? trim((string) $validated['instructions'])
                : null,
            'is_active' => $request->boolean('is_active'),
            'updated_by_user_id' => $keuangan->id,
        ]);

        AuditLogger::record(
            action: 'PAYMENT_ACCOUNT_UPDATED',
            auditable: $account,
            oldValues: $oldValues,
            newValues: $account->only(['payment_method', 'category', 'provider_name', 'account_number', 'account_holder', 'instructions', 'is_active']),
            actor: $keuangan,
            request: $request,
        );

        return redirect()
            ->route('finance.payment-accounts.index')
            ->with('status', "Rekening pembayaran {$account->payment_method} ({$account->account_number}) berhasil diperbarui.");
    }

    public function paymentAccountsDestroy(Request $request, CompanyPaymentAccount $account): RedirectResponse
    {
        /** @var User $keuangan */
        $keuangan = $request->user();

        $oldValues = $account->only(['payment_method', 'category', 'provider_name', 'account_number', 'account_holder', 'is_active']);
        $label = "{$account->payment_method} ({$account->account_number})";
        $accountId = (int) $account->id;

        $account->delete();

        AuditLogger::record(
            action: 'PAYMENT_ACCOUNT_DELETED',
            auditable: 'CompanyPaymentAccount',
            auditableId: $accountId,
            oldValues: $oldValues,
            actor: $keuangan,
            request: $request,
        );

        return redirect()
            ->route('finance.payment-accounts.index')
            ->with('status', "Rekening pembayaran {$label} berhasil dihapus.");
    }
}
