<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 50)->unique();
            $table->char('idempotency_key', 36)->unique();
            $table->foreignId('branch_id')
                ->constrained('branches')
                ->restrictOnDelete();
            $table->foreignId('cashier_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->index('cashier_id');
            $table->foreignId('member_id')
                ->nullable()
                ->constrained('members')
                ->restrictOnDelete();
            $table->index('member_id');
            $table->foreignId('member_home_branch_id')
                ->nullable()
                ->constrained('branches')
                ->restrictOnDelete();
            $table->index('member_home_branch_id');
            $table->decimal('subtotal', 15, 2);
            $table->decimal('total_amount', 15, 2);
            $table->decimal('total_cogs', 15, 2);
            $table->enum('payment_method', ['cash', 'midtrans'])->index();
            $table->enum('payment_status', [
                'pending',
                'paid',
                'failed',
                'expired',
                'cancelled',
            ])->default('pending')->index();
            $table->decimal('cash_received', 15, 2)->nullable();
            $table->decimal('change_amount', 15, 2)->nullable();
            $table->string('snap_token', 255)->nullable();
            $table->string('midtrans_payment_type', 50)->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('paid_at')->nullable()->index();
            $table->timestamps();

            $table->index(['branch_id', 'payment_status', 'paid_at']);
            $table->index(['payment_status', 'expires_at']);
        });

        Schema::create('transaction_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')
                ->constrained('transactions')
                ->cascadeOnDelete();
            $table->index('transaction_id');
            $table->foreignId('product_id')
                ->constrained('products')
                ->restrictOnDelete();
            $table->index('product_id');
            $table->string('product_name_snapshot', 200);
            $table->string('barcode_snapshot', 64);
            $table->string('unit_snapshot', 30);
            $table->integer('qty');
            $table->decimal('unit_price_snapshot', 15, 2);
            $table->decimal('cost_price_snapshot', 15, 2);
            $table->decimal('subtotal', 15, 2);
            $table->decimal('subtotal_cogs', 15, 2);
            $table->timestamps();
        });

        Schema::create('payment_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')
                ->nullable()
                ->constrained('transactions')
                ->nullOnDelete();
            $table->index('transaction_id');
            $table->string('gateway', 30)->default('midtrans');
            $table->enum('source', ['webhook', 'manual_check', 'scheduler_check']);
            $table->string('external_order_id', 100)->index();
            $table->string('midtrans_transaction_id', 100)->nullable()->index();
            $table->string('payment_type', 50)->nullable();
            $table->string('transaction_status', 50);
            $table->string('fraud_status', 30)->nullable();
            $table->decimal('gross_amount', 15, 2);
            $table->boolean('signature_verified')->default(false);
            $table->json('raw_payload');
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_logs');
        Schema::dropIfExists('transaction_items');
        Schema::dropIfExists('transactions');
    }
};
