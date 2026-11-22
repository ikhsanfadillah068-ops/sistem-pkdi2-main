<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\BillableTransaction;
use App\Models\ClientCompany;
use App\Models\DrumProduct;
use App\Models\ReconditioningService;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Models\WarehouseReceipt;
use App\Models\WarehouseSorting;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase2WarehousePrdV2WorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_full_reconditioning_warehouse_workflow_receipt_sorting_progress_result_billable_and_delivery(): void
    {
        Storage::fake('local');

        $client = User::query()->where('email', 'client@pkdi.co.id')->firstOrFail();
        $gudang = User::query()->where('email', 'kepalagudang@pkdi.co.id')->firstOrFail();
        $drumBesi = DrumProduct::query()->where('name', 'Drum besi')->firstOrFail();
        $laundryCat = ReconditioningService::query()->where('name', 'Laundry + Cat')->firstOrFail();

        // 1. Client mengajukan pesanan Jasa Rekondisi Drum
        $this->actingAs($client)->post(route('client.orders.store'), [
            'transaction_type' => ServiceOrder::TYPE_RECONDITIONING,
            'drum_product_id' => $drumBesi->id,
            'reconditioning_service_id' => $laundryCat->id,
            'requested_quantity' => 50,
            'requested_unit' => 'Drum',
            'origin_company_name' => 'PT Maju Jaya Kimia',
            'po_number' => 'PO-REC-2026-001',
            'needed_date' => '2026-12-01',
            'specification_notes' => 'Cat biru tua standar industri.',
        ])->assertRedirect();

        /** @var ServiceOrder $order */
        $order = ServiceOrder::query()->latest('id')->firstOrFail();
        $this->assertSame('UNDER_REVIEW', $order->order_status);
        $this->assertSame(50, $order->requested_quantity);

        // 2. Kepala Gudang meninjau dan menerima pesanan -> WAITING_RECEIPT
        $this->actingAs($gudang)->post(route('warehouse.orders.review', $order->order_slug), [
            'decision' => 'ACCEPT',
            'review_notes' => 'Jadwal jalur pencucian dan pengecatan tersedia.',
        ])->assertRedirect(route('warehouse.orders.show', $order->order_slug));

        $order->refresh();
        $this->assertSame('WAITING_RECEIPT', $order->order_status);

        // 2b. Kepala Gudang mencatat koordinasi pengangkutan & perjalanan (internal PKDI) -> IN_TRANSIT
        $this->actingAs($gudang)->post(route('warehouse.orders.transport.store', $order->order_slug), [
            'transport_status' => 'IN_TRANSIT',
            'scheduled_pickup_date' => '2026-11-20',
            'transporter_name' => 'Armada Internal PKDI',
            'vehicle_type' => 'Truk Engkel Box',
            'license_plate' => 'B 9441 PKD',
            'driver_name' => 'Rahmat',
            'driver_phone' => '081288990011',
            'transport_notes' => 'Menuju lokasi pabrik PT Maju Jaya Kimia.',
        ])->assertRedirect(route('warehouse.orders.show', $order->order_slug));

        $order->refresh();
        $this->assertSame('IN_TRANSIT', $order->order_status);
        $this->assertSame('Armada Internal PKDI', $order->transporter_name);
        $this->assertSame('B 9441 PKD', $order->license_plate);
        $this->assertNotNull($order->in_transit_at);

        // Tahapan operasional Gudang menampilkan 5 tahap untuk Rekondisi Drum (tanpa tahap Jadwal & Perjalanan Pengangkutan Limbah)
        $this->actingAs($gudang)->get(route('warehouse.orders.show', $order->order_slug))
            ->assertOk()
            ->assertSee('Tahapan Operasional')
            ->assertSee('5 Tahap')
            ->assertSee('Penyortiran Drum');

        // 3. Kepala Gudang mencatat penerimaan fisik (aktual 48 drum, berbeda dari pengajuan awal 50) + foto
        $receiptPhoto = UploadedFile::fake()->image('terima-drum.jpg', 320, 240);
        $this->actingAs($gudang)->post(route('warehouse.orders.receipt.store', $order->order_slug), [
            'actual_received_quantity' => 48,
            'unit' => 'Drum',
            'initial_condition' => '45 drum kondisi normal residu ringan, 3 drum penyok berat.',
            'discrepancy_notes' => 'Kurang 2 drum dari surat pengajuan awal.',
            'photo' => $receiptPhoto,
        ])->assertRedirect(route('warehouse.orders.show', $order->order_slug));

        $order->refresh();
        $this->assertSame('RECEIVED', $order->order_status);
        // Data pengajuan awal Client tidak boleh ditimpa
        $this->assertSame(50, $order->requested_quantity);
        $receipt = WarehouseReceipt::query()->where('service_order_id', $order->id)->firstOrFail();
        $this->assertSame(48, $receipt->actual_received_quantity);
        $this->assertNotNull($receipt->photo_path);
        Storage::disk('local')->assertExists($receipt->photo_path);

        // 4a. Penyortiran tidak seimbang wajib ditolak (40 + 5 + 2 = 47 != 48)
        $this->actingAs($gudang)->post(route('warehouse.orders.sorting.store', $order->order_slug), [
            'qty_passed' => 40,
            'qty_special_repair' => 5,
            'qty_rejected' => 2,
            'cargo_condition' => 'Hasil sortir awal tidak seimbang.',
            'rejected_reason' => 'Bocor parah di sisi bawah.',
        ])->assertSessionHasErrors('qty_passed');

        // 4b. Reject > 0 tanpa alasan wajib ditolak
        $this->actingAs($gudang)->post(route('warehouse.orders.sorting.store', $order->order_slug), [
            'qty_passed' => 40,
            'qty_special_repair' => 5,
            'qty_rejected' => 3,
            'cargo_condition' => '3 drum bocor korosi berat.',
            'rejected_reason' => '',
        ])->assertSessionHasErrors('rejected_reason');

        // 4c. Penyortiran seimbang (40 + 5 + 3 = 48) + foto berhasil -> IN_PROGRESS
        $sortingPhoto = UploadedFile::fake()->image('sortir-drum.png', 320, 240);
        $this->actingAs($gudang)->post(route('warehouse.orders.sorting.store', $order->order_slug), [
            'qty_passed' => 40,
            'qty_special_repair' => 5,
            'qty_rejected' => 3,
            'cargo_condition' => '40 standar, 5 perlu perbaikan penyok, 3 bocor korosi berat.',
            'sorting_notes' => '5 drum masuk jalur perbaikan sebelum cat.',
            'rejected_reason' => 'Bocor korosi parah pada bagian dasar drum.',
            'photo' => $sortingPhoto,
        ])->assertRedirect(route('warehouse.orders.show', $order->order_slug));

        $order->refresh();
        $this->assertSame('IN_PROGRESS', $order->order_status);
        $sorting = WarehouseSorting::query()->where('service_order_id', $order->id)->firstOrFail();
        $this->assertSame(45, $sorting->processableQuantity());
        $this->assertSame(3, $sorting->qty_rejected);

        // 5. Catat progres visible-to-client & progres internal-only + foto
        $procPhoto = UploadedFile::fake()->image('progres-cat.jpg', 320, 240);
        $this->actingAs($gudang)->post(route('warehouse.orders.processes.store', $order->order_slug), [
            'process_stage' => 'Pengecatan Primer & Warna',
            'progress_notes' => '45 drum selesai dicuci dan masuk oven cat.',
            'processed_qty' => 45,
            'is_visible_to_client' => 1,
            'photo' => $procPhoto,
        ])->assertRedirect(route('warehouse.orders.show', $order->order_slug));

        $this->actingAs($gudang)->post(route('warehouse.orders.processes.store', $order->order_slug), [
            'process_stage' => 'Catatan Internal QC Gudang',
            'progress_notes' => 'RAHASIA_INTERNAL_OPERATOR_SHIFT_2',
            'processed_qty' => 45,
            'is_visible_to_client' => 0,
        ])->assertRedirect(route('warehouse.orders.show', $order->order_slug));

        // Client tidak boleh melihat catatan internal-only
        $this->actingAs($client)->get(route('client.orders.show', $order->order_slug))
            ->assertOk()
            ->assertDontSee('RAHASIA_INTERNAL_OPERATOR_SHIFT_2');

        // 6. Catat hasil akhir pekerjaan + foto -> READY + READY_TO_BILL + 1 BillableTransaction unik
        $resultPhoto = UploadedFile::fake()->image('hasil-akhir.jpg', 320, 240);
        $this->actingAs($gudang)->post(route('warehouse.orders.result.store', $order->order_slug), [
            'reconditioning_service_id' => $laundryCat->id,
            'completed_qty' => 45,
            'rejected_qty' => 3,
            'result_notes' => '45 drum selesai Laundry + Cat, 3 drum rusak dikembalikan terpisah.',
            'photo' => $resultPhoto,
        ])->assertRedirect(route('warehouse.orders.show', $order->order_slug));

        $order->refresh();
        $this->assertSame('READY', $order->order_status);
        $this->assertSame('COMPLETED', $order->warehouse_status);
        $this->assertSame('READY_TO_BILL', $order->billing_status);

        $billable = BillableTransaction::query()->where('service_order_id', $order->id)->firstOrFail();
        $this->assertSame(1, BillableTransaction::query()->where('service_order_id', $order->id)->count());
        $this->assertSame('RECONDITIONING', $billable->transaction_type);
        $this->assertSame('Drum besi', $billable->product_name_snapshot);
        $this->assertSame('Laundry + Cat', $billable->service_name_snapshot);
        $this->assertSame(45, $billable->billable_quantity);
        $this->assertSame(3, $billable->rejected_quantity);
        $this->assertSame('READY_TO_BILL', $billable->billing_status);

        // 7. Catat penyerahan / pengiriman -> IN_DELIVERY -> COMPLETED
        $this->actingAs($gudang)->post(route('warehouse.orders.delivery.store', $order->order_slug), [
            'delivery_action' => 'START_DELIVERY',
            'delivery_method' => 'DIANTAR_PKDI',
            'delivered_quantity' => 45,
            'vehicle_type' => 'Truk Fuso PKDI',
            'license_plate' => 'B 9102 PKD',
            'driver_name' => 'Supriadi',
            'delivery_notes' => 'Berangkat ke Cikarang.',
        ])->assertRedirect(route('warehouse.orders.show', $order->order_slug));

        $order->refresh();
        $this->assertSame('IN_DELIVERY', $order->order_status);
        $this->assertSame('DIANTAR_PKDI', $order->delivery_method);
        $this->assertSame('B 9102 PKD', $order->license_plate);

        $this->actingAs($gudang)->post(route('warehouse.orders.delivery.store', $order->order_slug), [
            'delivery_action' => 'MARK_COMPLETED',
            'delivery_method' => 'DIANTAR_PKDI',
            'delivered_quantity' => 45,
            'delivery_notes' => 'Diterima bagian gudang PT Maju Jaya Kimia.',
        ])->assertRedirect(route('warehouse.orders.show', $order->order_slug));

        $order->refresh();
        $this->assertSame('COMPLETED', $order->order_status);
        $this->assertNotNull($order->delivered_at);
        $this->assertNotNull($order->completed_at);

        // 8. Akses foto dokumentasi dilindungi Policy (Gudang & Client pemilik OK; Client lain 403)
        $this->actingAs($gudang)->get(route('orders.attachments.show', ['code' => $order->order_slug, 'type' => 'receipt']))->assertOk();
        $this->actingAs($gudang)->get(route('orders.attachments.show', ['code' => $order->order_slug, 'type' => 'sorting']))->assertOk();
        $this->actingAs($gudang)->get(route('orders.attachments.show', ['code' => $order->order_slug, 'type' => 'process']))->assertOk();
        $this->actingAs($gudang)->get(route('orders.attachments.show', ['code' => $order->order_slug, 'type' => 'result']))->assertOk();
        $this->actingAs($client)->get(route('orders.attachments.show', ['code' => $order->order_slug, 'type' => 'result']))->assertOk();

        $otherCompany = ClientCompany::query()->create([
            'company_code' => 'CLT-TEST-999',
            'company_name' => 'PT Kompetitor Lain',
            'pic_name' => 'Andi',
            'phone' => '081200001111',
            'email' => 'andi@kompetitor.co.id',
            'address' => 'Bekasi Barat',
        ]);
        $otherClient = User::factory()->create([
            'role' => User::ROLE_CLIENT,
            'client_company_id' => $otherCompany->id,
            'is_active' => true,
        ]);

        $this->actingAs($otherClient)
            ->get(route('orders.attachments.show', ['code' => $order->order_slug, 'type' => 'result']))
            ->assertForbidden();

        // 9. Audit trail tercatat lengkap
        $this->assertTrue(AuditLog::query()->where('action', 'WAREHOUSE_ORDER_ACCEPTED')->exists());
        $this->assertTrue(AuditLog::query()->where('action', 'WAREHOUSE_RECEIPT_RECORDED')->exists());
        $this->assertTrue(AuditLog::query()->where('action', 'WAREHOUSE_SORTING_RECORDED')->exists());
        $this->assertTrue(AuditLog::query()->where('action', 'WAREHOUSE_DELIVERY_COMPLETED')->exists());
    }

    public function test_full_drum_sale_warehouse_workflow_and_reports_pdf_excel_export(): void
    {
        $client = User::query()->where('email', 'client@pkdi.co.id')->firstOrFail();
        $gudang = User::query()->where('email', 'kepalagudang@pkdi.co.id')->firstOrFail();
        $poligen = DrumProduct::query()->where('name', 'Poligen 150 liter')->firstOrFail();

        // Client mengajukan pesanan Pengangkutan Limbah (WASTE_TRANSPORT)
        $this->actingAs($client)->post(route('client.orders.store'), [
            'transaction_type' => ServiceOrder::TYPE_WASTE_TRANSPORT,
            'drum_product_id' => $poligen->id,
            'requested_quantity' => 30,
            'requested_unit' => 'Unit',
            'origin_company_name' => 'PT Maju Jaya Kimia',
            'po_number' => 'PO-WASTE-2026-009',
            'needed_date' => '2026-12-05',
        ])->assertRedirect();

        /** @var ServiceOrder $order */
        $order = ServiceOrder::query()->latest('id')->firstOrFail();
        $this->assertSame(ServiceOrder::TYPE_WASTE_TRANSPORT, $order->transaction_type);

        // Tahapan operasional Gudang untuk Pengangkutan Limbah menampilkan 4 tahap (tanpa tahap sortir/rekondisi)
        $this->actingAs($gudang)->get(route('warehouse.orders.show', $order->order_slug))
            ->assertOk()
            ->assertSee('Tahapan Operasional')
            ->assertSee('4 Tahap')
            ->assertSee('Penerimaan Fisik Limbah')
            ->assertDontSee('Penyortiran Drum');

        // Pengangkutan Limbah masuk PREPARING saat disetujui Kepala Gudang
        $this->actingAs($gudang)->post(route('warehouse.orders.review', $order->order_slug), [
            'decision' => 'ACCEPT',
            'review_notes' => 'Jadwal armada pengangkutan limbah tersedia.',
        ])->assertRedirect(route('warehouse.orders.show', $order->order_slug));

        $order->refresh();
        $this->assertSame('PREPARING', $order->order_status);
        $this->assertSame('PREPARING', $order->warehouse_status);

        // Koordinasi pengangkutan internal PKDI -> IN_TRANSIT
        $this->actingAs($gudang)->post(route('warehouse.orders.transport.store', $order->order_slug), [
            'transport_status' => 'IN_TRANSIT',
            'scheduled_pickup_date' => '2026-11-21',
            'transporter_name' => 'Armada Limbah PKDI',
            'vehicle_type' => 'Truk Fuso Wingbox',
            'license_plate' => 'B 9882 PKD',
            'driver_name' => 'Dedi Kurniawan',
            'driver_phone' => '081377889900',
            'transport_notes' => 'Penjemputan limbah Poligen 150L di Cikarang.',
        ])->assertRedirect(route('warehouse.orders.show', $order->order_slug));

        $this->assertSame('IN_TRANSIT', $order->fresh()->order_status);

        // Penerimaan fisik limbah di PKDI -> RECEIVED
        $this->actingAs($gudang)->post(route('warehouse.orders.receipt.store', $order->order_slug), [
            'actual_received_quantity' => 30,
            'unit' => 'Unit',
            'initial_condition' => '30 unit Poligen 150L diterima lengkap sesuai manifest.',
        ])->assertRedirect(route('warehouse.orders.show', $order->order_slug));

        $this->assertSame('RECEIVED', $order->fresh()->order_status);

        // Konfirmasi hasil penerimaan limbah (tanpa tahap sortir/rekondisi) -> READY + BillableTransaction WASTE_TRANSPORT
        $this->actingAs($gudang)->post(route('warehouse.orders.result.store', $order->order_slug), [
            'actual_service_type' => 'Pengangkutan Limbah',
            'completed_qty' => 30,
            'rejected_qty' => 0,
            'result_notes' => '30 unit limbah Poligen 150L telah diverifikasi dan siap ditagihkan.',
        ])->assertRedirect(route('warehouse.orders.show', $order->order_slug));

        $order->refresh();
        $this->assertSame('READY', $order->order_status);
        $this->assertSame('READY_TO_BILL', $order->billing_status);

        $billable = BillableTransaction::query()->where('service_order_id', $order->id)->firstOrFail();
        $this->assertSame('WASTE_TRANSPORT', $billable->transaction_type);
        $this->assertSame('Poligen 150 liter', $billable->product_name_snapshot);
        $this->assertSame('Pengangkutan Limbah', $billable->service_name_snapshot);
        $this->assertSame(30, $billable->billable_quantity);

        // Penutupan berita acara serah terima -> COMPLETED
        $this->actingAs($gudang)->post(route('warehouse.orders.delivery.store', $order->order_slug), [
            'delivery_action' => 'MARK_COMPLETED',
            'delivery_method' => 'DIAMBIL_CLIENT',
            'delivered_quantity' => 30,
            'delivery_notes' => 'Berita acara pengangkutan limbah ditutup.',
        ])->assertRedirect(route('warehouse.orders.show', $order->order_slug));

        $this->assertSame('COMPLETED', $order->fresh()->order_status);

        // Halaman Laporan Gudang & Ekspor PDF + Excel (kompatibel filter WASTE_TRANSPORT maupun DRUM_SALE)
        $this->actingAs($gudang)->get(route('warehouse.reports.index', ['transaction_type' => 'WASTE_TRANSPORT']))
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee('Poligen 150 liter');

        $pdfResponse = $this->actingAs($gudang)->get(route('warehouse.reports.export', ['format' => 'pdf', 'transaction_type' => 'WASTE_TRANSPORT']));
        $pdfResponse->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $pdfResponse->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF-1.4', (string) $pdfResponse->getContent());

        $xlsxResponse = $this->actingAs($gudang)->get(route('warehouse.reports.export', ['format' => 'xlsx', 'transaction_type' => 'WASTE_TRANSPORT']));
        $xlsxResponse->assertOk();
        $this->assertStringContainsString('spreadsheetml.sheet', (string) $xlsxResponse->headers->get('Content-Type'));
        $this->assertStringStartsWith('PK', (string) $xlsxResponse->getContent());

        // Client tidak boleh mengakses laporan Gudang
        $this->actingAs($client)->get(route('warehouse.reports.index'))->assertForbidden();
        $this->actingAs($client)->get(route('warehouse.reports.export', ['format' => 'pdf']))->assertForbidden();
    }

    public function test_warehouse_review_clarification_and_rejection_require_written_reason(): void
    {
        $client = User::query()->where('email', 'client@pkdi.co.id')->firstOrFail();
        $gudang = User::query()->where('email', 'kepalagudang@pkdi.co.id')->firstOrFail();
        $jerigen = DrumProduct::query()->where('name', 'Jerigen')->firstOrFail();
        $laundry = ReconditioningService::query()->where('name', 'Laundry')->firstOrFail();

        $this->actingAs($client)->post(route('client.orders.store'), [
            'transaction_type' => ServiceOrder::TYPE_RECONDITIONING,
            'drum_product_id' => $jerigen->id,
            'reconditioning_service_id' => $laundry->id,
            'requested_quantity' => 10,
            'requested_unit' => 'Unit',
            'origin_company_name' => 'PT Maju Jaya Kimia',
        ])->assertRedirect();

        /** @var ServiceOrder $order */
        $order = ServiceOrder::query()->latest('id')->firstOrFail();

        // Tanpa alasan wajib ditolak
        $this->actingAs($gudang)->post(route('warehouse.orders.review', $order->order_slug), [
            'decision' => 'NEEDS_CLARIFICATION',
            'decision_reason' => 'ok',
        ])->assertSessionHasErrors('decision_reason');

        // Dengan alasan valid -> NEEDS_CLARIFICATION
        $this->actingAs($gudang)->post(route('warehouse.orders.review', $order->order_slug), [
            'decision' => 'NEEDS_CLARIFICATION',
            'decision_reason' => 'Mohon konfirmasi jenis residu kimia di dalam jerigen.',
        ])->assertRedirect(route('warehouse.orders.show', $order->order_slug));

        $this->assertSame('NEEDS_CLARIFICATION', $order->fresh()->order_status);

        // Penolakan dengan alasan valid -> CANCELLED
        $this->actingAs($gudang)->post(route('warehouse.orders.review', $order->order_slug), [
            'decision' => 'REJECT',
            'decision_reason' => 'Residu B3 kategori khusus tidak dapat diproses di jalur laundry standar.',
        ])->assertRedirect(route('warehouse.orders.show', $order->order_slug));

        $order->refresh();
        $this->assertSame('CANCELLED', $order->order_status);
        $this->assertNotNull($order->cancelled_at);
    }
}
