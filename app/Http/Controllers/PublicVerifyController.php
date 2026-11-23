<?php

namespace App\Http\Controllers;

use App\Models\DeliveryOrder;
use App\Models\Invoice;
use App\Models\ReconditionCert;
use App\Support\QrCodeGenerator;
use Illuminate\View\View;

class PublicVerifyController extends Controller
{
    /**
     * Endpoint Publik Verifikasi QR Code Dokumen Resmi PKDI (`/verify/{document_hash}`).
     */
    public function show(string $documentHash): View
    {
        $hash = trim($documentHash);

        $cert = ReconditionCert::query()
            ->with(['serviceOrder.clientCompany', 'serviceOrder.drumProduct', 'serviceOrder.reconditioningService', 'issuedBy'])
            ->where('qr_hash', $hash)
            ->first();

        if ($cert) {
            $order = $cert->serviceOrder;

            return view('verify.show', [
                'valid' => true,
                'documentType' => 'Sertifikat Rekondisi Drum Resmi',
                'documentNumber' => $cert->cert_number,
                'issuedAt' => $cert->issued_at?->timezone('Asia/Jakarta')->format('d M Y, H.i').' WIB',
                'qrHash' => $cert->qr_hash,
                'qrSvg' => QrCodeGenerator::svg(url('/verify/'.$cert->qr_hash), 140),
                'details' => [
                    'Nomor Sertifikat' => $cert->cert_number,
                    'Nomor Pesanan / PO' => $order?->po_number ? "{$order->po_number} ({$order->order_number})" : ($order?->order_number ?? '-'),
                    'Perusahaan Client' => $order?->clientCompany?->company_name ?? ($order?->origin_company_name ?? '-'),
                    'Kemasan & Layanan' => ($order?->drumProduct?->name ?? $order?->service_category ?? '-').' · '.($order?->reconditioningService?->name ?? $order?->service_type ?? '-'),
                    'Jumlah Drum Lolos QC' => number_format((int) $cert->qc_passed_qty, 0, ',', '.').' '.($order?->requested_unit ?? 'Drum'),
                    'Tanggal Selesai' => $cert->completion_date?->format('d M Y') ?? '-',
                    'Diterbitkan Oleh' => $cert->issuedBy?->name ?? 'Kepala Gudang PKDI',
                ],
            ]);
        }

        $invoice = Invoice::query()
            ->with(['clientCompany', 'serviceOrder'])
            ->where('qr_hash', $hash)
            ->first();

        if ($invoice) {
            return view('verify.show', [
                'valid' => true,
                'documentType' => 'Faktur Tagihan Resmi (Commercial Invoice)',
                'documentNumber' => $invoice->invoice_number,
                'issuedAt' => ($invoice->issued_at ?? $invoice->created_at)?->timezone('Asia/Jakarta')->format('d M Y, H.i').' WIB',
                'qrHash' => $invoice->qr_hash,
                'qrSvg' => QrCodeGenerator::svg(url('/verify/'.$invoice->qr_hash), 140),
                'details' => [
                    'Nomor Invoice' => $invoice->invoice_number,
                    'Nomor Pesanan' => $invoice->serviceOrder?->order_number ?? '-',
                    'Perusahaan Client' => $invoice->clientCompany?->company_name ?? '-',
                    'Tanggal Terbit' => $invoice->issue_date?->format('d M Y') ?? '-',
                    'Jatuh Tempo' => $invoice->due_date?->format('d M Y') ?? '-',
                    'Net Receivable (Tagihan Bersih)' => 'Rp '.number_format((float) ($invoice->net_receivable > 0 ? $invoice->net_receivable : $invoice->total_amount), 0, ',', '.'),
                    'Status Tagihan' => $invoice->status,
                ],
            ]);
        }

        $do = DeliveryOrder::query()
            ->with(['clientCompany', 'serviceOrder', 'issuedBy'])
            ->where('qr_hash', $hash)
            ->first();

        if ($do) {
            return view('verify.show', [
                'valid' => true,
                'documentType' => 'Surat Jalan Resmi (Delivery Order)',
                'documentNumber' => $do->delivery_order_number,
                'issuedAt' => $do->issued_at?->timezone('Asia/Jakarta')->format('d M Y, H.i').' WIB',
                'qrHash' => $do->qr_hash,
                'qrSvg' => QrCodeGenerator::svg(url('/verify/'.$do->qr_hash), 140),
                'details' => [
                    'Nomor Surat Jalan' => $do->delivery_order_number,
                    'Nomor Pesanan' => $do->serviceOrder?->order_number ?? '-',
                    'Perusahaan Tujuan' => $do->clientCompany?->company_name ?? '-',
                    'Ringkasan Muatan' => $do->cargo_summary,
                    'Kuantitas Dikeluarkan' => "{$do->quantity_released} {$do->unit}",
                    'Armada / Nopol' => trim(($do->vehicle_type ?? '').' '.($do->license_plate ?? '')) ?: '-',
                    'Diterbitkan Oleh' => $do->issuedBy?->name ?? 'Kepala Gudang PKDI',
                ],
            ]);
        }

        return view('verify.show', [
            'valid' => false,
            'documentType' => null,
            'documentNumber' => null,
            'issuedAt' => null,
            'qrHash' => $hash,
            'qrSvg' => null,
            'details' => [],
        ]);
    }
}
