<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('drum_products')) {
            Schema::create('drum_products', function (Blueprint $table): void {
                $table->id();
                $table->string('code', 40)->unique();
                $table->string('name', 120)->unique();
                $table->string('capacity_label', 60)->nullable();
                $table->string('default_unit', 30)->default('Drum');
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('reconditioning_services')) {
            Schema::create('reconditioning_services', function (Blueprint $table): void {
                $table->id();
                $table->string('code', 40)->unique();
                $table->string('name', 120)->unique();
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        Schema::table('client_companies', function (Blueprint $table): void {
            if (! Schema::hasColumn('client_companies', 'nib_number')) {
                $table->string('nib_number', 60)->nullable()->after('npwp');
            }
            if (! Schema::hasColumn('client_companies', 'nib_file_path')) {
                $table->string('nib_file_path', 255)->nullable()->after('nib_number');
            }
            if (! Schema::hasColumn('client_companies', 'documents_status')) {
                $table->string('documents_status', 30)->default('INCOMPLETE')->index()->after('nib_file_path');
            }
        });

        Schema::table('service_orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('service_orders', 'transaction_type')) {
                $table->string('transaction_type', 30)->default('RECONDITIONING')->index()->after('created_by_user_id');
            }
            if (! Schema::hasColumn('service_orders', 'drum_product_id')) {
                $table->foreignId('drum_product_id')->nullable()->after('transaction_type')->constrained('drum_products')->nullOnDelete();
            }
            if (! Schema::hasColumn('service_orders', 'reconditioning_service_id')) {
                $table->foreignId('reconditioning_service_id')->nullable()->after('drum_product_id')->constrained('reconditioning_services')->nullOnDelete();
            }
            if (! Schema::hasColumn('service_orders', 'po_number')) {
                $table->string('po_number', 80)->nullable()->index()->after('requested_unit');
            }
            if (! Schema::hasColumn('service_orders', 'po_attachment_path')) {
                $table->string('po_attachment_path', 255)->nullable()->after('po_number');
            }
            if (! Schema::hasColumn('service_orders', 'needed_date')) {
                $table->date('needed_date')->nullable()->index()->after('po_attachment_path');
            }
            if (! Schema::hasColumn('service_orders', 'specification_notes')) {
                $table->text('specification_notes')->nullable()->after('client_notes');
            }
            if (! Schema::hasColumn('service_orders', 'billing_status')) {
                $table->string('billing_status', 30)->default('NOT_READY')->index()->after('warehouse_status');
            }
            if (! Schema::hasColumn('service_orders', 'delivery_method')) {
                $table->string('delivery_method', 30)->nullable()->after('payment_status');
            }
            if (! Schema::hasColumn('service_orders', 'delivered_quantity')) {
                $table->integer('delivered_quantity')->nullable()->after('delivery_method');
            }
            if (! Schema::hasColumn('service_orders', 'delivered_at')) {
                $table->timestamp('delivered_at')->nullable()->after('delivered_quantity');
            }
            if (! Schema::hasColumn('service_orders', 'delivery_notes')) {
                $table->text('delivery_notes')->nullable()->after('delivered_at');
            }
        });

        // Kendaraan dan muatan awal teks bebas tidak lagi wajib pada PRD 2.0.
        Schema::table('service_orders', function (Blueprint $table): void {
            $table->string('service_category', 150)->nullable()->change();
            $table->string('cargo_type', 150)->nullable()->change();
            $table->string('service_type', 150)->nullable()->change();
            $table->string('vehicle_type', 100)->nullable()->change();
            $table->string('license_plate', 25)->nullable()->change();
            $table->string('gate_status', 40)->default('NOT_REQUIRED')->change();
        });

        if (! Schema::hasTable('warehouse_receipts')) {
            Schema::create('warehouse_receipts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('service_order_id')->constrained('service_orders')->cascadeOnDelete();
                $table->string('receipt_number', 40)->unique();
                $table->timestamp('received_at');
                $table->integer('actual_received_quantity');
                $table->string('unit', 30)->default('Drum');
                $table->text('initial_condition');
                $table->text('discrepancy_notes')->nullable();
                $table->string('photo_path', 255)->nullable();
                $table->foreignId('received_by_user_id')->constrained('users')->restrictOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('warehouse_sortings')) {
            Schema::create('warehouse_sortings', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('service_order_id')->constrained('service_orders')->cascadeOnDelete();
                $table->foreignId('warehouse_receipt_id')->nullable()->constrained('warehouse_receipts')->nullOnDelete();
                $table->integer('qty_passed');
                $table->integer('qty_special_repair')->default(0);
                $table->integer('qty_rejected')->default(0);
                $table->string('unit', 30)->default('Drum');
                $table->text('sorting_notes')->nullable();
                $table->text('rejected_reason')->nullable();
                $table->string('photo_path', 255)->nullable();
                $table->foreignId('sorted_by_user_id')->constrained('users')->restrictOnDelete();
                $table->timestamp('sorted_at');
                $table->timestamps();
            });
        }

        Schema::table('warehouse_inspections', function (Blueprint $table): void {
            if (! Schema::hasColumn('warehouse_inspections', 'photo_path')) {
                $table->string('photo_path', 255)->nullable()->after('decision_reason');
            }
        });

        Schema::table('service_processes', function (Blueprint $table): void {
            if (! Schema::hasColumn('service_processes', 'photo_path')) {
                $table->string('photo_path', 255)->nullable()->after('processed_qty');
            }
        });

        Schema::table('service_results', function (Blueprint $table): void {
            if (! Schema::hasColumn('service_results', 'reconditioning_service_id')) {
                $table->foreignId('reconditioning_service_id')->nullable()->after('service_order_id')->constrained('reconditioning_services')->nullOnDelete();
            }
            if (! Schema::hasColumn('service_results', 'photo_path')) {
                $table->string('photo_path', 255)->nullable()->after('result_notes');
            }
        });

        if (! Schema::hasTable('billable_transactions')) {
            Schema::create('billable_transactions', function (Blueprint $table): void {
                $table->id();
                $table->string('transaction_code', 40)->unique();
                $table->foreignId('service_order_id')->unique()->constrained('service_orders')->restrictOnDelete();
                $table->foreignId('client_company_id')->constrained('client_companies')->restrictOnDelete();
                $table->string('transaction_type', 30)->index();
                $table->foreignId('drum_product_id')->nullable()->constrained('drum_products')->nullOnDelete();
                $table->foreignId('reconditioning_service_id')->nullable()->constrained('reconditioning_services')->nullOnDelete();
                $table->string('product_name_snapshot', 150);
                $table->string('service_name_snapshot', 150)->nullable();
                $table->integer('billable_quantity');
                $table->integer('rejected_quantity')->default(0);
                $table->string('unit', 30)->default('Drum');
                $table->string('billing_status', 30)->default('READY_TO_BILL')->index();
                $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
                $table->timestamp('ready_at');
                $table->timestamp('invoiced_at')->nullable();
                $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        Schema::table('invoice_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('invoice_items', 'billable_transaction_id')) {
                $table->foreignId('billable_transaction_id')->nullable()->after('service_tariff_id')->constrained('billable_transactions')->nullOnDelete();
            }
            if (! Schema::hasColumn('invoice_items', 'transaction_type')) {
                $table->string('transaction_type', 30)->nullable()->after('billable_transaction_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table): void {
            if (Schema::hasColumn('invoice_items', 'billable_transaction_id')) {
                $table->dropConstrainedForeignId('billable_transaction_id');
            }
            if (Schema::hasColumn('invoice_items', 'transaction_type')) {
                $table->dropColumn('transaction_type');
            }
        });

        Schema::dropIfExists('billable_transactions');

        Schema::table('service_results', function (Blueprint $table): void {
            if (Schema::hasColumn('service_results', 'reconditioning_service_id')) {
                $table->dropConstrainedForeignId('reconditioning_service_id');
            }
            if (Schema::hasColumn('service_results', 'photo_path')) {
                $table->dropColumn('photo_path');
            }
        });

        Schema::table('service_processes', function (Blueprint $table): void {
            if (Schema::hasColumn('service_processes', 'photo_path')) {
                $table->dropColumn('photo_path');
            }
        });

        Schema::table('warehouse_inspections', function (Blueprint $table): void {
            if (Schema::hasColumn('warehouse_inspections', 'photo_path')) {
                $table->dropColumn('photo_path');
            }
        });

        Schema::dropIfExists('warehouse_sortings');
        Schema::dropIfExists('warehouse_receipts');

        Schema::table('service_orders', function (Blueprint $table): void {
            if (Schema::hasColumn('service_orders', 'reconditioning_service_id')) {
                $table->dropConstrainedForeignId('reconditioning_service_id');
            }
            if (Schema::hasColumn('service_orders', 'drum_product_id')) {
                $table->dropConstrainedForeignId('drum_product_id');
            }
            $columns = array_filter([
                'transaction_type',
                'po_number',
                'po_attachment_path',
                'needed_date',
                'specification_notes',
                'billing_status',
                'delivery_method',
                'delivered_quantity',
                'delivered_at',
                'delivery_notes',
            ], static fn (string $col): bool => Schema::hasColumn('service_orders', $col));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });

        Schema::table('client_companies', function (Blueprint $table): void {
            $columns = array_filter([
                'nib_number',
                'nib_file_path',
                'documents_status',
            ], static fn (string $col): bool => Schema::hasColumn('client_companies', $col));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });

        Schema::dropIfExists('reconditioning_services');
        Schema::dropIfExists('drum_products');
    }
};
