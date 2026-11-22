<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('service_orders') && ! Schema::hasColumn('service_orders', 'po_date')) {
            Schema::table('service_orders', function (Blueprint $table): void {
                $table->date('po_date')->nullable()->after('po_number');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('service_orders') && Schema::hasColumn('service_orders', 'po_date')) {
            Schema::table('service_orders', function (Blueprint $table): void {
                $table->dropColumn('po_date');
            });
        }
    }
};
