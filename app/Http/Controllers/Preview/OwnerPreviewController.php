<?php

namespace App\Http\Controllers\Preview;

use App\Http\Controllers\Controller;
use App\Models\ClientCompany;
use App\Models\ServiceOrder;
use App\Services\ReportExportService;
use App\Support\OrderPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class OwnerPreviewController extends Controller
{
    public function dashboard(Request $request): View
    {
        $orders = OrderPresenter::allOrders();
        $summary = OrderPresenter::financialSummary();
        $operational = OrderPresenter::operationalSummary();

        return view('owner.dashboard', [
            'orders' => $orders,
            'auditLogs' => OrderPresenter::auditLogs(),
            'expenses' => OrderPresenter::expenses(),
            'summary' => $summary,
            'operational' => $operational,
        ]);
    }

    public function financial(Request $request): View
    {
        $from = $request->query('from') ? trim((string) $request->query('from')) : null;
        $to = $request->query('to') ? trim((string) $request->query('to')) : null;

        $summary = OrderPresenter::financialSummary($from, $to);

        return view('owner.financial', [
            'invoices' => OrderPresenter::allInvoices(),
            'invoicedOrders' => array_values(array_filter(OrderPresenter::allOrders(), static fn (array $o): bool => ! empty($o['invoice']))),
            'expenses' => OrderPresenter::expenses(),
            'cashbook' => OrderPresenter::cashbookEntries(),
            'summary' => $summary,
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function transactionsIndex(Request $request): View
    {
        $clientId = trim((string) $request->query('client_company_id', ''));
        $transactionType = strtoupper(trim((string) $request->query('transaction_type', '')));
        $search = trim((string) $request->query('q', ''));

        $orders = array_values(array_filter(
            OrderPresenter::allOrders(),
            static function (array $o) use ($clientId, $transactionType, $search): bool {
                if ($transactionType !== '') {
                    $normalizedFilter = $transactionType === 'DRUM_SALE' ? 'WASTE_TRANSPORT' : $transactionType;
                    $normalizedOrder = $o['transaction_type'] === 'DRUM_SALE' ? 'WASTE_TRANSPORT' : $o['transaction_type'];
                    if ($normalizedOrder !== $normalizedFilter) {
                        return false;
                    }
                }
                if ($search !== '') {
                    $needle = mb_strtolower($search);
                    $haystack = mb_strtolower($o['order_number'].' '.$o['client_company'].' '.$o['product_name'].' '.$o['service_type']);
                    if (! str_contains($haystack, $needle)) {
                        return false;
                    }
                }
                if ($clientId !== '') {
                    $matched = ClientCompany::query()->find((int) $clientId);
                    if ($matched && $o['client_company'] !== $matched->company_name) {
                        return false;
                    }
                }

                return true;
            }
        ));

        return view('owner.transactions.index', [
            'orders' => $orders,
            'clients' => ClientCompany::query()->orderBy('company_name')->get(),
            'filters' => [
                'client_company_id' => $clientId,
                'transaction_type' => $transactionType,
                'q' => $search,
            ],
        ]);
    }

    public function transactionsShow(Request $request, string $code): View
    {
        $dbOrder = ServiceOrder::findByCodeOrSlug($code)
            ?? (ctype_digit($code) ? ServiceOrder::query()->find((int) $code) : null);

        abort_if(! $dbOrder, 404, 'Transaksi tidak ditemukan.');
        Gate::authorize('view', $dbOrder);

        return view('owner.transactions.show', ['order' => OrderPresenter::toArray($dbOrder)]);
    }

    public function auditLogs(Request $request): View
    {
        return view('owner.audit-logs', [
            'logs' => OrderPresenter::auditLogs(),
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
