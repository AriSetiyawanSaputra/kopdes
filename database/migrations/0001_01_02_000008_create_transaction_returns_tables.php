<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_number', 50)->unique();
            $table->foreignId('transaction_id')->index()->constrained('transactions')->restrictOnDelete();
            $table->foreignId('branch_id')->index()->constrained('branches')->restrictOnDelete();
            $table->foreignId('processed_by_user_id')->index()->constrained('users')->restrictOnDelete();
            $table->foreignId('member_id')->nullable()->index()->constrained('members')->restrictOnDelete();
            $table->foreignId('member_home_branch_id')->nullable()->index()->constrained('branches')->restrictOnDelete();
            $table->enum('return_type', ['partial', 'full'])->index();
            $table->enum('refund_method', ['cash_refund', 'manual_transfer'])->index();
            $table->enum('cash_source', ['branch_cash', 'active_shift'])->nullable();
            $table->foreignId('refund_cashier_shift_id')->nullable()->index()->constrained('cashier_shifts')->restrictOnDelete();
            $table->string('refund_reference', 120)->nullable();
            $table->decimal('total_refund_amount', 15, 2);
            $table->decimal('total_restocked_cogs', 15, 2)->default(0);
            $table->decimal('total_damaged_cogs', 15, 2)->default(0);
            $table->text('reason');
            $table->enum('status', ['completed'])->default('completed');
            $table->timestamp('returned_at')->index();
            $table->timestamps();

            $table->index(['branch_id', 'status', 'returned_at'], 'idx_returns_branch_status_date');
            $table->index(['member_id', 'status', 'returned_at'], 'idx_returns_member_status_date');
        });

        Schema::create('transaction_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_return_id')->index()->constrained('transaction_returns')->cascadeOnDelete();
            $table->foreignId('transaction_item_id')->index()->constrained('transaction_items')->restrictOnDelete();
            $table->foreignId('product_id')->index()->constrained('products')->restrictOnDelete();
            $table->string('product_name_snapshot', 200);
            $table->integer('qty_returned');
            $table->enum('condition', ['restockable', 'damaged'])->index();
            $table->decimal('unit_price_snapshot', 15, 2);
            $table->decimal('cost_price_snapshot', 15, 2);
            $table->decimal('subtotal_refund', 15, 2);
            $table->decimal('subtotal_cogs', 15, 2);
            $table->timestamps();
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->enum('return_status', ['none', 'partial', 'full'])
                ->default('none')
                ->index()
                ->after('payment_status');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('return_status');
        });

        Schema::dropIfExists('transaction_return_items');
        Schema::dropIfExists('transaction_returns');
    }
};
