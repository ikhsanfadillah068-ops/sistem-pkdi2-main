<?php

namespace Tests\Feature;

use App\Models\ClientCompany;
use App\Models\DrumProduct;
use App\Models\ReconditioningService;
use App\Models\ServiceOrder;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase1PrdV2StructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_destructive_prd_v2_schema_and_master_data_exist(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertTrue(Schema::hasTable('drum_products'));
        $this->assertTrue(Schema::hasTable('reconditioning_services'));
        $this->assertTrue(Schema::hasTable('warehouse_receipts'));
        $this->assertTrue(Schema::hasTable('warehouse_sortings'));
        $this->assertTrue(Schema::hasTable('billable_transactions'));

        // Tabel warisan tetap ada secara non-destruktif
        $this->assertTrue(Schema::hasTable('vehicle_gate_logs'));
        $this->assertTrue(Schema::hasTable('delivery_orders'));

        foreach (['transaction_type', 'drum_product_id', 'reconditioning_service_id', 'po_number', 'po_attachment_path', 'needed_date', 'specification_notes', 'billing_status', 'delivery_method', 'delivered_quantity', 'delivered_at', 'delivery_notes', 'transporter_name', 'driver_phone', 'scheduled_pickup_date', 'in_transit_at', 'transport_notes'] as $col) {
            $this->assertTrue(Schema::hasColumn('service_orders', $col), "Kolom service_orders.{$col} harus tersedia.");
        }

        foreach (['nib_number', 'nib_file_path', 'documents_status'] as $col) {
            $this->assertTrue(Schema::hasColumn('client_companies', $col), "Kolom client_companies.{$col} harus tersedia.");
        }

        $this->assertSame(
            ['Drum besi', 'Poligen 150 liter', 'Poligen 120 liter', 'Jerigen'],
            DrumProduct::query()->active()->pluck('name')->all()
        );

        $this->assertSame(
            ['Laundry', 'Laundry + Cat', 'Laundry + Cat + Sablon'],
            ReconditioningService::query()->active()->pluck('name')->all()
        );

        // Hanya 4 peran aktif pada seeder (tanpa akun aktif Satpam)
        $this->assertSame(4, User::query()->where('is_active', true)->count());
        $this->assertSame(0, User::query()->where('role', User::ROLE_SATPAM)->where('is_active', true)->count());
    }

    public function test_satpam_role_and_gate_routes_are_retired_from_active_workflow(): void
    {
        $this->seed(DatabaseSeeder::class);

        $legacySatpam = User::factory()->satpam()->create([
            'email' => 'legacy.satpam@pkdi.co.id',
            'password' => Hash::make('satpam123'),
            'is_active' => true,
        ]);

        $this->post('/login', [
            'email' => 'legacy.satpam@pkdi.co.id',
            'password' => 'satpam123',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();

        $gudang = User::query()->where('role', User::ROLE_KEPALA_GUDANG)->firstOrFail();
        $this->actingAs($gudang)->get('/gate/dashboard')->assertNotFound();
        $this->assertFalse($legacySatpam->can('inspectGate', new ServiceOrder));
    }

    public function test_client_can_create_reconditioning_and_drum_sale_orders_directly_to_warehouse_without_vehicle_plate(): void
    {
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);

        $client = User::query()->where('role', User::ROLE_CLIENT)->firstOrFail();
        $gudang = User::query()->where('role', User::ROLE_KEPALA_GUDANG)->firstOrFail();
        $drumBesi = DrumProduct::query()->where('code', 'PRD-DRUM-BESI')->firstOrFail();
        $poligen = DrumProduct::query()->where('code', 'PRD-POLIGEN-150')->firstOrFail();
        $paketCat = ReconditioningService::query()->where('code', 'REC-LAUNDRY-CAT')->firstOrFail();

        // 1. Form buat pesanan menampilkan 4 produk dan tepat 3 paket rekondisi
        $this->actingAs($client)
            ->get('/client/orders/create')
            ->assertOk()
            ->assertSee('Drum besi')
            ->assertSee('Poligen 150 liter')
            ->assertSee('Poligen 120 liter')
            ->assertSee('Jerigen')
            ->assertSee('Laundry + Cat + Sablon')
            ->assertDontSee('Pemeriksaan Satpam');

        // 2. Rekondisi wajib pilih paket layanan
        $this->actingAs($client)
            ->post('/client/orders', [
                'transaction_type' => ServiceOrder::TYPE_RECONDITIONING,
                'drum_product_id' => $drumBesi->id,
                'requested_quantity' => 40,
                'requested_unit' => 'Drum',
                'origin_company_name' => 'PT Maju Jaya Kimia',
            ])
            ->assertSessionHasErrors('reconditioning_service_id');

        // 3. Pengajuan Jasa Rekondisi sukses tanpa nopol/kendaraan, langsung masuk antrean Kepala Gudang
        $recondResponse = $this->actingAs($client)->post('/client/orders', [
            'transaction_type' => ServiceOrder::TYPE_RECONDITIONING,
            'drum_product_id' => $drumBesi->id,
            'reconditioning_service_id' => $paketCat->id,
            'cargo_type' => 'Bekas Pelumas',
            'requested_quantity' => 40,
            'requested_unit' => 'Drum',
            'needed_date' => '2026-11-28',
            'po_number' => 'PO/MJK/2026/11/001',
            'po_attachment' => UploadedFile::fake()->create('po-rekondisi.pdf', 120, 'application/pdf'),
            'origin_company_name' => 'PT Maju Jaya Kimia',
            'specification_notes' => 'Cat biru tua standar pabrik.',
        ]);

        $recondResponse->assertRedirect();
        $recondOrder = ServiceOrder::query()->where('po_number', 'PO/MJK/2026/11/001')->firstOrFail();

        $this->assertSame(ServiceOrder::TYPE_RECONDITIONING, $recondOrder->transaction_type);
        $this->assertSame('UNDER_REVIEW', $recondOrder->order_status);
        $this->assertSame('NOT_REQUIRED', $recondOrder->gate_status);
        $this->assertSame('PENDING_REVIEW', $recondOrder->warehouse_status);
        $this->assertSame('NOT_READY', $recondOrder->billing_status);
        $this->assertNull($recondOrder->license_plate);
        $this->assertNotNull($recondOrder->po_attachment_path);

        // 4. Pengajuan Pengangkutan Limbah sukses tanpa paket rekondisi dan tanpa nopol
        $saleResponse = $this->actingAs($client)->post('/client/orders', [
            'transaction_type' => ServiceOrder::TYPE_WASTE_TRANSPORT,
            'drum_product_id' => $poligen->id,
            'requested_quantity' => 25,
            'requested_unit' => 'Unit',
            'needed_date' => '2026-11-30',
            'po_number' => 'PO/MJK/2026/11/002',
            'origin_company_name' => 'PT Maju Jaya Kimia',
        ]);

        $saleResponse->assertRedirect();
        $saleOrder = ServiceOrder::query()->where('po_number', 'PO/MJK/2026/11/002')->firstOrFail();
        $this->assertSame(ServiceOrder::TYPE_WASTE_TRANSPORT, $saleOrder->transaction_type);
        $this->assertNull($saleOrder->reconditioning_service_id);
        $this->assertSame('Pengangkutan Limbah', $saleOrder->service_type);

        // Stepper Client dibedakan antara Rekondisi Drum (5 tahap) dan Pengangkutan Limbah (4 tahap)
        $this->actingAs($client)
            ->get("/client/orders/{$recondOrder->order_slug}")
            ->assertOk()
            ->assertSee('Tahap 1 dari 5')
            ->assertSee('Pemeriksaan & Sortir')
            ->assertSee('Proses Rekondisi');

        $this->actingAs($client)
            ->get("/client/orders/{$saleOrder->order_slug}")
            ->assertOk()
            ->assertSee('Tahap 1 dari 4')
            ->assertSee('Disetujui & Dalam Perjalanan')
            ->assertSee('Layanan Selesai')
            ->assertDontSee('Proses Rekondisi');

        // 5. Kepala Gudang dapat langsung membuka dan memproses pesanan tanpa persetujuan Satpam
        $this->actingAs($gudang)
            ->get("/warehouse/orders/{$recondOrder->order_slug}")
            ->assertOk()
            ->assertSee($recondOrder->order_number)
            ->assertSee('PO/MJK/2026/11/001')
            ->assertDontSee('Menunggu Persetujuan Masuk dari Satpam');

        // 6. Unduhan berkas PO dilindungi Policy (Client B ditolak, Client pemilik & Kepala Gudang diizinkan)
        $companyB = ClientCompany::factory()->create();
        $clientB = User::factory()->client($companyB)->create();

        $this->actingAs($client)->get("/orders/{$recondOrder->order_slug}/attachments/po")->assertOk();
        $this->actingAs($gudang)->get("/orders/{$recondOrder->order_slug}/attachments/po")->assertOk();
        $this->actingAs($clientB)->get("/orders/{$recondOrder->order_slug}/attachments/po")->assertForbidden();
    }

    public function test_client_company_profile_supports_npwp_and_nib_completeness_tracking(): void
    {
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);

        $client = User::query()->where('role', User::ROLE_CLIENT)->firstOrFail();

        $this->actingAs($client)
            ->put('/client/company', [
                'company_name' => 'PT Maju Jaya Kimia',
                'pic_name' => 'Budi Santoso',
                'phone' => '021-89104421',
                'email' => 'client@pkdi.co.id',
                'address' => 'Kawasan Industri Jababeka Tahap II Blok C-14, Cikarang, Bekasi',
                'npwp' => '01.234.567.8-901.000',
                'nib_number' => '1234567890123',
                'nib_file' => UploadedFile::fake()->create('nib-mjk.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect();

        $company = $client->clientCompany()->firstOrFail();
        $this->assertSame('01.234.567.8-901.000', $company->npwp);
        $this->assertSame('1234567890123', $company->nib_number);
        $this->assertNotNull($company->nib_file_path);
        $this->assertSame('COMPLETE', $company->documents_status);
    }
}
