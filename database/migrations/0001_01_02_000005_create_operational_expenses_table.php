<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operational_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->date('expense_date')->index();
            $table->enum('category', [
                'listrik_air',
                'gaji_honor',
                'sewa',
                'atk_kemasan',
                'transportasi',
                'pemeliharaan',
                'lainnya',
            ])->index();
            $table->string('description', 200);
            $table->decimal('amount', 15, 2);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'expense_date'], 'idx_opex_branch_date');
            $table->index(['branch_id', 'category', 'expense_date'], 'idx_opex_branch_cat_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_expenses');
    }
};
