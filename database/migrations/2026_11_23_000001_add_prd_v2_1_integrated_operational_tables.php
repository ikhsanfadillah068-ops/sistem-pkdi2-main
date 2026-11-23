<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('service_orders', 'is_locked')) {
                $table->boolean('is_locked')->default(false)->after('payment_method');
            }
            if (! Schema::hasColumn('service_orders', 'handed_over_to_finance_at')) {
                $table->timestamp('handed_over_to_finance_at')->nullable()->after('is_locked');
            }
            if (! Schema::hasColumn('service_orders', 'manager_override_by_user_id')) {
                $table->foreignId('manager_override_by_user_id')->nullable()->after('handed_over_to_finance_at')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('service_orders', 'manager_override_at')) {
                $table->timestamp('manager_override_at')->nullable()->after('manager_override_by_user_id');
            }
            if (! Schema::hasColumn('service_orders', 'manager_override_reason')) {
                $table->text('manager_override_reason')->nullable()->after('manager_override_at');
            }
            if (! Schema::hasColumn('service_orders', 'reordered_from_order_id')) {
                $table->foreignId('reordered_from_order_id')->nullable()->after('manager_override_reason')->constrained('service_orders')->nullOnDelete();
            }
        });

        if (! Schema::hasTable('gate_logs')) {
            Schema::create('gate_logs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('service_order_id')->constrained('service_orders')->cascadeOnDelete();
                $table->string('type', 10)->default('IN'); // IN | OUT
                $table->string('plate_number', 30);
                $table->string('driver_name', 120);
                $table->unsignedInteger('qty_good')->default(0);
                $table->unsignedInteger('qty_dent')->default(0);
                $table->unsignedInteger('qty_reject')->default(0);
                $table->unsignedInteger('total_physical_qty')->default(0);
                $table->json('evidence_photos')->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('recorded_at')->useCurrent();
                $table->timestamps();

                $table->index(['service_order_id', 'type']);
            });
        }

        if (! Schema::hasTable('discrepancy_reports')) {
            Schema::create('discrepancy_reports', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('service_order_id')->constrained('service_orders')->cascadeOnDelete();
                $table->foreignId('gate_log_id')->nullable()->constrained('gate_logs')->nullOnDelete();
                $table->string('ba_number', 40)->unique();
                $table->unsignedInteger('po_qty')->default(0);
                $table->unsignedInteger('actual_good_qty')->default(0);
                $table->unsignedInteger('actual_dent_qty')->default(0);
                $table->unsignedInteger('actual_reject_qty')->default(0);
                $table->unsignedInteger('total_physical_qty')->default(0);
                $table->json('evidence_urls')->nullable();
                $table->text('notes')->nullable();
                $table->string('status', 30)->default('PENDING'); // PENDING | APPROVED | SPLIT_RESOLVED
                $table->string('magic_token', 80)->unique();
                $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('approved_by_name', 150)->nullable();
                $table->string('approved_ip', 64)->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['service_order_id', 'status']);
            });
        }

        if (! Schema::hasTable('compliance_docs')) {
            Schema::create('compliance_docs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('service_order_id')->constrained('service_orders')->cascadeOnDelete();
                $table->string('doc_type', 30); // SURAT_JALAN | MANIFEST_B3 | BAST
                $table->string('document_number', 100)->nullable();
                $table->string('file_path', 255);
                $table->string('original_filename', 255)->nullable();
                $table->boolean('is_verified')->default(true);
                $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('verified_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('verified_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['service_order_id', 'doc_type']);
            });
        }

        if (! Schema::hasTable('recondition_certs')) {
            Schema::create('recondition_certs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('service_order_id')->constrained('service_orders')->cascadeOnDelete();
                $table->string('cert_number', 50)->unique();
                $table->string('qr_hash', 80)->unique();
                $table->unsignedInteger('qc_passed_qty')->default(0);
                $table->date('completion_date');
                $table->string('pdf_path', 255)->nullable();
                $table->foreignId('issued_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('issued_at')->useCurrent();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('client_contracts')) {
            Schema::create('client_contracts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('client_company_id')->constrained('client_companies')->cascadeOnDelete();
                $table->string('service_type', 120);
                $table->foreignId('drum_product_id')->nullable()->constrained('drum_products')->nullOnDelete();
                $table->decimal('unit_price', 14, 2);
                $table->decimal('reject_disposal_fee', 14, 2)->default(0);
                $table->decimal('ppn_rate', 5, 2)->default(11.00);
                $table->decimal('pph23_rate', 5, 2)->default(2.00);
                $table->boolean('is_active')->default(true);
                $table->text('notes')->nullable();
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['client_company_id', 'service_type']);
            });
        }

        Schema::table('invoices', function (Blueprint $table): void {
            if (! Schema::hasColumn('invoices', 'additional_fees')) {
                $table->decimal('additional_fees', 14, 2)->default(0)->after('subtotal_amount');
            }
            if (! Schema::hasColumn('invoices', 'ppn_rate')) {
                $table->decimal('ppn_rate', 5, 2)->default(11.00)->after('tax_amount');
            }
            if (! Schema::hasColumn('invoices', 'ppn_amount')) {
                $table->decimal('ppn_amount', 14, 2)->default(0)->after('ppn_rate');
            }
            if (! Schema::hasColumn('invoices', 'pph23_rate')) {
                $table->decimal('pph23_rate', 5, 2)->default(2.00)->after('ppn_amount');
            }
            if (! Schema::hasColumn('invoices', 'pph23_amount')) {
                $table->decimal('pph23_amount', 14, 2)->default(0)->after('pph23_rate');
            }
            if (! Schema::hasColumn('invoices', 'net_receivable')) {
                $table->decimal('net_receivable', 14, 2)->default(0)->after('pph23_amount');
            }
            if (! Schema::hasColumn('invoices', 'efaktur_number')) {
                $table->string('efaktur_number', 80)->nullable()->after('pdf_path');
            }
            if (! Schema::hasColumn('invoices', 'efaktur_path')) {
                $table->string('efaktur_path', 255)->nullable()->after('efaktur_number');
            }
            if (! Schema::hasColumn('invoices', 'efaktur_uploaded_at')) {
                $table->timestamp('efaktur_uploaded_at')->nullable()->after('efaktur_path');
            }
            if (! Schema::hasColumn('invoices', 'pph23_proof_path')) {
                $table->string('pph23_proof_path', 255)->nullable()->after('efaktur_uploaded_at');
            }
            if (! Schema::hasColumn('invoices', 'pph23_proof_uploaded_at')) {
                $table->timestamp('pph23_proof_uploaded_at')->nullable()->after('pph23_proof_path');
            }
            if (! Schema::hasColumn('invoices', 'pph23_verified')) {
                $table->boolean('pph23_verified')->default(false)->after('pph23_proof_uploaded_at');
            }
            if (! Schema::hasColumn('invoices', 'is_split_invoice')) {
                $table->boolean('is_split_invoice')->default(false)->after('pph23_verified');
            }
            if (! Schema::hasColumn('invoices', 'split_label')) {
                $table->string('split_label', 150)->nullable()->after('is_split_invoice');
            }
            if (! Schema::hasColumn('invoices', 'billed_qty')) {
                $table->unsignedInteger('billed_qty')->nullable()->after('split_label');
            }
            if (! Schema::hasColumn('invoices', 'qr_hash')) {
                $table->string('qr_hash', 80)->nullable()->unique()->after('billed_qty');
            }
        });

        Schema::table('delivery_orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('delivery_orders', 'qr_hash')) {
                $table->string('qr_hash', 80)->nullable()->unique()->after('notes');
            }
        });

        Schema::table('payments', function (Blueprint $table): void {
            if (! Schema::hasColumn('payments', 'pph23_proof_path')) {
                $table->string('pph23_proof_path', 255)->nullable()->after('proof_file_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            if (Schema::hasColumn('payments', 'pph23_proof_path')) {
                $table->dropColumn('pph23_proof_path');
            }
        });

        Schema::table('delivery_orders', function (Blueprint $table): void {
            if (Schema::hasColumn('delivery_orders', 'qr_hash')) {
                $table->dropColumn('qr_hash');
            }
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $cols = [
                'additional_fees', 'ppn_rate', 'ppn_amount', 'pph23_rate', 'pph23_amount',
                'net_receivable', 'efaktur_number', 'efaktur_path', 'efaktur_uploaded_at',
                'pph23_proof_path', 'pph23_proof_uploaded_at', 'pph23_verified',
                'is_split_invoice', 'split_label', 'billed_qty', 'qr_hash',
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('invoices', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::dropIfExists('client_contracts');
        Schema::dropIfExists('recondition_certs');
        Schema::dropIfExists('compliance_docs');
        Schema::dropIfExists('discrepancy_reports');
        Schema::dropIfExists('gate_logs');

        Schema::table('service_orders', function (Blueprint $table): void {
            foreach (['reordered_from_order_id', 'manager_override_by_user_id'] as $fk) {
                if (Schema::hasColumn('service_orders', $fk)) {
                    $table->dropConstrainedForeignId($fk);
                }
            }
            foreach (['is_locked', 'handed_over_to_finance_at', 'manager_override_at', 'manager_override_reason'] as $col) {
                if (Schema::hasColumn('service_orders', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
