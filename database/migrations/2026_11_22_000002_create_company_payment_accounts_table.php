<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('company_payment_accounts')) {
            Schema::create('company_payment_accounts', function (Blueprint $table): void {
                $table->id();
                $table->string('payment_method', 50)->index();
                $table->string('category', 40)->index();
                $table->string('provider_name', 80);
                $table->string('account_number', 80);
                $table->string('account_holder', 150);
                $table->text('instructions')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('company_payment_accounts');
    }
};
