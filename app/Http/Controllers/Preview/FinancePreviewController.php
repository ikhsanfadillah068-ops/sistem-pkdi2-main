<?php

namespace App\Http\Controllers\Preview;

use App\Http\Controllers\Controller;
use App\Models\ClientCompany;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\ServiceOrder;
use App\Models\ServiceTariff;
use App\Models\User;
use App\Services\BillingAndExitWorkflowService;
use App\Services\ReportExportService;
use App\Support\OrderPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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
            'tax_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
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

    public function billableShow(Request $request, string $code): View
    {
        $dbOrder = ServiceOrder::findByCodeOrSlug($code)
            ?? (ctype_digit($code) ? ServiceOrder::query()->find((int) $code) : null);

        abort_if(! $dbOrder, 404, 'Pesanan tidak ditemukan.');
        Gate::authorize('manageBilling', $dbOrder);

        return view('finance.billable.show', [
            'order' => OrderPresenter::toArray($dbOrder),
            'tariffs' => OrderPresenter::tariffs(),
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
            'tax_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
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
        $dataset = $exporter->buildReportDataset([
            'report_type' => $request->query('report_type', 'cashbook'),
            'from' => $request->query('from'),
            'to' => $request->query('to'),
            'category' => $request->query('category'),
            'entry_type' => $request->query('entry_type'),
            'q' => $request->query('q'),
        ]);

        if ($format === 'xlsx' || $format === 'excel') {
            return $exporter->exportReportExcel($dataset);
        }

        return $exporter->exportReportPdf($dataset);
    }
}
