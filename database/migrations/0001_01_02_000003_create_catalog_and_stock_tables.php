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
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 120)->unique();
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')
                ->constrained('categories')
                ->restrictOnDelete();
            $table->index('category_id');
            $table->string('sku', 50)->unique();
            $table->string('barcode', 64)->unique();
            $table->index('barcode');
            $table->string('name', 200)->index();
            $table->string('unit', 30)->default('pcs');
            $table->decimal('cost_price', 15, 2);
            $table->decimal('selling_price', 15, 2);
            $table->boolean('is_active')->default(true)->index();
            $table->softDeletes();
            $table->index('deleted_at');
            $table->timestamps();
        });

        Schema::create('branch_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')
                ->constrained('branches')
                ->restrictOnDelete();
            $table->foreignId('product_id')
                ->constrained('products')
                ->restrictOnDelete();
            $table->integer('quantity')->default(0);
            $table->integer('reserved_qty')->default(0);
            $table->integer('min_stock')->default(5);
            $table->timestamps();

            $table->unique(['branch_id', 'product_id']);
            $table->index('product_id');
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')
                ->constrained('branches')
                ->restrictOnDelete();
            $table->index('branch_id');
            $table->foreignId('product_id')
                ->constrained('products')
                ->restrictOnDelete();
            $table->index('product_id');
            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->enum('type', ['sale', 'restock', 'adjustment', 'void_return'])->index();
            $table->integer('qty_change');
            $table->integer('qty_before');
            $table->integer('qty_after');
            $table->string('reference_type', 100)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['branch_id', 'product_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('branch_stocks');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
    }
};
