<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('member_price', 15, 2)->nullable()->after('selling_price');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->decimal('regular_subtotal', 15, 2)->default(0)->after('member_home_branch_id');
            $table->decimal('member_discount_total', 15, 2)->default(0)->after('regular_subtotal');
        });

        Schema::table('transaction_items', function (Blueprint $table) {
            $table->decimal('regular_price_snapshot', 15, 2)->default(0)->after('qty');
            $table->decimal('member_discount_snapshot', 15, 2)->default(0)->after('unit_price_snapshot');
            $table->boolean('is_member_price_applied')->default(false)->after('member_discount_snapshot');
        });

        // Backfill transaksi & item historis MVP agar tetap konsisten secara akuntansi
        DB::table('transactions')->update([
            'regular_subtotal' => DB::raw('subtotal'),
        ]);

        DB::table('transaction_items')->update([
            'regular_price_snapshot' => DB::raw('unit_price_snapshot'),
        ]);
    }

    public function down(): void
    {
        Schema::table('transaction_items', function (Blueprint $table) {
            $table->dropColumn([
                'regular_price_snapshot',
                'member_discount_snapshot',
                'is_member_price_applied',
            ]);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn([
                'regular_subtotal',
                'member_discount_total',
            ]);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('member_price');
        });
    }
};
