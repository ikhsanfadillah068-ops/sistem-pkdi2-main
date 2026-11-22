<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ClientCompany;
use App\Models\DrumProduct;
use App\Models\ReconditioningService;
use App\Models\ServiceOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase5OrderGateWarehouseWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_order_is_created_with_required_and_optional_fields_and_appears_in_internal_queues(): void
    {
        $this->seed();

        $client = User::query()->where('email', 'client@pkdi.co.id')->firstOrFail();
        $gudang = User::query()->where('role', User::ROLE_KEPALA_GUDANG)->firstOrFail();
        $owner = User::query()->where('role', User::ROLE_OWNER)->firstOrFail();

        $drumBesi = DrumProduct::query()->where('code', 'PRD-DRUM-BESI')->firstOrFail();
        $paketCat = ReconditioningService::query()->where('code', 'REC-LAUNDRY-CAT')->firstOrFail();

        $invalidResponse = $this->actingAs($client)->post('/client/orders', [
            'transaction_type' => '',
            'drum_product_id' => '',
            'requested_quantity' => 0,
            'origin_company_name' => '',
        ]);
        $invalidResponse->assertSessionHasErrors([
            'transaction_type',
            'drum_product_id',
            'requested_quantity',
            'origin_company_name',
        ]);

        $createResponse = $this->actingAs($client)->post('/client/orders', [
            'transaction_type' => ServiceOrder::TYPE_RECONDITIONING,
            'drum_product_id' => $drumBesi->id,
            'reconditioning_service_id' => $paketCat->id,
            'cargo_type' => 'Bekas Pelarut Cat Industri',
            'requested_quantity' => 80,
            'requested_unit' => 'Drum',
            'po_number' => 'PO/MJK/2026/11/080',
            'needed_date' => '2026-11-29',
            'origin_company_name' => 'PT Maju Jaya Kimia',
            'specification_notes' => 'Cat luar warna abu-abu standar.',
            'client_notes' => 'Mohon dijadwalkan penerimaan minggu ini.',
        ]);

        $order = ServiceOrder::query()->where('po_number', 'PO/MJK/2026/11/080')->firstOrFail();

        $createResponse->assertRedirect(route('client.orders.show', $order->order_slug));
        $this->assertMatchesRegularExpression('/^(senin|selasa|rabu|kamis|jumat|sabtu|minggu)-\d{2}\/\d{2}\/\d{2}-\d+$/', $order->order_number);
        $this->assertNull($order->license_plate);
        $this->assertNull($order->driver_name);
        $this->assertSame('UNDER_REVIEW', $order->order_status);
        $this->assertSame('NOT_REQUIRED', $order->gate_status);
        $this->assertSame('PENDING_REVIEW', $order->warehouse_status);
        $this->assertSame('NOT_READY', $order->billing_status);
        $this->assertSame('UNPAID', $order->payment_status);

        $this->assertDatabaseHas('order_status_histories', [
            'service_order_id' => $order->id,
            'from_status' => 'SUBMITTED',
            'to_status' => 'UNDER_REVIEW',
            'changed_by_user_id' => $client->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $client->id,
            'action' => 'ORDER_CREATED',
            'auditable_type' => 'ServiceOrder',
            'auditable_id' => $order->id,
        ]);

        $this->actingAs($gudang)
            ->get('/warehouse/dashboard')
            ->assertOk()
            ->assertSee($order->order_number);

        $this->actingAs($owner)
            ->get('/owner/transactions')
            ->assertOk()
            ->assertSee($order->order_number);
    }

    public function test_warehouse_clarification_and_rejection_require_written_reason(): void
    {
        $this->seed();

        $client = User::query()->where('email', 'client@pkdi.co.id')->firstOrFail();
        $gudang = User::query()->where('role', User::ROLE_KEPALA_GUDANG)->firstOrFail();
        $drumBesi = DrumProduct::query()->where('code', 'PRD-DRUM-BESI')->firstOrFail();
        $laundry = ReconditioningService::query()->where('code', 'REC-LAUNDRY')->firstOrFail();

        $this->actingAs($client)->post('/client/orders', [
            'transaction_type' => ServiceOrder::TYPE_RECONDITIONING,
            'drum_product_id' => $drumBesi->id,
            'reconditioning_service_id' => $laundry->id,
            'requested_quantity' => 30,
            'requested_unit' => 'Drum',
            'po_number' => 'PO/MJK/CLARIFY-01',
            'origin_company_name' => 'PT Maju Jaya Kimia',
        ])->assertRedirect();

        $order = ServiceOrder::query()->where('po_number', 'PO/MJK/CLARIFY-01')->firstOrFail();

        $this->actingAs($gudang)->post("/warehouse/orders/{$order->order_slug}/inspect", [
            'decision' => 'NEEDS_CLARIFICATION',
            'decision_reason' => '',
        ])->assertSessionHasErrors(['decision_reason']);

        $this->actingAs($gudang)->post("/warehouse/orders/{$order->order_slug}/inspect", [
            'decision' => 'NEEDS_CLARIFICATION',
            'decision_reason' => 'Mohon konfirmasi jenis residu cairan pada drum yang dikirim.',
        ])->assertRedirect();

        $this->assertSame('NEEDS_CLARIFICATION', $order->fresh()->order_status);
    }

    public function test_full_warehouse_workflow_client_timeline_isolation_and_audit_trail(): void
    {
        $this->seed();

        $clientA = User::query()->where('email', 'client@pkdi.co.id')->firstOrFail();
        $companyB = ClientCompany::factory()->create(['company_name' => 'PT Pelanggan Kedua']);
        $clientB = User::factory()->client($companyB)->create(['email' => 'client2@pelanggankedua.co.id']);
        $gudang = User::query()->where('role', User::ROLE_KEPALA_GUDANG)->firstOrFail();
        $keuangan = User::query()->where('role', User::ROLE_KEUANGAN)->firstOrFail();
        $owner = User::query()->where('role', User::ROLE_OWNER)->firstOrFail();

        $drumBesi = DrumProduct::query()->where('code', 'PRD-DRUM-BESI')->firstOrFail();
        $paketCat = ReconditioningService::query()->where('code', 'REC-LAUNDRY-CAT')->firstOrFail();

        $this->actingAs($clientA)->post('/client/orders', [
            'transaction_type' => ServiceOrder::TYPE_RECONDITIONING,
            'drum_product_id' => $drumBesi->id,
            'reconditioning_service_id' => $paketCat->id,
            'cargo_type' => 'Bekas Oli Hidrolik',
            'requested_quantity' => 60,
            'requested_unit' => 'Drum',
            'po_number' => 'PO/MJK/FULL-01',
            'origin_company_name' => 'PT Maju Jaya Kimia',
            'client_notes' => 'Pengiriman pagi.',
        ])->assertRedirect();

        $order = ServiceOrder::query()->where('po_number', 'PO/MJK/FULL-01')->firstOrFail();

        $this->actingAs($gudang)->post("/warehouse/orders/{$order->order_slug}/inspect", [
            'decision' => 'APPROVED',
            'actual_cargo_type' => 'Bekas Oli Hidrolik Kental',
            'actual_quantity' => 58,
            'actual_unit' => 'Drum',
            'cargo_condition' => '56 drum utuh, 2 drum penyok berat di bagian bawah.',
            'discrepancy_notes' => 'Jumlah fisik aktual 58 drum (kurang 2 drum dari pengajuan awal 60 drum).',
        ])->assertRedirect();

        $order->refresh();
        $this->assertSame('IN_PROGRESS', $order->order_status);
        $this->assertSame('IN_PROGRESS', $order->warehouse_status);
        $this->assertSame(60, $order->requested_quantity);
        $this->assertSame('Bekas Oli Hidrolik', $order->cargo_type);

        $this->actingAs($gudang)->post("/warehouse/orders/{$order->order_slug}/processes", [
            'process_stage' => 'Pencucian Kimia & Pengecatan',
            'progress_notes' => '56 drum telah dicuci bersih dan masuk oven pengeringan.',
            'processed_qty' => 56,
        ])->assertRedirect();

        $this->actingAs($gudang)->post("/warehouse/orders/{$order->order_slug}/result", [
            'actual_service_type' => 'Laundry + Cat',
            'completed_qty' => 56,
            'rejected_qty' => 2,
            'result_notes' => '56 drum selesai direkondisi, 2 drum bocor parah tidak dapat diproses.',
        ])->assertRedirect();

        $order->refresh();
        $this->assertSame('READY', $order->order_status);
        $this->assertSame('COMPLETED', $order->warehouse_status);
        $this->assertSame('READY_TO_BILL', $order->billing_status);

        $clientDetail = $this->actingAs($clientA)->get("/client/orders/{$order->order_slug}");
        $clientDetail->assertOk()
            ->assertSee('Siap Diserahkan')
            ->assertSee('Bekas Oli Hidrolik Kental')
            ->assertSee('58 Drum')
            ->assertSee('56 Drum')
            ->assertSee('Pencucian Kimia &amp; Pengecatan', false);

        $this->actingAs($clientB)->get("/client/orders/{$order->order_slug}")->assertForbidden();
        $this->actingAs($clientB)->get("/client/orders/{$order->id}")->assertForbidden();

        $heartbeatClientB = $this->actingAs($clientB)
            ->getJson("/status-heartbeat?order={$order->order_slug}")
            ->assertOk();
        $this->assertNull($heartbeatClientB->json('order'));

        $heartbeatClientA = $this->actingAs($clientA)
            ->getJson("/status-heartbeat?order={$order->order_slug}")
            ->assertOk();
        $this->assertSame('fallback_polling', $heartbeatClientA->json('mode'));
        $this->assertSame('READY', $heartbeatClientA->json('order.order_status'));
        $this->assertSame('READY_TO_BILL', $heartbeatClientA->json('order.billing_status'));

        $this->actingAs($keuangan)
            ->get('/finance/billable-orders')
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee('56 Drum selesai');

        $this->actingAs($owner)
            ->get("/owner/transactions/{$order->order_slug}")
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee('56 selesai, 2 tidak layak');

        foreach (['ORDER_CREATED', 'WAREHOUSE_INSPECTION_APPROVED', 'WAREHOUSE_PROCESS_UPDATED', 'SERVICE_COMPLETED'] as $expectedAction) {
            $this->assertTrue(
                AuditLog::query()
                    ->where('auditable_type', 'ServiceOrder')
                    ->where('auditable_id', $order->id)
                    ->where('action', $expectedAction)
                    ->exists(),
                "Audit log {$expectedAction} harus tercatat untuk pesanan {$order->order_number}."
            );
        }
    }
}
