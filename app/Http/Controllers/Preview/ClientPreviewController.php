<?php

namespace App\Http\Controllers\Preview;

use App\Http\Controllers\Controller;
use App\Models\ClientCompany;
use App\Models\DrumProduct;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\ReconditioningService;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Services\BillingAndExitWorkflowService;
use App\Services\OrderWorkflowService;
use App\Services\ReportExportService;
use App\Support\AuditLogger;
use App\Support\OrderPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ClientPreviewController extends Controller
{
    public function dashboard(Request $request): View
    {
        $orders = $this->visibleOrders($request->user());

        return view('client.dashboard', [
            'orders' => $orders,
            'invoices' => array_values(array_filter($orders, static fn (array $o): bool => ! empty($o['invoice']))),
        ]);
    }

    public function ordersIndex(Request $request): View
    {
        return view('client.orders.index', [
            'orders' => $this->visibleOrders($request->user()),
        ]);
    }

    public function ordersCreate(Request $request): View
    {
        Gate::authorize('create', ServiceOrder::class);

        DrumProduct::ensureDefaults();
        ReconditioningService::ensureDefaults();

        return view('client.orders.create', [
            'companyName' => $request->user()?->companyDisplay() ?? 'PT Maju Jaya Kimia',
            'drumProducts' => DrumProduct::query()->active()->get(),
            'reconditioningServices' => ReconditioningService::query()->active()->get(),
        ]);
    }

    public function ordersStore(Request $request, OrderWorkflowService $workflow): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        Gate::authorize('create', ServiceOrder::class);

        $validated = $request->validate([
            'transaction_type' => ['required', 'string', 'in:WASTE_TRANSPORT,RECONDITIONING,DRUM_SALE'],
            'drum_product_id' => ['required', 'integer', 'exists:drum_products,id'],
            'reconditioning_service_id' => ['required_if:transaction_type,RECONDITIONING', 'nullable', 'integer', 'exists:reconditioning_services,id'],
            'cargo_type' => ['nullable', 'string', 'max:150'],
            'requested_quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'requested_unit' => ['required', 'string', 'min:1', 'max:30'],
            'needed_date' => ['nullable', 'date'],
            'po_number' => ['nullable', 'string', 'max:80'],
            'po_attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'origin_company_name' => ['required', 'string', 'min:3', 'max:200'],
            'specification_notes' => ['nullable', 'string', 'max:2000'],
            'client_notes' => ['nullable', 'string', 'max:2000'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ], [
            'transaction_type.required' => 'Jenis layanan wajib dipilih.',
            'transaction_type.in' => 'Jenis layanan harus Pengangkutan Limbah atau Pengangkutan & Rekondisi Drum.',
            'drum_product_id.required' => 'Jenis drum / kemasan wajib dipilih dari daftar.',
            'drum_product_id.exists' => 'Jenis drum / kemasan yang dipilih tidak valid.',
            'reconditioning_service_id.required_if' => 'Paket layanan rekondisi wajib dipilih untuk transaksi Jasa Rekondisi.',
            'reconditioning_service_id.exists' => 'Paket layanan rekondisi yang dipilih tidak valid.',
            'requested_quantity.required' => 'Jumlah kuantitas pesanan wajib diisi.',
            'requested_quantity.integer' => 'Jumlah kuantitas harus berupa angka bulat.',
            'requested_quantity.min' => 'Jumlah kuantitas minimal 1.',
            'requested_unit.required' => 'Satuan produk wajib diisi.',
            'needed_date.date' => 'Format tanggal kebutuhan tidak valid.',
            'origin_company_name.required' => 'Asal perusahaan / PT wajib diisi.',
            'origin_company_name.min' => 'Asal perusahaan / PT minimal 3 karakter.',
            'po_attachment.mimes' => 'Dokumen PO harus berformat PDF, JPG, atau PNG.',
            'po_attachment.max' => 'Ukuran dokumen PO maksimal 5 MB.',
            'attachment.mimes' => 'Lampiran dokumen/foto harus berformat PDF, JPG, atau PNG.',
            'attachment.max' => 'Ukuran lampiran maksimal 5 MB.',
        ]);

        $order = $workflow->createOrder(
            client: $user,
            validated: $validated,
            attachment: $request->file('attachment'),
            poAttachment: $request->file('po_attachment'),
            request: $request,
        );

        return redirect()
            ->route('client.orders.show', $order->order_slug)
            ->with('status', "Pesanan {$order->order_number} berhasil dibuat dan diteruskan ke antrean Kepala Gudang.");
    }

    public function ordersShow(Request $request, string $code): View
    {
        /** @var User $user */
        $user = $request->user();

        $dbOrder = ServiceOrder::findByCodeOrSlug($code)
            ?? (ctype_digit($code) ? ServiceOrder::query()->find((int) $code) : null);

        if ($dbOrder) {
            Gate::authorize('view', $dbOrder);

            return view('client.orders.show', [
                'order' => OrderPresenter::toArray($dbOrder),
            ]);
        }

        abort(404);
    }

    public function invoicesIndex(Request $request): View
    {
        return view('client.invoices.index', [
            'invoices' => OrderPresenter::allInvoices($request->user(), excludeDraft: true),
        ]);
    }

    public function invoicesShow(Request $request, string $code): View
    {
        $dbInvoice = Invoice::findByCodeOrSlug($code)
            ?? (ctype_digit($code) ? Invoice::query()->find((int) $code) : null);

        abort_if(! $dbInvoice, 404, 'Invoice tidak ditemukan.');
        Gate::authorize('view', $dbInvoice);

        if ($dbInvoice->status === 'DRAFT') {
            abort(403, 'Tagihan berstatus draft belum diterbitkan ke portal Client.');
        }

        return view('client.invoices.show', [
            'invoice' => OrderPresenter::invoiceToArray($dbInvoice),
        ]);
    }

    public function submitPaymentProof(
        Request $request,
        string $code,
        BillingAndExitWorkflowService $billing,
    ): RedirectResponse {
        /** @var User $client */
        $client = $request->user();

        $invoice = Invoice::findByCodeOrSlug($code)
            ?? (ctype_digit($code) ? Invoice::query()->find((int) $code) : null);

        abort_if(! $invoice, 404, 'Invoice tidak ditemukan.');
        Gate::authorize('submitPaymentProof', $invoice);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:999999999999'],
            'payment_date' => ['required', 'date'],
            'payment_method' => ['required', 'string', 'min:2', 'max:50'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'proof_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'proof_file_name' => ['nullable', 'string', 'max:255'],
        ], [
            'amount.required' => 'Nominal pembayaran wajib diisi.',
            'amount.numeric' => 'Nominal pembayaran harus berupa angka yang valid.',
            'amount.min' => 'Nominal pembayaran minimal Rp 1.',
            'payment_date.required' => 'Tanggal transfer wajib diisi.',
            'payment_date.date' => 'Format tanggal transfer tidak valid.',
            'payment_method.required' => 'Metode / bank pengirim wajib diisi.',
            'proof_file.mimes' => 'Bukti pembayaran harus berformat PDF, JPG, atau PNG.',
            'proof_file.max' => 'Ukuran berkas bukti pembayaran maksimal 5 MB.',
        ]);

        $payment = $billing->submitClientPaymentProof(
            client: $client,
            invoice: $invoice,
            validated: $validated,
            proofFile: $request->file('proof_file'),
            request: $request,
        );

        return redirect()
            ->route('client.invoices.show', $invoice->invoice_slug)
            ->with('status', "Bukti pembayaran {$payment->payment_number} berhasil dikirim dan sedang menunggu verifikasi divisi Keuangan.");
    }

    public function downloadInvoicePdf(
        Request $request,
        string $code,
        ReportExportService $exporter,
    ): Response {
        $invoice = Invoice::findByCodeOrSlug($code)
            ?? (ctype_digit($code) ? Invoice::query()->find((int) $code) : null);

        abort_if(! $invoice, 404, 'Dokumen invoice tidak ditemukan.');
        Gate::authorize('view', $invoice);

        if ($request->user()?->isClient() && $invoice->status === 'DRAFT') {
            abort(403, 'Tagihan draft belum dapat diunduh oleh Client.');
        }

        return $exporter->exportInvoicePdf($invoice);
    }

    public function downloadPaymentProof(Request $request, Payment $payment): Response
    {
        $invoice = $payment->invoice;
        abort_if(! $invoice, 404, 'Dokumen invoice pembayaran tidak ditemukan.');
        Gate::authorize('view', $invoice);

        $path = $payment->proof_file_path;
        abort_if(! $path || ! Storage::disk('local')->exists($path), 404, 'Berkas bukti pembayaran tidak ditemukan di penyimpanan.');

        return Storage::disk('local')->response($path);
    }

    public function downloadOrderAttachment(Request $request, string $code, string $type): Response
    {
        $order = ServiceOrder::findByCodeOrSlug($code)
            ?? (ctype_digit($code) ? ServiceOrder::query()->find((int) $code) : null);

        abort_if(! $order, 404, 'Pesanan tidak ditemukan.');
        Gate::authorize('viewAttachments', $order);

        $path = match ($type) {
            'po' => $order->po_attachment_path,
            'client' => $order->attachment_path,
            'receipt' => $order->latestReceipt?->photo_path,
            'sorting' => $order->latestSorting?->photo_path,
            'inspection' => $order->latestInspection?->photo_path,
            'process' => $order->processes()
                ->when($request->filled('item'), fn ($q) => $q->whereKey((int) $request->query('item')))
                ->whereNotNull('photo_path')
                ->latest('id')
                ->value('photo_path'),
            'result' => $order->result?->photo_path,
            default => null,
        };

        abort_if(! $path || ! Storage::disk('local')->exists($path), 404, 'Dokumen atau foto tidak ditemukan.');

        return Storage::disk('local')->response($path);
    }

    public function company(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $company = $user->clientCompany;

        if ($company) {
            Gate::authorize('view', $company);
        }

        return view('client.company', [
            'company' => $company,
        ]);
    }

    public function updateCompany(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        /** @var ClientCompany|null $company */
        $company = $user->clientCompany;

        abort_if(! $company, 403, 'Profil perusahaan tidak ditemukan pada akun ini.');
        Gate::authorize('update', $company);

        $validated = $request->validate([
            'company_name' => ['required', 'string', 'min:3', 'max:200'],
            'pic_name' => ['required', 'string', 'min:2', 'max:150'],
            'phone' => ['required', 'string', 'min:8', 'max:30'],
            'email' => ['required', 'string', 'email:filter', 'max:150'],
            'address' => ['required', 'string', 'min:10', 'max:1000'],
            'npwp' => ['nullable', 'string', 'max:50'],
            'nib_number' => ['nullable', 'string', 'max:60'],
            'nib_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ], [
            'company_name.required' => 'Nama perusahaan wajib diisi.',
            'company_name.min' => 'Nama perusahaan minimal 3 karakter.',
            'pic_name.required' => 'Nama penanggung jawab (PIC) wajib diisi.',
            'pic_name.min' => 'Nama penanggung jawab minimal 2 karakter.',
            'phone.required' => 'Nomor telepon perusahaan wajib diisi.',
            'phone.min' => 'Nomor telepon perusahaan minimal 8 digit.',
            'email.required' => 'Email kontak perusahaan wajib diisi.',
            'email.email' => 'Masukkan alamat email yang valid.',
            'address.required' => 'Alamat lengkap perusahaan wajib diisi.',
            'address.min' => 'Alamat lengkap perusahaan minimal 10 karakter.',
            'nib_file.mimes' => 'Dokumen NIB harus berformat PDF, JPG, atau PNG.',
            'nib_file.max' => 'Ukuran dokumen NIB maksimal 5 MB.',
        ]);

        $oldValues = $company->only(['company_name', 'pic_name', 'phone', 'email', 'address', 'npwp', 'nib_number', 'documents_status']);

        $nibFilePath = $company->nib_file_path;
        if ($request->hasFile('nib_file')) {
            $nibFilePath = $request->file('nib_file')?->store('client-nib-documents', 'local');
        }

        $npwp = isset($validated['npwp']) && trim((string) $validated['npwp']) !== '' ? trim((string) $validated['npwp']) : null;
        $nibNumber = isset($validated['nib_number']) && trim((string) $validated['nib_number']) !== '' ? trim((string) $validated['nib_number']) : null;
        $documentsStatus = ($npwp !== null && ($nibNumber !== null || $nibFilePath !== null)) ? 'COMPLETE' : 'INCOMPLETE';

        $company->update([
            'company_name' => trim($validated['company_name']),
            'pic_name' => trim($validated['pic_name']),
            'phone' => trim($validated['phone']),
            'email' => strtolower(trim($validated['email'])),
            'address' => trim($validated['address']),
            'npwp' => $npwp,
            'nib_number' => $nibNumber,
            'nib_file_path' => $nibFilePath,
            'documents_status' => $documentsStatus,
        ]);

        AuditLogger::record(
            action: 'CLIENT_COMPANY_UPDATED',
            auditable: $company,
            oldValues: $oldValues,
            newValues: $company->only(['company_name', 'pic_name', 'phone', 'email', 'address', 'npwp', 'nib_number', 'documents_status']),
            actor: $user,
            request: $request,
        );

        return back()->with('status', 'Profil dan administrasi perusahaan pelanggan berhasil diperbarui.');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function visibleOrders(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $dbOrders = ServiceOrder::query()
            ->visibleTo($user)
            ->with([
                'clientCompany',
                'gateLogs.checkedBy',
                'latestInspection.inspectedBy',
                'processes.recordedBy',
                'result.completedBy',
                'latestInvoice.items',
                'latestInvoice.payments',
                'latestDeliveryOrder.issuedBy',
                'statusHistories.changedBy',
            ])
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->get();

        return OrderPresenter::collection($dbOrders);
    }
}
