<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cashier_shifts', function (Blueprint $table) {
            $table->id();
            $table->string('shift_code', 40)->unique();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('cashier_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('closed_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->enum('status', ['open', 'closed'])->default('open')->index();
            $table->boolean('is_force_closed')->default(false);
            $table->decimal('opening_cash', 15, 2)->default(0);
            $table->decimal('cash_sales_total', 15, 2)->default(0);
            $table->decimal('midtrans_sales_total', 15, 2)->default(0);
            $table->decimal('cash_in_total', 15, 2)->default(0);
            $table->decimal('cash_out_total', 15, 2)->default(0);
            $table->decimal('cash_refund_total', 15, 2)->default(0);
            $table->decimal('expected_closing_cash', 15, 2)->nullable();
            $table->decimal('actual_closing_cash', 15, 2)->nullable();
            $table->decimal('variance', 15, 2)->nullable();
            $table->text('opening_notes')->nullable();
            $table->text('closing_notes')->nullable();
            $table->timestamp('opened_at')->index();
            $table->timestamp('closed_at')->nullable()->index();
            $table->timestamps();

            $table->index(['branch_id', 'status', 'opened_at'], 'idx_shifts_branch_status_opened');
            $table->index(['cashier_id', 'status'], 'idx_shifts_cashier_status');
        });

        Schema::create('shift_cash_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cashier_shift_id')->constrained('cashier_shifts')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->enum('type', ['cash_in', 'cash_out'])->index();
            $table->decimal('amount', 15, 2);
            $table->string('notes', 255);
            $table->timestamp('created_at')->index();

            $table->index(['cashier_shift_id', 'type'], 'idx_shift_movements_shift_type');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('cashier_shift_id')
                ->nullable()
                ->after('cashier_id')
                ->index()
                ->constrained('cashier_shifts')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cashier_shift_id');
        });

        Schema::dropIfExists('shift_cash_movements');
        Schema::dropIfExists('cashier_shifts');
    }
};
