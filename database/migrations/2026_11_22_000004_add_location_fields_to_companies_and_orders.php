<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('client_companies')) {
            Schema::table('client_companies', function (Blueprint $table): void {
                if (! Schema::hasColumn('client_companies', 'latitude')) {
                    $table->decimal('latitude', 10, 7)->nullable()->after('address');
                }
                if (! Schema::hasColumn('client_companies', 'longitude')) {
                    $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
                }
                if (! Schema::hasColumn('client_companies', 'google_maps_url')) {
                    $table->string('google_maps_url', 255)->nullable()->after('longitude');
                }
            });
        }

        if (Schema::hasTable('service_orders')) {
            Schema::table('service_orders', function (Blueprint $table): void {
                if (! Schema::hasColumn('service_orders', 'pickup_address')) {
                    $table->text('pickup_address')->nullable()->after('origin_company_name');
                }
                if (! Schema::hasColumn('service_orders', 'pickup_latitude')) {
                    $table->decimal('pickup_latitude', 10, 7)->nullable()->after('pickup_address');
                }
                if (! Schema::hasColumn('service_orders', 'pickup_longitude')) {
                    $table->decimal('pickup_longitude', 10, 7)->nullable()->after('pickup_latitude');
                }
                if (! Schema::hasColumn('service_orders', 'pickup_google_maps_url')) {
                    $table->string('pickup_google_maps_url', 255)->nullable()->after('pickup_longitude');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('client_companies')) {
            Schema::table('client_companies', function (Blueprint $table): void {
                $cols = array_filter(
                    ['latitude', 'longitude', 'google_maps_url'],
                    fn (string $c): bool => Schema::hasColumn('client_companies', $c)
                );
                if ($cols !== []) {
                    $table->dropColumn($cols);
                }
            });
        }

        if (Schema::hasTable('service_orders')) {
            Schema::table('service_orders', function (Blueprint $table): void {
                $cols = array_filter(
                    ['pickup_address', 'pickup_latitude', 'pickup_longitude', 'pickup_google_maps_url'],
                    fn (string $c): bool => Schema::hasColumn('service_orders', $c)
                );
                if ($cols !== []) {
                    $table->dropColumn($cols);
                }
            });
        }
    }
};
