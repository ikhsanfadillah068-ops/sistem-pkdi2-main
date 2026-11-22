<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_tariffs', function (Blueprint $table): void {
            $table->id();
            $table->string('tariff_code', 40)->unique();
            $table->string('name', 150);
            $table->string('service_category', 120)->nullable();
            $table->string('cargo_type', 120)->nullable();
            $table->string('service_type', 120);
            $table->string('unit', 30)->default('Drum');
            $table->decimal('unit_price', 15, 2);
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('service_orders', function (Blueprint $table): void {
            $table->id();
            $table->string('order_number', 40)->unique();
            $table->string('order_slug', 40)->unique();
            $table->foreignId('client_company_id')->constrained('client_companies')->restrictOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('service_category', 150);
            $table->string('cargo_type', 150);
            $table->string('service_type', 150);
            $table->integer('requested_quantity')->nullable();
            $table->string('requested_unit', 30)->default('Drum');
            $table->string('origin_company_name', 200);
            $table->string('vehicle_type', 100);
            $table->string('license_plate', 25)->index();
            $table->string('driver_name', 120)->nullable();
            $table->text('client_notes')->nullable();
            $table->string('attachment_path', 255)->nullable();
            $table->string('order_status', 40)->index();
            $table->string('gate_status', 40)->index();
            $table->string('warehouse_status', 40)->index();
            $table->string('payment_status', 40)->index();
            $table->timestamp('submitted_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('order_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_order_id')->constrained('service_orders')->cascadeOnDelete();
            $table->string('track_type', 30)->index();
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40);
            $table->foreignId('changed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->boolean('is_visible_to_client')->default(true);
            $table->timestamps();
        });

        Schema::create('warehouse_inspections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_order_id')->constrained('service_orders')->cascadeOnDelete();
            $table->string('actual_cargo_type', 150);
            $table->integer('actual_quantity');
            $table->string('actual_unit', 30)->default('Drum');
            $table->text('cargo_condition');
            $table->text('discrepancy_notes')->nullable();
            $table->string('decision', 30)->index();
            $table->text('decision_reason')->nullable();
            $table->foreignId('inspected_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('inspected_at');
            $table->timestamps();
        });

        Schema::create('service_processes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_order_id')->constrained('service_orders')->cascadeOnDelete();
            $table->string('process_stage', 100);
            $table->text('progress_notes');
            $table->integer('processed_qty')->nullable();
            $table->boolean('is_visible_to_client')->default(true);
            $table->foreignId('recorded_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('recorded_at');
            $table->timestamps();
        });

        Schema::create('service_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_order_id')->unique()->constrained('service_orders')->cascadeOnDelete();
            $table->string('actual_service_type', 150);
            $table->integer('completed_quantity');
            $table->integer('unprocessed_or_rejected_quantity')->default(0);
            $table->string('unit', 30)->default('Drum');
            $table->text('result_notes')->nullable();
            $table->foreignId('completed_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('completed_at');
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->string('invoice_number', 40)->unique();
            $table->string('invoice_slug', 40)->unique();
            $table->foreignId('service_order_id')->constrained('service_orders')->restrictOnDelete();
            $table->foreignId('client_company_id')->constrained('client_companies')->restrictOnDelete();
            $table->integer('revision_number')->default(0);
            $table->decimal('subtotal_amount', 15, 2)->default(0);
            $table->decimal('adjustment_amount', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->decimal('balance_due', 15, 2)->default(0);
            $table->string('status', 30)->index();
            $table->date('issue_date')->nullable();
            $table->date('due_date')->nullable();
            $table->text('payment_instructions')->nullable();
            $table->text('notes')->nullable();
            $table->string('pdf_path', 255)->nullable();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('invoice_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('service_tariff_id')->nullable()->constrained('service_tariffs')->nullOnDelete();
            $table->string('description', 255);
            $table->integer('quantity');
            $table->string('unit', 30)->default('Drum');
            $table->decimal('unit_price', 15, 2);
            $table->decimal('line_total', 15, 2);
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->string('payment_number', 40)->unique();
            $table->foreignId('invoice_id')->constrained('invoices')->restrictOnDelete();
            $table->foreignId('client_company_id')->constrained('client_companies')->restrictOnDelete();
            $table->date('payment_date');
            $table->decimal('amount', 15, 2);
            $table->string('payment_method', 50);
            $table->string('reference_number', 100)->nullable();
            $table->string('proof_file_path', 255)->nullable();
            $table->string('verification_status', 30)->index();
            $table->text('verification_notes')->nullable();
            $table->foreignId('submitted_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('verified_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('delivery_orders', function (Blueprint $table): void {
            $table->id();
            $table->string('delivery_order_number', 40)->unique();
            $table->foreignId('service_order_id')->constrained('service_orders')->restrictOnDelete();
            $table->foreignId('invoice_id')->constrained('invoices')->restrictOnDelete();
            $table->foreignId('client_company_id')->constrained('client_companies')->restrictOnDelete();
            $table->text('cargo_summary');
            $table->integer('quantity_released');
            $table->string('unit', 30)->default('Drum');
            $table->string('vehicle_type', 80);
            $table->string('license_plate', 25);
            $table->string('driver_name', 120)->nullable();
            $table->string('status', 30)->index();
            $table->text('notes')->nullable();
            $table->string('pdf_path', 255)->nullable();
            $table->foreignId('issued_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('issued_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('vehicle_gate_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_order_id')->constrained('service_orders')->cascadeOnDelete();
            $table->foreignId('delivery_order_id')->nullable()->constrained('delivery_orders')->nullOnDelete();
            $table->string('check_type', 20)->index();
            $table->string('actual_license_plate', 25);
            $table->string('actual_vehicle_type', 80);
            $table->string('actual_driver_name', 120)->nullable();
            $table->boolean('is_plate_matched')->default(true);
            $table->boolean('is_vehicle_matched')->default(true);
            $table->boolean('is_cargo_matched')->nullable();
            $table->string('decision', 20)->index();
            $table->text('hold_or_note_reason')->nullable();
            $table->foreignId('checked_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('checked_at');
            $table->timestamps();
        });

        Schema::create('operational_expenses', function (Blueprint $table): void {
            $table->id();
            $table->string('expense_number', 40)->unique();
            $table->string('expense_category', 120)->index();
            $table->date('expense_date')->index();
            $table->text('description');
            $table->decimal('amount', 15, 2);
            $table->string('recipient_or_vendor', 150)->nullable();
            $table->string('payment_method', 50);
            $table->string('receipt_file_path', 255)->nullable();
            $table->string('status', 20)->default('RECORDED')->index();
            $table->text('void_reason')->nullable();
            $table->foreignId('recorded_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('cashbook_entries', function (Blueprint $table): void {
            $table->id();
            $table->string('entry_number', 40)->unique();
            $table->string('entry_type', 10)->index();
            $table->date('transaction_date')->index();
            $table->string('source_type', 50);
            $table->foreignId('payment_id')->nullable()->unique()->constrained('payments')->nullOnDelete();
            $table->foreignId('operational_expense_id')->nullable()->unique()->constrained('operational_expenses')->nullOnDelete();
            $table->string('description', 255);
            $table->decimal('amount', 15, 2);
            $table->string('status', 20)->default('ACTIVE')->index();
            $table->foreignId('recorded_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('recipient_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('service_order_id')->nullable()->constrained('service_orders')->nullOnDelete();
            $table->string('title', 150);
            $table->text('message');
            $table->string('tone', 20)->default('info');
            $table->string('icon', 40)->default('bell');
            $table->string('action_url', 255)->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_role', 30);
            $table->string('action', 80)->index();
            $table->string('auditable_type', 100);
            $table->unsignedBigInteger('auditable_id');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->text('reason')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['auditable_type', 'auditable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('cashbook_entries');
        Schema::dropIfExists('operational_expenses');
        Schema::dropIfExists('vehicle_gate_logs');
        Schema::dropIfExists('delivery_orders');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('service_results');
        Schema::dropIfExists('service_processes');
        Schema::dropIfExists('warehouse_inspections');
        Schema::dropIfExists('order_status_histories');
        Schema::dropIfExists('service_orders');
        Schema::dropIfExists('service_tariffs');
    }
};
