<?php

namespace Database\Factories;

use App\Models\ClientCompany;
use App\Models\Invoice;
use App\Models\ServiceOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $seq = fake()->unique()->numerify('####');
        $invNumber = "INV/PKDI/2026/11/{$seq}";

        return [
            'invoice_number' => $invNumber,
            'invoice_slug' => str_replace('/', '-', $invNumber),
            'service_order_id' => ServiceOrder::factory(),
            'client_company_id' => ClientCompany::factory(),
            'revision_number' => 0,
            'subtotal_amount' => 3500000,
            'adjustment_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 3500000,
            'paid_amount' => 0,
            'balance_due' => 3500000,
            'status' => 'ISSUED',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'payment_instructions' => 'Transfer ke Rekening Operasional PKDI.',
            'notes' => 'Tagihan operasional layanan drum.',
            'created_by_user_id' => User::factory()->keuangan(),
            'issued_at' => now(),
        ];
    }
}
