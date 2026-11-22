<?php

namespace App\Services;

use App\Models\CashbookEntry;
use App\Models\Invoice;
use App\Models\OperationalExpense;
use App\Models\ServiceOrder;
use App\Support\PkdiDemoData;
use Symfony\Component\HttpFoundation\Response;
use ZipArchive;

class ReportExportService
{
    /**
     * Menyusun data laporan berdasarkan filter yang dipilih:
     * - cashbook: menggunakan `transaction_date` pada `cashbook_entries`
     * - invoices: menggunakan `issue_date` pada `invoices`
     * - expenses: menggunakan `expense_date` pada `operational_expenses`
     *
     * @param  array<string, mixed>  $filters
     * @return array{
     *   report_type: string,
     *   title: string,
     *   date_basis_label: string,
     *   from: string|null,
     *   to: string|null,
     *   category: string|null,
     *   entry_type: string|null,
     *   columns: list<string>,
     *   rows: list<array<string, mixed>>,
     *   summary: array<string, float>
     * }
     */
    public function buildReportDataset(array $filters): array
    {
        $reportType = (string) ($filters['report_type'] ?? 'cashbook');
        $from = isset($filters['from']) && trim((string) $filters['from']) !== '' ? trim((string) $filters['from']) : null;
        $to = isset($filters['to']) && trim((string) $filters['to']) !== '' ? trim((string) $filters['to']) : null;
        $category = isset($filters['category']) && trim((string) $filters['category']) !== '' ? trim((string) $filters['category']) : null;
        $entryType = isset($filters['entry_type']) && trim((string) $filters['entry_type']) !== '' ? strtoupper(trim((string) $filters['entry_type'])) : null;
        $search = isset($filters['q']) && trim((string) $filters['q']) !== '' ? trim((string) $filters['q']) : null;

        if ($reportType === 'invoices') {
            $query = Invoice::query()
                ->with(['clientCompany', 'serviceOrder'])
                ->where('status', '!=', 'DRAFT')
                ->orderByDesc('issue_date')
                ->orderByDesc('id');

            if ($from) {
                $query->whereDate('issue_date', '>=', $from);
            }
            if ($to) {
                $query->whereDate('issue_date', '<=', $to);
            }
            if ($search) {
                $query->where(function ($q) use ($search): void {
                    $q->where('invoice_number', 'like', "%{$search}%")
                        ->orWhereHas('clientCompany', fn ($cq) => $cq->where('company_name', 'like', "%{$search}%"));
                });
            }

            $invoices = $query->get();
            $rows = [];
            $totalAmount = 0.0;
            $totalPaid = 0.0;
            $totalBalance = 0.0;

            foreach ($invoices as $inv) {
                $totalAmount += (float) $inv->total_amount;
                $totalPaid += (float) $inv->paid_amount;
                $totalBalance += (float) $inv->balance_due;

                $rows[] = [
                    'reference' => $inv->invoice_number,
                    'date' => $inv->issue_date?->format('d M Y') ?? '-',
                    'type' => $inv->status === 'PAID' ? 'Lunas' : ($inv->status === 'PARTIALLY_PAID' ? 'Sebagian' : 'Belum Dibayar'),
                    'description' => ($inv->clientCompany?->company_name ?? '-').' (Pesanan '.($inv->serviceOrder?->order_number ?? '-').')',
                    'amount' => (float) $inv->total_amount,
                    'paid' => (float) $inv->paid_amount,
                    'balance' => (float) $inv->balance_due,
                ];
            }

            return [
                'report_type' => 'invoices',
                'title' => 'Laporan Rekap Tagihan & Piutang Client PKDI',
                'date_basis_label' => 'Berdasarkan Tanggal Terbit Invoice',
                'from' => $from,
                'to' => $to,
                'category' => $category,
                'entry_type' => $entryType,
                'columns' => ['No. Invoice', 'Tgl Terbit', 'Status Bayar', 'Client & Pesanan', 'Nominal Tagihan (Rp)'],
                'rows' => $rows,
                'summary' => [
                    'total_amount' => $totalAmount,
                    'total_paid' => $totalPaid,
                    'total_balance' => $totalBalance,
                ],
            ];
        }

        if ($reportType === 'expenses') {
            $query = OperationalExpense::query()
                ->where('status', 'RECORDED')
                ->orderByDesc('expense_date')
                ->orderByDesc('id');

            if ($from) {
                $query->whereDate('expense_date', '>=', $from);
            }
            if ($to) {
                $query->whereDate('expense_date', '<=', $to);
            }
            if ($category) {
                $query->where('expense_category', 'like', "%{$category}%");
            }
            if ($search) {
                $query->where(function ($q) use ($search): void {
                    $q->where('expense_number', 'like', "%{$search}%")
                        ->orWhere('expense_category', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('recipient_or_vendor', 'like', "%{$search}%");
                });
            }

            $expenses = $query->get();
            $rows = [];
            $totalExpense = 0.0;

            foreach ($expenses as $exp) {
                $totalExpense += (float) $exp->amount;
                $rows[] = [
                    'reference' => $exp->expense_number,
                    'date' => $exp->expense_date?->format('d M Y') ?? '-',
                    'type' => $exp->expense_category,
                    'description' => $exp->description.($exp->recipient_or_vendor ? " ({$exp->recipient_or_vendor})" : ''),
                    'amount' => (float) $exp->amount,
                ];
            }

            return [
                'report_type' => 'expenses',
                'title' => 'Laporan Rekap Biaya Operasional PKDI',
                'date_basis_label' => 'Berdasarkan Tanggal Pengeluaran Operasional',
                'from' => $from,
                'to' => $to,
                'category' => $category,
                'entry_type' => $entryType,
                'columns' => ['No. Pengeluaran', 'Tgl Pengeluaran', 'Kategori Biaya', 'Uraian & Vendor', 'Nominal (Rp)'],
                'rows' => $rows,
                'summary' => [
                    'total_amount' => $totalExpense,
                    'total_in' => 0.0,
                    'total_out' => $totalExpense,
                    'net_cash' => -$totalExpense,
                ],
            ];
        }

        // Default: cashbook
        $query = CashbookEntry::query()
            ->with(['payment', 'operationalExpense'])
            ->where('status', 'ACTIVE')
            ->orderByDesc('transaction_date')
            ->orderByDesc('id');

        if ($from) {
            $query->whereDate('transaction_date', '>=', $from);
        }
        if ($to) {
            $query->whereDate('transaction_date', '<=', $to);
        }
        if ($entryType && in_array($entryType, ['IN', 'OUT'], true)) {
            $query->where('entry_type', $entryType);
        }
        if ($category) {
            $query->where(function ($q) use ($category): void {
                $q->where('description', 'like', "%{$category}%")
                    ->orWhereHas('operationalExpense', fn ($eq) => $eq->where('expense_category', 'like', "%{$category}%"));
            });
        }
        if ($search) {
            $query->where(function ($q) use ($search): void {
                $q->where('entry_number', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $entries = $query->get();
        $rows = [];
        $totalIn = 0.0;
        $totalOut = 0.0;

        foreach ($entries as $entry) {
            $amt = (float) $entry->amount;
            if ($entry->entry_type === 'IN') {
                $totalIn += $amt;
            } else {
                $totalOut += $amt;
            }

            $rows[] = [
                'reference' => $entry->entry_number,
                'date' => $entry->transaction_date?->format('d M Y') ?? '-',
                'type' => $entry->entry_type === 'IN' ? 'Pemasukan Jasa (IN)' : 'Biaya Operasional (OUT)',
                'entry_type' => $entry->entry_type,
                'description' => $entry->description,
                'amount' => $amt,
            ];
        }

        return [
            'report_type' => 'cashbook',
            'title' => 'Laporan Buku Kas Masuk & Keluar PKDI',
            'date_basis_label' => 'Berdasarkan Tanggal Transaksi Kas',
            'from' => $from,
            'to' => $to,
            'category' => $category,
            'entry_type' => $entryType,
            'columns' => ['No. Buku Kas', 'Tgl Transaksi', 'Jenis Arus', 'Sumber & Keterangan', 'Nominal (Rp)'],
            'rows' => $rows,
            'summary' => [
                'total_in' => $totalIn,
                'total_out' => $totalOut,
                'net_cash' => $totalIn - $totalOut,
                'total_amount' => $totalIn + $totalOut,
            ],
        ];
    }

    /**
     * Menghasilkan dokumen PDF resmi (%PDF-1.4) dengan tata letak tabel korporat untuk laporan yang difilter.
     *
     * @param  array<string, mixed>  $dataset
     */
    public function exportReportPdf(array $dataset): Response
    {
        $pages = [];
        $ops = [];

        $fromLabel = $dataset['from'] ? date('d M Y', strtotime((string) $dataset['from'])) : 'Awal';
        $toLabel = $dataset['to'] ? date('d M Y', strtotime((string) $dataset['to'])) : 'Sekarang';
        $periodText = ($dataset['from'] || $dataset['to']) ? "{$fromLabel} s/d {$toLabel}" : 'Semua Periode';
        $printedAt = now('Asia/Jakarta')->format('d M Y, H:i').' WIB';
        $rows = $dataset['rows'] ?? [];
        $columns = $dataset['columns'] ?? ['No. Referensi', 'Tanggal', 'Jenis', 'Keterangan', 'Nominal (Rp)'];

        // Corporate Header Banner
        $ops[] = $this->pdfRect(36, 748, 523, 56, '0.533 0.075 0.216');
        $ops[] = $this->pdfText(50, 782, 'PT PULO KENCANA DRUM INDUSTRI (PKDI)', 'F2', 13, '1 1 1');
        $ops[] = $this->pdfText(50, 764, 'Sistem Informasi Operasional & Keuangan Layanan Drum Industri', 'F1', 8.5, '0.96 0.86 0.89');
        $ops[] = $this->pdfTextRight(545, 783, 'LAPORAN KEUANGAN RESMI', 'F2', 8.5, '1 1 1');
        $ops[] = $this->pdfTextRight(545, 764, 'Dicetak: '.$printedAt, 'F1', 8, '0.96 0.86 0.89');

        // Report Metadata Card
        $ops[] = $this->pdfRect(36, 686, 523, 52, '0.976 0.976 0.980', '0.86 0.86 0.89', 0.7);
        $ops[] = $this->pdfText(48, 717, (string) $dataset['title'], 'F2', 11, '0.09 0.09 0.11');
        $ops[] = $this->pdfText(48, 699, 'Acuan Data: '.(string) $dataset['date_basis_label'], 'F1', 8.5, '0.38 0.38 0.43');
        $ops[] = $this->pdfTextRight(547, 717, 'Periode: '.$periodText, 'F2', 8.5, '0.15 0.15 0.18');
        $ops[] = $this->pdfTextRight(547, 699, 'Total Data: '.count($rows).' transaksi', 'F1', 8.5, '0.38 0.38 0.43');

        // Summary KPI Strip (3 Cards)
        $kpiCards = $this->buildReportKpiCards($dataset);
        $cardPositions = [36.0, 213.0, 390.0];
        foreach ($kpiCards as $idx => $card) {
            $cx = $cardPositions[$idx] ?? 36.0;
            $ops[] = $this->pdfRect($cx, 628, 169, 48, '1 1 1', '0.86 0.86 0.89', 0.6);
            $ops[] = $this->pdfRect($cx, 673, 169, 3, $card['accent']);
            $ops[] = $this->pdfText($cx + 10, 657, $card['label'], 'F2', 7.5, '0.42 0.42 0.47');
            $ops[] = $this->pdfText($cx + 10, 639, $card['value'], 'F2', 10.5, $card['color']);
        }

        // Table Layout Coordinates
        $colX = [36.0, 62.0, 156.0, 220.0, 318.0, 469.0, 559.0];
        $y = 614.0;

        $drawTableHeader = function (float $topY) use (&$ops, $colX, $columns): float {
            $rowBottom = $topY - 24.0;
            $ops[] = $this->pdfRect(36, $rowBottom, 523, 24, '0.15 0.15 0.18');
            $ops[] = $this->pdfText(43, $rowBottom + 8, 'No', 'F2', 8, '1 1 1');
            $ops[] = $this->pdfText($colX[1] + 6, $rowBottom + 8, (string) ($columns[0] ?? 'No. Referensi'), 'F2', 8, '1 1 1');
            $ops[] = $this->pdfText($colX[2] + 6, $rowBottom + 8, (string) ($columns[1] ?? 'Tanggal'), 'F2', 8, '1 1 1');
            $ops[] = $this->pdfText($colX[3] + 6, $rowBottom + 8, (string) ($columns[2] ?? 'Jenis / Kategori'), 'F2', 8, '1 1 1');
            $ops[] = $this->pdfText($colX[4] + 6, $rowBottom + 8, (string) ($columns[3] ?? 'Uraian & Keterangan'), 'F2', 8, '1 1 1');
            $ops[] = $this->pdfTextRight($colX[6] - 8, $rowBottom + 8, (string) ($columns[4] ?? 'Nominal (Rp)'), 'F2', 8, '1 1 1');

            return $rowBottom;
        };

        $y = $drawTableHeader($y);

        if (count($rows) === 0) {
            $rowBottom = $y - 32.0;
            $ops[] = $this->pdfRect(36, $rowBottom, 523, 32, '1 1 1', '0.86 0.86 0.89', 0.5);
            $ops[] = $this->pdfText(210, $rowBottom + 12, 'Tidak ada transaksi pada periode filter yang dipilih.', 'F1', 8.5, '0.45 0.45 0.50');
            $y = $rowBottom;
        } else {
            foreach ($rows as $idx => $row) {
                $descLines = explode("\n", wordwrap((string) ($row['description'] ?? '-'), 34, "\n", true));
                $descLines = array_slice($descLines, 0, 2);
                $rowHeight = count($descLines) > 1 ? 32.0 : 22.0;

                if ($y - $rowHeight < 115.0) {
                    $ops[] = $this->pdfPageFooter(count($pages) + 1);
                    $pages[] = implode("\n", $ops);
                    $ops = [];
                    $ops[] = $this->pdfText(36, 804, 'PT PULO KENCANA DRUM INDUSTRI (PKDI) - Lanjutan '.$dataset['title'], 'F2', 9, '0.35 0.35 0.40');
                    $y = $drawTableHeader(790.0);
                }

                $rowBottom = $y - $rowHeight;
                $bg = ($idx % 2 === 1) ? '0.984 0.984 0.988' : '1 1 1';
                $ops[] = $this->pdfRect(36, $rowBottom, 523, $rowHeight, $bg, '0.88 0.88 0.90', 0.5);

                foreach ([1, 2, 3, 4, 5] as $cIdx) {
                    $ops[] = $this->pdfLine($colX[$cIdx], $rowBottom, $colX[$cIdx], $y, '0.88 0.88 0.90', 0.4);
                }

                $textY = $y - 14.0;
                $amtFormatted = 'Rp '.number_format((float) ($row['amount'] ?? 0), 0, ',', '.');
                $ops[] = $this->pdfText($colX[0] + 7, $textY, (string) ($idx + 1), 'F1', 8, '0.35 0.35 0.40');
                $ops[] = $this->pdfText($colX[1] + 6, $textY, mb_strimwidth((string) ($row['reference'] ?? '-'), 0, 19, ''), 'F2', 8, '0.09 0.09 0.11');
                $ops[] = $this->pdfText($colX[2] + 6, $textY, (string) ($row['date'] ?? '-'), 'F1', 8, '0.20 0.20 0.24');
                $ops[] = $this->pdfText($colX[3] + 6, $textY, mb_strimwidth((string) ($row['type'] ?? '-'), 0, 20, ''), 'F1', 8, '0.20 0.20 0.24');
                $ops[] = $this->pdfText($colX[4] + 6, $textY, $descLines[0] ?? '-', 'F1', 8, '0.15 0.15 0.18');
                if (isset($descLines[1])) {
                    $ops[] = $this->pdfText($colX[4] + 6, $textY - 11.0, $descLines[1], 'F1', 7.5, '0.35 0.35 0.40');
                }
                $ops[] = $this->pdfTextRight($colX[6] - 8, $textY, $amtFormatted, 'F2', 8.5, '0.09 0.09 0.11');

                $y = $rowBottom;
            }
        }

        // Table Footer Total Bar
        $totalRowBottom = $y - 24.0;
        $totalVal = 'Rp '.number_format((float) ($dataset['summary']['total_amount'] ?? 0), 0, ',', '.');
        $ops[] = $this->pdfRect(36, $totalRowBottom, 523, 24, '0.965 0.935 0.945', '0.533 0.075 0.216', 0.7);
        $ops[] = $this->pdfLine($colX[5], $totalRowBottom, $colX[5], $y, '0.533 0.075 0.216', 0.6);
        $ops[] = $this->pdfTextRight($colX[5] - 10, $totalRowBottom + 8, 'TOTAL NILAI REKAPITULASI', 'F2', 8.5, '0.533 0.075 0.216');
        $ops[] = $this->pdfTextRight($colX[6] - 8, $totalRowBottom + 8, $totalVal, 'F2', 9.5, '0.533 0.075 0.216');

        $ops[] = $this->pdfPageFooter(count($pages) + 1);
        $pages[] = implode("\n", $ops);

        $pdfBinary = $this->compilePdfPages($pages);
        $filename = 'laporan-'.$dataset['report_type'].'-pkdi.pdf';

        return response($pdfBinary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * Menghasilkan dokumen PDF resmi (%PDF-1.4) dengan tata letak faktur korporat untuk satu Invoice.
     */
    public function exportInvoicePdf(Invoice $invoice): Response
    {
        $invoice->loadMissing(['clientCompany', 'serviceOrder', 'items.billableTransaction.serviceOrder', 'billableTransactions.serviceOrder', 'payments']);

        $ops = [];
        $statusLabel = match ($invoice->status) {
            'PAID' => 'LUNAS',
            'PARTIALLY_PAID' => 'DIBAYAR SEBAGIAN',
            'VOIDED' => 'DIBATALKAN (VOID)',
            'DRAFT' => 'DRAFT',
            default => 'MENUNGGU PEMBAYARAN',
        };

        $orderNumbers = $invoice->billableTransactions
            ->map(static fn ($tx): ?string => $tx->serviceOrder?->order_number)
            ->filter()
            ->unique()
            ->values()
            ->all();
        if ($orderNumbers === [] && $invoice->serviceOrder) {
            $orderNumbers = [$invoice->serviceOrder->order_number];
        }
        $refLabel = count($orderNumbers) > 1
            ? 'Gabungan ('.count($orderNumbers).' pesanan): '.mb_strimwidth(implode(', ', $orderNumbers), 0, 32, '...')
            : 'Ref. Pesanan: '.($orderNumbers[0] ?? '-');
        $npwpText = $invoice->clientCompany?->npwp ? ' | NPWP: '.$invoice->clientCompany->npwp : '';

        // Top Corporate Header Banner
        $ops[] = $this->pdfRect(36, 744, 523, 60, '0.533 0.075 0.216');
        $ops[] = $this->pdfText(50, 780, 'PT PULO KENCANA DRUM INDUSTRI (PKDI)', 'F2', 13, '1 1 1');
        $ops[] = $this->pdfText(50, 761, 'FAKTUR TAGIHAN RESMI (COMMERCIAL INVOICE)', 'F1', 9, '0.96 0.86 0.89');
        $ops[] = $this->pdfTextRight(545, 780, $invoice->invoice_number, 'F2', 11, '1 1 1');
        $ops[] = $this->pdfTextRight(545, 761, 'Status: '.$statusLabel, 'F2', 8.5, '0.98 0.90 0.92');

        // Client & Invoice Info Two-Column Box
        $ops[] = $this->pdfRect(36, 650, 255, 82, '0.976 0.976 0.980', '0.86 0.86 0.89', 0.6);
        $ops[] = $this->pdfText(48, 716, 'DITAGIHKAN KEPADA (CLIENT)', 'F2', 7.5, '0.42 0.42 0.47');
        $ops[] = $this->pdfText(48, 700, (string) ($invoice->clientCompany?->company_name ?? '-'), 'F2', 10, '0.09 0.09 0.11');
        $ops[] = $this->pdfText(48, 684, mb_strimwidth('PIC: '.($invoice->clientCompany?->pic_name ?? '-').$npwpText, 0, 44, '...'), 'F1', 8, '0.30 0.30 0.35');
        $ops[] = $this->pdfText(48, 666, mb_strimwidth($refLabel, 0, 46, '...'), 'F2', 7.8, '0.20 0.20 0.25');

        $ops[] = $this->pdfRect(304, 650, 255, 82, '0.976 0.976 0.980', '0.86 0.86 0.89', 0.6);
        $ops[] = $this->pdfText(316, 716, 'INFORMASI DOKUMEN TAGIHAN', 'F2', 7.5, '0.42 0.42 0.47');
        $ops[] = $this->pdfText(316, 700, 'Nomor Invoice', 'F1', 8.5, '0.35 0.35 0.40');
        $ops[] = $this->pdfTextRight(547, 700, $invoice->invoice_number, 'F2', 8.5, '0.09 0.09 0.11');
        $ops[] = $this->pdfText(316, 684, 'Tanggal Terbit', 'F1', 8.5, '0.35 0.35 0.40');
        $ops[] = $this->pdfTextRight(547, 684, $invoice->issue_date?->format('d M Y') ?? '-', 'F2', 8.5, '0.09 0.09 0.11');
        $ops[] = $this->pdfText(316, 666, 'Jatuh Tempo', 'F1', 8.5, '0.35 0.35 0.40');
        $ops[] = $this->pdfTextRight(547, 666, $invoice->due_date?->format('d M Y') ?? '-', 'F2', 8.5, '0.533 0.075 0.216');

        // Invoice Items Table Header
        $colX = [36.0, 64.0, 294.0, 344.0, 404.0, 479.0, 559.0];
        $y = 634.0;
        $headerBottom = $y - 24.0;
        $ops[] = $this->pdfRect(36, $headerBottom, 523, 24, '0.15 0.15 0.18');
        $ops[] = $this->pdfText(44, $headerBottom + 8, 'No', 'F2', 8, '1 1 1');
        $ops[] = $this->pdfText($colX[1] + 8, $headerBottom + 8, 'Deskripsi Layanan & Pekerjaan', 'F2', 8, '1 1 1');
        $ops[] = $this->pdfTextRight($colX[3] - 8, $headerBottom + 8, 'Qty', 'F2', 8, '1 1 1');
        $ops[] = $this->pdfText($colX[3] + 8, $headerBottom + 8, 'Satuan', 'F2', 8, '1 1 1');
        $ops[] = $this->pdfTextRight($colX[5] - 8, $headerBottom + 8, 'Harga Satuan', 'F2', 8, '1 1 1');
        $ops[] = $this->pdfTextRight($colX[6] - 8, $headerBottom + 8, 'Subtotal (Rp)', 'F2', 8, '1 1 1');
        $y = $headerBottom;

        foreach ($invoice->items as $idx => $item) {
            $descLines = explode("\n", wordwrap((string) $item->description, 42, "\n", true));
            $descLines = array_slice($descLines, 0, 2);
            $rowH = count($descLines) > 1 ? 32.0 : 24.0;
            $rowBottom = $y - $rowH;
            $bg = ($idx % 2 === 1) ? '0.984 0.984 0.988' : '1 1 1';

            $ops[] = $this->pdfRect(36, $rowBottom, 523, $rowH, $bg, '0.88 0.88 0.90', 0.5);
            foreach ([1, 2, 3, 4, 5] as $cIdx) {
                $ops[] = $this->pdfLine($colX[$cIdx], $rowBottom, $colX[$cIdx], $y, '0.88 0.88 0.90', 0.4);
            }

            $ty = $y - 15.0;
            $ops[] = $this->pdfText($colX[0] + 8, $ty, (string) ($idx + 1), 'F1', 8.5, '0.35 0.35 0.40');
            $ops[] = $this->pdfText($colX[1] + 8, $ty, $descLines[0] ?? '-', 'F2', 8.5, '0.09 0.09 0.11');
            if (isset($descLines[1])) {
                $ops[] = $this->pdfText($colX[1] + 8, $ty - 11.0, $descLines[1], 'F1', 8, '0.35 0.35 0.40');
            }
            $ops[] = $this->pdfTextRight($colX[3] - 8, $ty, number_format((int) $item->quantity, 0, ',', '.'), 'F2', 8.5, '0.09 0.09 0.11');
            $ops[] = $this->pdfText($colX[3] + 8, $ty, (string) $item->unit, 'F1', 8.5, '0.25 0.25 0.30');
            $ops[] = $this->pdfTextRight($colX[5] - 8, $ty, 'Rp '.number_format((float) $item->unit_price, 0, ',', '.'), 'F1', 8.5, '0.15 0.15 0.20');
            $ops[] = $this->pdfTextRight($colX[6] - 8, $ty, 'Rp '.number_format((float) $item->line_total, 0, ',', '.'), 'F2', 8.5, '0.09 0.09 0.11');

            $y = $rowBottom;
        }

        // Totals Summary Box on Right + Payment Note Box on Left
        $summaryTop = $y - 14.0;
        $ops[] = $this->pdfRect(36, $summaryTop - 118, 255, 118, '0.976 0.976 0.980', '0.86 0.86 0.89', 0.5);
        $ops[] = $this->pdfText(48, $summaryTop - 18, 'INSTRUKSI TRANSFER & PEMBAYARAN', 'F2', 8, '0.35 0.35 0.40');
        $ops[] = $this->pdfText(48, $summaryTop - 34, '1. Transfer sesuai nominal Total Tagihan ke rekening PKDI.', 'F1', 7.8, '0.25 0.25 0.30');
        $ops[] = $this->pdfText(48, $summaryTop - 48, '2. Unggah bukti transfer melalui Portal Client PKDI.', 'F1', 7.8, '0.25 0.25 0.30');
        $ops[] = $this->pdfText(48, $summaryTop - 62, '3. Status Lunas sah setelah diverifikasi divisi Keuangan.', 'F1', 7.8, '0.25 0.25 0.30');
        if ($invoice->notes) {
            $ops[] = $this->pdfText(48, $summaryTop - 82, 'Catatan: '.mb_strimwidth((string) $invoice->notes, 0, 46, '...'), 'F2', 7.8, '0.20 0.20 0.25');
        }

        $summaryRows = [
            ['label' => 'Subtotal Pekerjaan', 'val' => 'Rp '.number_format((float) $invoice->subtotal_amount, 0, ',', '.'), 'bold' => false],
            ['label' => 'Penyesuaian Biaya', 'val' => 'Rp '.number_format((float) $invoice->adjustment_amount, 0, ',', '.'), 'bold' => false],
            ['label' => 'Pajak / PPN (11%)', 'val' => 'Rp '.number_format((float) $invoice->tax_amount, 0, ',', '.'), 'bold' => false],
            ['label' => 'TOTAL TAGIHAN', 'val' => 'Rp '.number_format((float) $invoice->total_amount, 0, ',', '.'), 'bold' => true, 'highlight' => true],
            ['label' => 'Sudah Dibayar', 'val' => 'Rp '.number_format((float) $invoice->paid_amount, 0, ',', '.'), 'bold' => false],
            ['label' => 'SISA TAGIHAN', 'val' => 'Rp '.number_format((float) $invoice->balance_due, 0, ',', '.'), 'bold' => true],
        ];

        $sy = $summaryTop;
        foreach ($summaryRows as $sRow) {
            $sh = ! empty($sRow['highlight']) ? 22.0 : 19.0;
            $sBottom = $sy - $sh;
            $sBg = ! empty($sRow['highlight']) ? '0.965 0.935 0.945' : '1 1 1';
            $ops[] = $this->pdfRect(304, $sBottom, 255, $sh, $sBg, '0.86 0.86 0.89', 0.5);
            $font = $sRow['bold'] ? 'F2' : 'F1';
            $color = ! empty($sRow['highlight']) ? '0.533 0.075 0.216' : '0.15 0.15 0.18';
            $ops[] = $this->pdfText(314, $sBottom + 6, $sRow['label'], $font, 8.5, $color);
            $ops[] = $this->pdfTextRight(549, $sBottom + 6, $sRow['val'], $font, 8.5, $color);
            $sy = $sBottom;
        }

        $ops[] = $this->pdfPageFooter(1);
        $pdfBinary = $this->compilePdfPages([implode("\n", $ops)]);
        $filename = $invoice->invoice_slug.'.pdf';

        return response($pdfBinary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * Menghasilkan dokumen Excel (.xlsx OpenXML dengan styling tabel, lebar kolom otomatis, dan format Rupiah).
     *
     * @param  array<string, mixed>  $dataset
     */
    public function exportReportExcel(array $dataset): Response
    {
        $fromLabel = $dataset['from'] ? date('d M Y', strtotime((string) $dataset['from'])) : 'Awal';
        $toLabel = $dataset['to'] ? date('d M Y', strtotime((string) $dataset['to'])) : 'Sekarang';
        $periodText = ($dataset['from'] || $dataset['to']) ? "{$fromLabel} s/d {$toLabel}" : 'Semua Periode';
        $printedAt = now('Asia/Jakarta')->format('d M Y, H:i').' WIB';
        $columns = $dataset['columns'] ?? ['No. Referensi', 'Tanggal', 'Jenis', 'Keterangan', 'Nominal (Rp)'];

        $styledRows = [
            // Row 1: Corporate Banner (merged A1:F1)
            [
                ['v' => 'PT PULO KENCANA DRUM INDUSTRI (PKDI)', 's' => 1],
                ['v' => '', 's' => 1],
                ['v' => '', 's' => 1],
                ['v' => '', 's' => 1],
                ['v' => '', 's' => 1],
                ['v' => '', 's' => 1],
            ],
            // Row 2: Report Title (merged A2:F2)
            [
                ['v' => (string) $dataset['title'], 's' => 2],
                ['v' => '', 's' => 2],
                ['v' => '', 's' => 2],
                ['v' => '', 's' => 2],
                ['v' => '', 's' => 2],
                ['v' => '', 's' => 2],
            ],
            // Row 3: Metadata Row 1
            [
                ['v' => 'Acuan Data', 's' => 3],
                ['v' => (string) $dataset['date_basis_label'], 's' => 4],
                ['v' => '', 's' => 4],
                ['v' => 'Periode Filter', 's' => 3],
                ['v' => $periodText, 's' => 4],
                ['v' => '', 's' => 4],
            ],
            // Row 4: Metadata Row 2
            [
                ['v' => 'Waktu Cetak', 's' => 3],
                ['v' => $printedAt, 's' => 4],
                ['v' => '', 's' => 4],
                ['v' => 'Jumlah Transaksi', 's' => 3],
                ['v' => count($dataset['rows']).' transaksi', 's' => 4],
                ['v' => '', 's' => 4],
            ],
            // Row 5: Blank separator
            [],
            // Row 6: Table Headers
            [
                ['v' => 'No.', 's' => 5],
                ['v' => (string) ($columns[0] ?? 'No. Referensi'), 's' => 5],
                ['v' => (string) ($columns[1] ?? 'Tanggal'), 's' => 5],
                ['v' => (string) ($columns[2] ?? 'Jenis / Status'), 's' => 5],
                ['v' => (string) ($columns[3] ?? 'Uraian & Keterangan'), 's' => 5],
                ['v' => (string) ($columns[4] ?? 'Nominal (Rp)'), 's' => 6],
            ],
        ];

        foreach ($dataset['rows'] as $idx => $row) {
            $styledRows[] = [
                ['v' => $idx + 1, 's' => 8],
                ['v' => (string) $row['reference'], 's' => 7],
                ['v' => (string) $row['date'], 's' => 8],
                ['v' => (string) $row['type'], 's' => 7],
                ['v' => (string) $row['description'], 's' => 7],
                ['v' => (float) $row['amount'], 's' => 9],
            ];
        }

        $styledRows[] = [];

        if (isset($dataset['summary']['total_in'], $dataset['summary']['total_out'])) {
            $styledRows[] = [
                ['v' => '', 's' => 0], ['v' => '', 's' => 0], ['v' => '', 's' => 0], ['v' => '', 's' => 0],
                ['v' => 'Total Kas Masuk (IN)', 's' => 10],
                ['v' => (float) $dataset['summary']['total_in'], 's' => 11],
            ];
            $styledRows[] = [
                ['v' => '', 's' => 0], ['v' => '', 's' => 0], ['v' => '', 's' => 0], ['v' => '', 's' => 0],
                ['v' => 'Total Kas Keluar (OUT)', 's' => 10],
                ['v' => (float) $dataset['summary']['total_out'], 's' => 11],
            ];
            $styledRows[] = [
                ['v' => '', 's' => 0], ['v' => '', 's' => 0], ['v' => '', 's' => 0], ['v' => '', 's' => 0],
                ['v' => 'Saldo Kas Bersih', 's' => 12],
                ['v' => (float) $dataset['summary']['net_cash'], 's' => 13],
            ];
        } elseif (isset($dataset['summary']['total_paid'], $dataset['summary']['total_balance'])) {
            $styledRows[] = [
                ['v' => '', 's' => 0], ['v' => '', 's' => 0], ['v' => '', 's' => 0], ['v' => '', 's' => 0],
                ['v' => 'Total Sudah Dibayar', 's' => 10],
                ['v' => (float) $dataset['summary']['total_paid'], 's' => 11],
            ];
            $styledRows[] = [
                ['v' => '', 's' => 0], ['v' => '', 's' => 0], ['v' => '', 's' => 0], ['v' => '', 's' => 0],
                ['v' => 'Total Sisa Piutang', 's' => 10],
                ['v' => (float) $dataset['summary']['total_balance'], 's' => 11],
            ];
        }

        $styledRows[] = [
            ['v' => '', 's' => 0], ['v' => '', 's' => 0], ['v' => '', 's' => 0], ['v' => '', 's' => 0],
            ['v' => 'TOTAL NILAI REKAPITULASI', 's' => 12],
            ['v' => (float) ($dataset['summary']['total_amount'] ?? 0), 's' => 13],
        ];

        $xlsxBinary = $this->buildXlsxBinary($styledRows);
        $filename = 'laporan-'.$dataset['report_type'].'-pkdi.xlsx';

        return response($xlsxBinary, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * Menyusun dataset Laporan Operasional Gudang berdasarkan filter periode, jenis transaksi, paket layanan, dan status.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function buildWarehouseReportDataset(array $filters): array
    {
        $from = isset($filters['from']) && trim((string) $filters['from']) !== '' ? trim((string) $filters['from']) : null;
        $to = isset($filters['to']) && trim((string) $filters['to']) !== '' ? trim((string) $filters['to']) : null;
        $transactionType = isset($filters['transaction_type']) && trim((string) $filters['transaction_type']) !== ''
            ? strtoupper(trim((string) $filters['transaction_type']))
            : null;
        $serviceId = isset($filters['reconditioning_service_id']) && trim((string) $filters['reconditioning_service_id']) !== ''
            ? (int) $filters['reconditioning_service_id']
            : null;
        $status = isset($filters['status']) && trim((string) $filters['status']) !== ''
            ? strtoupper(trim((string) $filters['status']))
            : null;
        $search = isset($filters['q']) && trim((string) $filters['q']) !== '' ? trim((string) $filters['q']) : null;

        $query = ServiceOrder::query()
            ->with(['clientCompany', 'drumProduct', 'reconditioningService', 'latestReceipt', 'latestSorting', 'result', 'billableTransaction'])
            ->orderByDesc('submitted_at')
            ->orderByDesc('id');

        if ($from) {
            $query->whereDate('submitted_at', '>=', $from);
        }
        if ($to) {
            $query->whereDate('submitted_at', '<=', $to);
        }
        if ($transactionType && in_array($transactionType, ServiceOrder::TRANSACTION_TYPES, true)) {
            $normalizedType = $transactionType === 'DRUM_SALE' ? ServiceOrder::TYPE_WASTE_TRANSPORT : $transactionType;
            $query->whereIn('transaction_type', $normalizedType === ServiceOrder::TYPE_WASTE_TRANSPORT ? ['WASTE_TRANSPORT', 'DRUM_SALE'] : [$normalizedType]);
        }
        if ($serviceId) {
            $query->where('reconditioning_service_id', $serviceId);
        }
        if ($status) {
            $query->where('order_status', $status);
        }
        if ($search) {
            $query->where(function ($q) use ($search): void {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhere('po_number', 'like', "%{$search}%")
                    ->orWhere('service_category', 'like', "%{$search}%")
                    ->orWhere('service_type', 'like', "%{$search}%")
                    ->orWhereHas('clientCompany', fn ($cq) => $cq->where('company_name', 'like', "%{$search}%"));
            });
        }

        $orders = $query->get();

        $rows = [];
        $totalRequested = 0;
        $totalReceived = 0;
        $totalCompleted = 0;
        $totalRejected = 0;
        $readyToBillCount = 0;

        foreach ($orders as $order) {
            $reqQty = (int) ($order->requested_quantity ?? 0);
            $recQty = (int) ($order->latestReceipt?->actual_received_quantity ?? $reqQty);
            $doneQty = (int) ($order->result?->completed_quantity ?? 0);
            $rejQty = (int) ($order->result?->unprocessed_or_rejected_quantity ?? ($order->latestSorting?->qty_rejected ?? 0));

            $totalRequested += $reqQty;
            $totalReceived += $recQty;
            $totalCompleted += $doneQty;
            $totalRejected += $rejQty;

            if ($order->billing_status === 'READY_TO_BILL') {
                $readyToBillCount++;
            }

            $statusMeta = PkdiDemoData::statusMeta($order->order_status);
            $productName = $order->drumProduct?->name ?? $order->service_category ?? '-';
            $serviceLabel = $order->isWasteTransport()
                ? 'Pengangkutan Limbah'
                : ($order->reconditioningService?->name ?? $order->service_type ?? '-');

            $rows[] = [
                'order_number' => $order->order_number,
                'order_slug' => $order->order_slug,
                'date' => $order->submitted_at?->timezone('Asia/Jakarta')->format('d M Y') ?? '-',
                'client' => $order->clientCompany?->company_name ?? $order->origin_company_name,
                'transaction_type' => $order->transaction_type ?? ServiceOrder::TYPE_RECONDITIONING,
                'transaction_type_label' => $order->transactionTypeLabel(),
                'product' => $productName,
                'service' => $serviceLabel,
                'unit' => $order->result?->unit ?? $order->requested_unit,
                'requested_qty' => $reqQty,
                'received_qty' => $order->latestReceipt?->actual_received_quantity,
                'completed_qty' => $doneQty,
                'rejected_qty' => $rejQty,
                'order_status' => $order->order_status,
                'status_label' => $statusMeta['label'],
                'status_tone' => $statusMeta['tone'],
                'billing_status' => $order->billing_status ?? 'NOT_READY',
                'billing_status_meta' => config('pkdi.billing_statuses.'.($order->billing_status ?? 'NOT_READY'), ['label' => 'Belum Ditagihkan', 'tone' => 'neutral']),
                'billable_code' => $order->billableTransaction?->transaction_code,
            ];
        }

        return [
            'report_type' => 'warehouse',
            'title' => 'Laporan Operasional Gudang PT PKDI',
            'date_basis_label' => 'Berdasarkan Tanggal Pengajuan Pesanan',
            'from' => $from,
            'to' => $to,
            'transaction_type' => $transactionType,
            'reconditioning_service_id' => $serviceId,
            'status' => $status,
            'q' => $search,
            'rows' => $rows,
            'summary' => [
                'total_orders' => count($rows),
                'total_requested' => $totalRequested,
                'total_received' => $totalReceived,
                'total_completed' => $totalCompleted,
                'total_rejected' => $totalRejected,
                'ready_to_bill_count' => $readyToBillCount,
            ],
        ];
    }

    /**
     * Menghasilkan PDF resmi Laporan Operasional Gudang (%PDF-1.4).
     *
     * @param  array<string, mixed>  $dataset
     */
    public function exportWarehouseReportPdf(array $dataset): Response
    {
        $pages = [];
        $ops = [];

        $fromLabel = $dataset['from'] ? date('d M Y', strtotime((string) $dataset['from'])) : 'Awal';
        $toLabel = $dataset['to'] ? date('d M Y', strtotime((string) $dataset['to'])) : 'Sekarang';
        $periodText = ($dataset['from'] || $dataset['to']) ? "{$fromLabel} s/d {$toLabel}" : 'Semua Periode';
        $printedAt = now('Asia/Jakarta')->format('d M Y, H:i').' WIB';
        $rows = $dataset['rows'] ?? [];
        $summary = $dataset['summary'] ?? [];

        $ops[] = $this->pdfRect(36, 748, 523, 56, '0.533 0.075 0.216');
        $ops[] = $this->pdfText(50, 782, 'PT PULO KENCANA DRUM INDUSTRI (PKDI)', 'F2', 13, '1 1 1');
        $ops[] = $this->pdfText(50, 764, 'Divisi Operasional Gudang, Penerimaan & Rekondisi Drum', 'F1', 8.5, '0.96 0.86 0.89');
        $ops[] = $this->pdfTextRight(545, 783, 'LAPORAN OPERASIONAL GUDANG', 'F2', 8.5, '1 1 1');
        $ops[] = $this->pdfTextRight(545, 764, 'Dicetak: '.$printedAt, 'F1', 8, '0.96 0.86 0.89');

        $ops[] = $this->pdfRect(36, 686, 523, 52, '0.976 0.976 0.980', '0.86 0.86 0.89', 0.7);
        $ops[] = $this->pdfText(48, 717, (string) $dataset['title'], 'F2', 11, '0.09 0.09 0.11');
        $ops[] = $this->pdfText(48, 699, 'Acuan Data: '.(string) $dataset['date_basis_label'], 'F1', 8.5, '0.38 0.38 0.43');
        $ops[] = $this->pdfTextRight(547, 717, 'Periode: '.$periodText, 'F2', 8.5, '0.15 0.15 0.18');
        $ops[] = $this->pdfTextRight(547, 699, 'Total Pesanan: '.($summary['total_orders'] ?? 0).' pesanan', 'F1', 8.5, '0.38 0.38 0.43');

        $kpiCards = [
            [
                'label' => 'TOTAL PESANAN GUDANG',
                'value' => number_format((int) ($summary['total_orders'] ?? 0), 0, ',', '.').' Pesanan',
                'accent' => '0.533 0.075 0.216',
                'color' => '0.09 0.09 0.11',
            ],
            [
                'label' => 'TOTAL SELESAI LAYAK',
                'value' => number_format((int) ($summary['total_completed'] ?? 0), 0, ',', '.').' Unit',
                'accent' => '0.086 0.639 0.290',
                'color' => '0.07 0.48 0.22',
            ],
            [
                'label' => 'TIDAK LAYAK / REJECT',
                'value' => number_format((int) ($summary['total_rejected'] ?? 0), 0, ',', '.').' Unit',
                'accent' => '0.863 0.149 0.149',
                'color' => '0.72 0.11 0.11',
            ],
        ];

        $cardPositions = [36.0, 213.0, 390.0];
        foreach ($kpiCards as $idx => $card) {
            $cx = $cardPositions[$idx];
            $ops[] = $this->pdfRect($cx, 628, 169, 48, '1 1 1', '0.86 0.86 0.89', 0.6);
            $ops[] = $this->pdfRect($cx, 673, 169, 3, $card['accent']);
            $ops[] = $this->pdfText($cx + 10, 657, $card['label'], 'F2', 7.5, '0.42 0.42 0.47');
            $ops[] = $this->pdfText($cx + 10, 639, $card['value'], 'F2', 10.5, $card['color']);
        }

        $colX = [36.0, 58.0, 145.0, 250.0, 385.0, 472.0, 559.0];
        $y = 614.0;

        $drawHeader = function (float $topY) use (&$ops, $colX): float {
            $rowBottom = $topY - 24.0;
            $ops[] = $this->pdfRect(36, $rowBottom, 523, 24, '0.15 0.15 0.18');
            $ops[] = $this->pdfText(42, $rowBottom + 8, 'No', 'F2', 8, '1 1 1');
            $ops[] = $this->pdfText($colX[1] + 5, $rowBottom + 8, 'No. Pesanan', 'F2', 8, '1 1 1');
            $ops[] = $this->pdfText($colX[2] + 5, $rowBottom + 8, 'Perusahaan Client', 'F2', 8, '1 1 1');
            $ops[] = $this->pdfText($colX[3] + 5, $rowBottom + 8, 'Produk & Layanan', 'F2', 8, '1 1 1');
            $ops[] = $this->pdfText($colX[4] + 5, $rowBottom + 8, 'Status', 'F2', 8, '1 1 1');
            $ops[] = $this->pdfTextRight($colX[6] - 6, $rowBottom + 8, 'Selesai / Tolak', 'F2', 8, '1 1 1');

            return $rowBottom;
        };

        $y = $drawHeader($y);

        if (count($rows) === 0) {
            $rowBottom = $y - 32.0;
            $ops[] = $this->pdfRect(36, $rowBottom, 523, 32, '1 1 1', '0.86 0.86 0.89', 0.5);
            $ops[] = $this->pdfText(195, $rowBottom + 12, 'Tidak ada data operasional Gudang pada filter yang dipilih.', 'F1', 8.5, '0.45 0.45 0.50');
            $y = $rowBottom;
        } else {
            foreach ($rows as $idx => $row) {
                $rowHeight = 28.0;
                if ($y - $rowHeight < 100.0) {
                    $ops[] = $this->pdfPageFooter(count($pages) + 1);
                    $pages[] = implode("\n", $ops);
                    $ops = [];
                    $ops[] = $this->pdfText(36, 804, 'PT PULO KENCANA DRUM INDUSTRI (PKDI) - Lanjutan '.$dataset['title'], 'F2', 9, '0.35 0.35 0.40');
                    $y = $drawHeader(790.0);
                }

                $rowBottom = $y - $rowHeight;
                $bg = ($idx % 2 === 1) ? '0.984 0.984 0.988' : '1 1 1';
                $ops[] = $this->pdfRect(36, $rowBottom, 523, $rowHeight, $bg, '0.88 0.88 0.90', 0.5);

                foreach ([1, 2, 3, 4, 5] as $cIdx) {
                    $ops[] = $this->pdfLine($colX[$cIdx], $rowBottom, $colX[$cIdx], $y, '0.88 0.88 0.90', 0.4);
                }

                $textY = $y - 12.0;
                $ops[] = $this->pdfText($colX[0] + 6, $textY, (string) ($idx + 1), 'F1', 8, '0.35 0.35 0.40');
                $ops[] = $this->pdfText($colX[1] + 5, $textY, mb_strimwidth((string) $row['order_number'], 0, 17, ''), 'F2', 7.8, '0.09 0.09 0.11');
                $ops[] = $this->pdfText($colX[1] + 5, $textY - 10, (string) $row['date'], 'F1', 7.2, '0.42 0.42 0.47');
                $ops[] = $this->pdfText($colX[2] + 5, $textY, mb_strimwidth((string) $row['client'], 0, 20, '...'), 'F1', 7.8, '0.15 0.15 0.18');
                $ops[] = $this->pdfText($colX[3] + 5, $textY, mb_strimwidth($row['product'].' - '.$row['service'], 0, 27, '...'), 'F1', 7.8, '0.15 0.15 0.18');
                $ops[] = $this->pdfText($colX[3] + 5, $textY - 10, (string) $row['transaction_type_label'], 'F1', 7.2, '0.42 0.42 0.47');
                $ops[] = $this->pdfText($colX[4] + 5, $textY, mb_strimwidth((string) $row['status_label'], 0, 17, ''), 'F2', 7.5, '0.20 0.20 0.24');
                $ops[] = $this->pdfTextRight($colX[6] - 6, $textY, $row['completed_qty'].' / '.$row['rejected_qty'].' '.$row['unit'], 'F2', 7.8, '0.09 0.09 0.11');

                $y = $rowBottom;
            }
        }

        $ops[] = $this->pdfPageFooter(count($pages) + 1);
        $pages[] = implode("\n", $ops);

        $pdfBinary = $this->compilePdfPages($pages);

        return response($pdfBinary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="laporan-operasional-gudang-pkdi.pdf"',
        ]);
    }

    /**
     * Menghasilkan dokumen Excel (.xlsx OpenXML) untuk Laporan Operasional Gudang.
     *
     * @param  array<string, mixed>  $dataset
     */
    public function exportWarehouseReportExcel(array $dataset): Response
    {
        $fromLabel = $dataset['from'] ? date('d M Y', strtotime((string) $dataset['from'])) : 'Awal';
        $toLabel = $dataset['to'] ? date('d M Y', strtotime((string) $dataset['to'])) : 'Sekarang';
        $periodText = ($dataset['from'] || $dataset['to']) ? "{$fromLabel} s/d {$toLabel}" : 'Semua Periode';
        $printedAt = now('Asia/Jakarta')->format('d M Y, H:i').' WIB';
        $summary = $dataset['summary'] ?? [];

        $styledRows = [
            [
                ['v' => 'PT PULO KENCANA DRUM INDUSTRI (PKDI)', 's' => 1],
                ['v' => '', 's' => 1], ['v' => '', 's' => 1], ['v' => '', 's' => 1], ['v' => '', 's' => 1], ['v' => '', 's' => 1],
            ],
            [
                ['v' => (string) $dataset['title'], 's' => 2],
                ['v' => '', 's' => 2], ['v' => '', 's' => 2], ['v' => '', 's' => 2], ['v' => '', 's' => 2], ['v' => '', 's' => 2],
            ],
            [
                ['v' => 'Periode Filter', 's' => 3],
                ['v' => $periodText, 's' => 4],
                ['v' => '', 's' => 4],
                ['v' => 'Waktu Cetak', 's' => 3],
                ['v' => $printedAt, 's' => 4],
                ['v' => '', 's' => 4],
            ],
            [
                ['v' => 'Total Pesanan', 's' => 3],
                ['v' => (int) ($summary['total_orders'] ?? 0).' pesanan', 's' => 4],
                ['v' => '', 's' => 4],
                ['v' => 'Total Selesai Layak', 's' => 3],
                ['v' => (int) ($summary['total_completed'] ?? 0).' unit', 's' => 4],
                ['v' => '', 's' => 4],
            ],
            [],
            [
                ['v' => 'No.', 's' => 5],
                ['v' => 'No. Pesanan', 's' => 5],
                ['v' => 'Tanggal', 's' => 5],
                ['v' => 'Perusahaan Client', 's' => 5],
                ['v' => 'Transaksi, Produk & Layanan', 's' => 5],
                ['v' => 'Selesai / Tolak (Unit)', 's' => 6],
            ],
        ];

        foreach ($dataset['rows'] as $idx => $row) {
            $styledRows[] = [
                ['v' => $idx + 1, 's' => 8],
                ['v' => (string) $row['order_number'], 's' => 7],
                ['v' => (string) $row['date'], 's' => 8],
                ['v' => (string) $row['client'], 's' => 7],
                ['v' => "{$row['transaction_type_label']} - {$row['product']} ({$row['service']}) | {$row['status_label']}", 's' => 7],
                ['v' => "{$row['completed_qty']} layak / {$row['rejected_qty']} tolak {$row['unit']}", 's' => 8],
            ];
        }

        $styledRows[] = [];
        $styledRows[] = [
            ['v' => '', 's' => 0], ['v' => '', 's' => 0], ['v' => '', 's' => 0], ['v' => '', 's' => 0],
            ['v' => 'TOTAL SELESAI LAYAK', 's' => 12],
            ['v' => (int) ($summary['total_completed'] ?? 0).' unit', 's' => 12],
        ];

        $xlsxBinary = $this->buildXlsxBinary($styledRows);

        return response($xlsxBinary, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="laporan-operasional-gudang-pkdi.xlsx"',
        ]);
    }

    /**
     * @param  array<string, mixed>  $dataset
     * @return list<array{label: string, value: string, accent: string, color: string}>
     */
    private function buildReportKpiCards(array $dataset): array
    {
        $summary = $dataset['summary'] ?? [];
        if (($dataset['report_type'] ?? 'cashbook') === 'invoices') {
            return [
                [
                    'label' => 'TOTAL TAGIHAN DITERBITKAN',
                    'value' => 'Rp '.number_format((float) ($summary['total_amount'] ?? 0), 0, ',', '.'),
                    'accent' => '0.533 0.075 0.216',
                    'color' => '0.09 0.09 0.11',
                ],
                [
                    'label' => 'SUDAH TERBAYAR (LUNAS)',
                    'value' => 'Rp '.number_format((float) ($summary['total_paid'] ?? 0), 0, ',', '.'),
                    'accent' => '0.086 0.639 0.290',
                    'color' => '0.07 0.48 0.22',
                ],
                [
                    'label' => 'SISA PIUTANG CLIENT',
                    'value' => 'Rp '.number_format((float) ($summary['total_balance'] ?? 0), 0, ',', '.'),
                    'accent' => '0.851 0.467 0.024',
                    'color' => '0.70 0.35 0.02',
                ],
            ];
        }

        if (($dataset['report_type'] ?? 'cashbook') === 'expenses') {
            return [
                [
                    'label' => 'TOTAL BIAYA OPERASIONAL',
                    'value' => 'Rp '.number_format((float) ($summary['total_amount'] ?? 0), 0, ',', '.'),
                    'accent' => '0.863 0.149 0.149',
                    'color' => '0.72 0.11 0.11',
                ],
                [
                    'label' => 'FILTER KATEGORI BIAYA',
                    'value' => $dataset['category'] ? mb_strimwidth((string) $dataset['category'], 0, 22, '...') : 'Semua Kategori',
                    'accent' => '0.533 0.075 0.216',
                    'color' => '0.09 0.09 0.11',
                ],
                [
                    'label' => 'JUMLAH PENGELUARAN',
                    'value' => count($dataset['rows'] ?? []).' Transaksi',
                    'accent' => '0.25 0.25 0.30',
                    'color' => '0.09 0.09 0.11',
                ],
            ];
        }

        return [
            [
                'label' => 'TOTAL KAS MASUK (IN)',
                'value' => 'Rp '.number_format((float) ($summary['total_in'] ?? 0), 0, ',', '.'),
                'accent' => '0.086 0.639 0.290',
                'color' => '0.07 0.48 0.22',
            ],
            [
                'label' => 'TOTAL KAS KELUAR (OUT)',
                'value' => 'Rp '.number_format((float) ($summary['total_out'] ?? 0), 0, ',', '.'),
                'accent' => '0.863 0.149 0.149',
                'color' => '0.72 0.11 0.11',
            ],
            [
                'label' => 'SALDO KAS BERSIH',
                'value' => 'Rp '.number_format((float) ($summary['net_cash'] ?? 0), 0, ',', '.'),
                'accent' => '0.533 0.075 0.216',
                'color' => '0.533 0.075 0.216',
            ],
        ];
    }

    private function escapePdfText(string $text): string
    {
        $clean = str_replace(['·', '—', '–'], ['-', '-', '-'], $text);
        $converted = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $clean);
        if (is_string($converted) && $converted !== '') {
            $clean = $converted;
        }

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $clean);
    }

    private function pdfText(float $x, float $y, string $text, string $font = 'F1', float $size = 9.0, string $rgb = '0.09 0.09 0.11'): string
    {
        $escaped = $this->escapePdfText($text);

        return sprintf('BT /%s %.2f Tf %s rg 1 0 0 1 %.2f %.2f Tm (%s) Tj ET', $font, $size, $rgb, $x, $y, $escaped);
    }

    private function pdfTextRight(float $rightX, float $y, string $text, string $font = 'F1', float $size = 9.0, string $rgb = '0.09 0.09 0.11'): string
    {
        $factor = $font === 'F2' ? 0.51 : 0.47;
        $estimatedWidth = strlen($text) * $size * $factor;
        $x = max(36.0, $rightX - $estimatedWidth);

        return $this->pdfText($x, $y, $text, $font, $size, $rgb);
    }

    private function pdfRect(float $x, float $y, float $w, float $h, ?string $fillRgb = null, ?string $strokeRgb = null, float $lineWidth = 0.6): string
    {
        $cmds = ['q', sprintf('%.2f w', $lineWidth)];
        if ($fillRgb !== null) {
            $cmds[] = $fillRgb.' rg';
        }
        if ($strokeRgb !== null) {
            $cmds[] = $strokeRgb.' RG';
        }
        $op = ($fillRgb !== null && $strokeRgb !== null) ? 'B' : ($fillRgb !== null ? 'f' : 'S');
        $cmds[] = sprintf('%.2f %.2f %.2f %.2f re %s', $x, $y, $w, $h, $op);
        $cmds[] = 'Q';

        return implode(' ', $cmds);
    }

    private function pdfLine(float $x1, float $y1, float $x2, float $y2, string $strokeRgb = '0.88 0.88 0.90', float $lineWidth = 0.5): string
    {
        return sprintf('q %.2f w %s RG %.2f %.2f m %.2f %.2f l S Q', $lineWidth, $strokeRgb, $x1, $y1, $x2, $y2);
    }

    private function pdfPageFooter(int $pageNumber): string
    {
        $line = $this->pdfLine(36, 48, 559, 48, '0.86 0.86 0.89', 0.6);
        $left = $this->pdfText(36, 35, 'Dokumen Resmi Sistem Operasional & Keuangan PT Pulo Kencana Drum Industri (PKDI)', 'F1', 7.5, '0.45 0.45 0.50');
        $right = $this->pdfTextRight(559, 35, 'Halaman '.$pageNumber, 'F2', 7.5, '0.45 0.45 0.50');

        return $line."\n".$left."\n".$right;
    }

    /**
     * @param  list<string>  $pageStreams
     */
    private function compilePdfPages(array $pageStreams): string
    {
        if (empty($pageStreams)) {
            $pageStreams = [''];
        }

        $objects = [];
        $pageCount = count($pageStreams);
        $fontRegularObjId = 3 + ($pageCount * 2);
        $fontBoldObjId = $fontRegularObjId + 1;

        $kidsRefs = [];
        for ($i = 0; $i < $pageCount; $i++) {
            $kidsRefs[] = (3 + ($i * 2)).' 0 R';
        }

        $objects[] = '1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj';
        $objects[] = '2 0 obj << /Type /Pages /Kids ['.implode(' ', $kidsRefs).'] /Count '.$pageCount.' >> endobj';

        for ($i = 0; $i < $pageCount; $i++) {
            $pageObjId = 3 + ($i * 2);
            $contentObjId = $pageObjId + 1;
            $stream = $pageStreams[$i];
            $len = strlen($stream);

            $objects[] = "{$pageObjId} 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents {$contentObjId} 0 R /Resources << /Font << /F1 {$fontRegularObjId} 0 R /F2 {$fontBoldObjId} 0 R >> >> >> endobj";
            $objects[] = "{$contentObjId} 0 obj << /Length {$len} >> stream\n{$stream}\nendstream endobj";
        }

        $objects[] = "{$fontRegularObjId} 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >> endobj";
        $objects[] = "{$fontBoldObjId} 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >> endobj";

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $obj) {
            $offsets[] = strlen($pdf);
            $pdf .= $obj."\n";
        }

        $xrefOffset = strlen($pdf);
        $count = count($objects) + 1;
        $pdf .= "xref\n0 {$count}\n0000000000 65535 f \n";
        for ($i = 1; $i < $count; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer << /Size {$count} /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

        return $pdf;
    }

    /**
     * @param  list<list<array{v: string|float|int, s?: int}>>  $rows
     */
    private function buildXlsxBinary(array $rows): string
    {
        $xmlRows = '';
        foreach ($rows as $rIdx => $row) {
            $rowNum = $rIdx + 1;
            $rowHeightAttr = match ($rowNum) {
                1 => ' ht="28" customHeight="1"',
                2 => ' ht="22" customHeight="1"',
                6 => ' ht="24" customHeight="1"',
                default => ' ht="19" customHeight="1"',
            };
            $xmlCells = '';
            foreach ($row as $cIdx => $cell) {
                $colLetter = chr(65 + ($cIdx % 26));
                $cellRef = $colLetter.$rowNum;
                $cellValue = $cell['v'] ?? '';
                $styleIdx = (int) ($cell['s'] ?? 0);
                $styleAttr = $styleIdx > 0 ? " s=\"{$styleIdx}\"" : '';

                if (is_int($cellValue) || is_float($cellValue)) {
                    $xmlCells .= "<c r=\"{$cellRef}\"{$styleAttr}><v>{$cellValue}</v></c>";
                } else {
                    $escaped = htmlspecialchars((string) $cellValue, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                    $xmlCells .= "<c r=\"{$cellRef}\"{$styleAttr} t=\"inlineStr\"><is><t>{$escaped}</t></is></c>";
                }
            }
            $xmlRows .= "<row r=\"{$rowNum}\"{$rowHeightAttr}>{$xmlCells}</row>";
        }

        $colsXml = '<cols>'
            .'<col min="1" max="1" width="7" customWidth="1"/>'
            .'<col min="2" max="2" width="24" customWidth="1"/>'
            .'<col min="3" max="3" width="16" customWidth="1"/>'
            .'<col min="4" max="4" width="25" customWidth="1"/>'
            .'<col min="5" max="5" width="46" customWidth="1"/>'
            .'<col min="6" max="6" width="22" customWidth="1"/>'
            .'</cols>';

        $mergeXml = '<mergeCells count="4">'
            .'<mergeCell ref="A1:F1"/>'
            .'<mergeCell ref="A2:F2"/>'
            .'<mergeCell ref="B3:C3"/>'
            .'<mergeCell ref="B4:C4"/>'
            .'</mergeCells>';

        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .$colsXml
            ."<sheetData>{$xmlRows}</sheetData>"
            .$mergeXml
            .'</worksheet>';

        $stylesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<numFmts count="1">'
            .'<numFmt numFmtId="164" formatCode="&quot;Rp &quot;#,##0;(&quot;Rp &quot;#,##0);&quot;Rp &quot;0"/>'
            .'</numFmts>'
            .'<fonts count="6">'
            .'<font><sz val="10"/><color rgb="FF18181B"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="13"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="11"/><color rgb="FF881337"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="10"/><color rgb="FF18181B"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="10"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="10.5"/><color rgb="FF881337"/><name val="Calibri"/></font>'
            .'</fonts>'
            .'<fills count="6">'
            .'<fill><patternFill patternType="none"/></fill>'
            .'<fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FF881337"/></patternFill></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFF4F4F5"/></patternFill></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FF27272A"/></patternFill></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFFFF1F2"/></patternFill></fill>'
            .'</fills>'
            .'<borders count="3">'
            .'<border><left/><right/><top/><bottom/><diagonal/></border>'
            .'<border><left style="thin"><color rgb="FFD4D4D8"/></left><right style="thin"><color rgb="FFD4D4D8"/></right><top style="thin"><color rgb="FFD4D4D8"/></top><bottom style="thin"><color rgb="FFD4D4D8"/></bottom><diagonal/></border>'
            .'<border><left style="thin"><color rgb="FF881337"/></left><right style="thin"><color rgb="FF881337"/></right><top style="thin"><color rgb="FF881337"/></top><bottom style="double"><color rgb="FF881337"/></bottom><diagonal/></border>'
            .'</borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="14">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="2" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="4" fillId="4" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="4" fillId="4" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="center" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            .'<xf numFmtId="164" fontId="3" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="3" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>'
            .'<xf numFmtId="164" fontId="3" fillId="3" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="5" fillId="5" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>'
            .'<xf numFmtId="164" fontId="5" fillId="5" borderId="2" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>'
            .'</cellXfs>'
            .'</styleSheet>';

        $tmpFile = tempnam(sys_get_temp_dir(), 'pkdi_xlsx_');
        if ($tmpFile === false) {
            return $sheetXml;
        }

        $zip = new ZipArchive;
        if ($zip->open($tmpFile, ZipArchive::OVERWRITE) === true) {
            $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
            $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
            $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
            $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Laporan PKDI" sheetId="1" r:id="rId1"/></sheets></workbook>');
            $zip->addFromString('xl/styles.xml', $stylesXml);
            $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
            $zip->close();

            $binary = (string) file_get_contents($tmpFile);
            @unlink($tmpFile);

            return $binary;
        }

        @unlink($tmpFile);

        return $sheetXml;
    }
}
