<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('service_orders', 'transporter_name')) {
                $table->string('transporter_name', 150)->nullable()->after('origin_company_name');
            }
            if (! Schema::hasColumn('service_orders', 'driver_phone')) {
                $table->string('driver_phone', 40)->nullable()->after('driver_name');
            }
            if (! Schema::hasColumn('service_orders', 'scheduled_pickup_date')) {
                $table->date('scheduled_pickup_date')->nullable()->after('needed_date');
            }
            if (! Schema::hasColumn('service_orders', 'in_transit_at')) {
                $table->timestamp('in_transit_at')->nullable()->after('submitted_at');
            }
            if (! Schema::hasColumn('service_orders', 'transport_notes')) {
                $table->text('transport_notes')->nullable()->after('specification_notes');
            }
        });

        // Selaraskan data transaksi non-rekondisi ke istilah PRD v2.1 (WASTE_TRANSPORT) secara non-destruktif.
        if (Schema::hasTable('service_orders')) {
            DB::table('service_orders')
                ->where('transaction_type', 'DRUM_SALE')
                ->update([
                    'transaction_type' => 'WASTE_TRANSPORT',
                    'service_type' => 'Pengangkutan Limbah',
                ]);
        }

        if (Schema::hasTable('billable_transactions')) {
            DB::table('billable_transactions')
                ->where('transaction_type', 'DRUM_SALE')
                ->update([
                    'transaction_type' => 'WASTE_TRANSPORT',
                    'service_name_snapshot' => 'Pengangkutan Limbah',
                ]);
        }

        if (Schema::hasTable('invoice_items')) {
            DB::table('invoice_items')
                ->where('transaction_type', 'DRUM_SALE')
                ->update([
                    'transaction_type' => 'WASTE_TRANSPORT',
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('service_orders', function (Blueprint $table): void {
            $columns = array_filter([
                'transporter_name',
                'driver_phone',
                'scheduled_pickup_date',
                'in_transit_at',
                'transport_notes',
            ], static fn (string $col): bool => Schema::hasColumn('service_orders', $col));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
