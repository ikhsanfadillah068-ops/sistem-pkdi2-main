<?php

namespace Database\Factories;

use App\Models\ClientCompany;
use App\Models\ServiceOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceOrder>
 */
class ServiceOrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $seq = fake()->unique()->numberBetween(100, 999);
        $orderNumber = "kamis-19/11/26-{$seq}";

        return [
            'order_number' => $orderNumber,
            'order_slug' => str_replace('/', '-', $orderNumber),
            'client_company_id' => ClientCompany::factory(),
            'created_by_user_id' => User::factory(),
            'transaction_type' => ServiceOrder::TYPE_RECONDITIONING,
            'service_category' => 'Drum besi',
            'cargo_type' => 'Bekas Resin Industri',
            'service_type' => 'Laundry + Cat',
            'requested_quantity' => 50,
            'requested_unit' => 'Drum',
            'po_number' => 'PO-PKDI-'.fake()->numerify('####'),
            'needed_date' => now()->addDays(5)->toDateString(),
            'origin_company_name' => 'PT Pelanggan Industri',
            'vehicle_type' => null,
            'license_plate' => null,
            'driver_name' => null,
            'client_notes' => 'Catatan pengajuan pesanan.',
            'specification_notes' => 'Warna biru industri, tutup standar.',
            'attachment_path' => null,
            'order_status' => 'UNDER_REVIEW',
            'gate_status' => 'NOT_REQUIRED',
            'warehouse_status' => 'PENDING_REVIEW',
            'billing_status' => 'NOT_READY',
            'payment_status' => 'UNPAID',
            'submitted_at' => now(),
        ];
    }
}
