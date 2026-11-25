<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\CashierShift;
use App\Models\Category;
use App\Models\Member;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Milestone6MemberPricingTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branchNgunut;

    protected Branch $branchCekok;

    protected User $adminNasional;

    protected User $cashierNgunut;

    protected Member $memberCekok;

    protected Category $category;

    protected Product $productWithMemberPrice;

    protected Product $productWithoutMemberPrice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->branchNgunut = Branch::where('code', 'KOP-3502-001')->firstOrFail();
        $this->branchCekok = Branch::where('code', 'KOP-3502-002')->firstOrFail();

        $this->adminNasional = User::where('email', 'admin.nasional@kopdes.id')->firstOrFail();
        $this->cashierNgunut = User::where('card_uid', '0008493873')->firstOrFail();

        CashierShift::create([
            'shift_code' => 'SHF-KOP-3502-001-M6-001',
            'branch_id' => $this->branchNgunut->id,
            'cashier_id' => $this->cashierNgunut->id,
            'status' => CashierShift::STATUS_OPEN,
            'opening_cash' => 100000,
            'opened_at' => now(),
        ]);

        // Member terdaftar di KOPDES Desa Cekok (untuk uji portabilitas lintas cabang di Ngunut)
        $this->memberCekok = Member::where('home_branch_id', $this->branchCekok->id)
            ->where('status', 'active')
            ->firstOrFail();

        $this->category = Category::firstOrCreate(
            ['slug' => 'sembako-m6'],
            ['name' => 'Sembako M6']
        );

        $this->productWithMemberPrice = Product::create([
            'category_id' => $this->category->id,
            'sku' => 'SKU-M6-001',
            'barcode' => '8996001001001',
            'name' => 'Beras KOPDES 5kg',
            'unit' => 'karung',
            'cost_price' => 55000,
            'selling_price' => 70000,
            'member_price' => 65000,
            'is_active' => true,
        ]);

        $this->productWithoutMemberPrice = Product::create([
            'category_id' => $this->category->id,
            'sku' => 'SKU-M6-002',
            'barcode' => '8996001001002',
            'name' => 'Gula Pasir 1kg',
            'unit' => 'kg',
            'cost_price' => 14000,
            'selling_price' => 18000,
            'member_price' => null,
            'is_active' => true,
        ]);

        foreach ([$this->productWithMemberPrice, $this->productWithoutMemberPrice] as $product) {
            BranchStock::create([
                'branch_id' => $this->branchNgunut->id,
                'product_id' => $product->id,
                'quantity' => 50,
                'reserved_qty' => 0,
                'min_stock' => 5,
            ]);
        }
    }

    public function test_member_price_validation_rejects_above_selling_price_and_allows_below_cost_price(): void
    {
        // (a1) member_price > selling_price ditolak HTTP 422 saat store
        $this->actingAs($this->adminNasional)
            ->postJson(route('products.store'), [
                'category_id' => $this->category->id,
                'sku' => 'SKU-M6-INVALID',
                'barcode' => '8996001009999',
                'name' => 'Produk Harga Member Salah',
                'unit' => 'pcs',
                'cost_price' => 10000,
                'selling_price' => 15000,
                'member_price' => 16000,
                'is_active' => true,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['member_price']);

        // (a2) member_price > selling_price ditolak HTTP 422 saat update
        $this->actingAs($this->adminNasional)
            ->putJson(route('products.update', $this->productWithMemberPrice), [
                'category_id' => $this->category->id,
                'sku' => $this->productWithMemberPrice->sku,
                'barcode' => $this->productWithMemberPrice->barcode,
                'name' => $this->productWithMemberPrice->name,
                'unit' => $this->productWithMemberPrice->unit,
                'cost_price' => 55000,
                'selling_price' => 70000,
                'member_price' => 75000,
                'is_active' => true,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['member_price']);

        // (a3) member_price < cost_price (non-blocking warning di UI) TETAP diperbolehkan tersimpan
        $this->actingAs($this->adminNasional)
            ->post(route('products.store'), [
                'category_id' => $this->category->id,
                'sku' => 'SKU-M6-SUBSIDI',
                'barcode' => '8996001008888',
                'name' => 'Minyak Subsidi Member 1L',
                'unit' => 'liter',
                'cost_price' => 16000,
                'selling_price' => 18000,
                'member_price' => 14500,
                'is_active' => true,
            ])
            ->assertRedirect(route('products.index'));

        $subsidi = Product::where('sku', 'SKU-M6-SUBSIDI')->firstOrFail();
        $this->assertSame(14500.0, (float) $subsidi->member_price);

        // (a4) Mengosongkan member_price (string kosong) tersimpan sebagai NULL
        $this->actingAs($this->adminNasional)
            ->put(route('products.update', $subsidi), [
                'category_id' => $this->category->id,
                'sku' => $subsidi->sku,
                'barcode' => $subsidi->barcode,
                'name' => $subsidi->name,
                'unit' => $subsidi->unit,
                'cost_price' => 16000,
                'selling_price' => 18000,
                'member_price' => '',
                'is_active' => true,
            ])
            ->assertRedirect(route('products.index'));

        $this->assertNull($subsidi->fresh()->member_price);
    }

    public function test_pos_barcode_lookup_returns_member_price_and_cross_branch_member_checkout_applies_member_pricing_and_snapshots(): void
    {
        // Verifikasi PosLookupController::productByBarcode mengembalikan member_price (termasuk null)
        $this->actingAs($this->cashierNgunut)
            ->getJson(route('pos.products.barcode', ['barcode' => $this->productWithMemberPrice->barcode]))
            ->assertOk()
            ->assertJsonPath('selling_price', 70000)
            ->assertJsonPath('member_price', 65000);

        $this->actingAs($this->cashierNgunut)
            ->getJson(route('pos.products.barcode', ['barcode' => $this->productWithoutMemberPrice->barcode]))
            ->assertOk()
            ->assertJsonPath('selling_price', 18000)
            ->assertJsonPath('member_price', null);

        // (b & c) Member Cekok (lintas cabang) belanja di Cabang Ngunut:
        // - 2x Beras (normal 70.000, member 65.000 -> regular 140.000, actual 130.000, hemat 10.000)
        // - 3x Gula (normal 18.000, member null -> fallback 18.000 -> regular 54.000, actual 54.000, hemat 0)
        // Total normal = 194.000 | Total hemat member = 10.000 | Total tagihan = 184.000
        $response = $this->actingAs($this->cashierNgunut)
            ->postJson(route('pos.checkout'), [
                'idempotency_key' => (string) Str::uuid(),
                'payment_method' => 'cash',
                'member_id' => $this->memberCekok->id,
                'cash_received' => 200000,
                'items' => [
                    ['product_id' => $this->productWithMemberPrice->id, 'qty' => 2],
                    ['product_id' => $this->productWithoutMemberPrice->id, 'qty' => 3],
                ],
            ])
            ->assertOk();

        $txId = $response->json('transaction_id');
        $transaction = Transaction::with('items')->findOrFail($txId);

        $this->assertSame($this->memberCekok->id, $transaction->member_id);
        $this->assertSame($this->branchCekok->id, $transaction->member_home_branch_id);
        $this->assertSame(194000.0, (float) $transaction->regular_subtotal);
        $this->assertSame(10000.0, (float) $transaction->member_discount_total);
        $this->assertSame(184000.0, (float) $transaction->subtotal);
        $this->assertSame(184000.0, (float) $transaction->total_amount);
        $this->assertSame(16000.0, (float) $transaction->change_amount);

        // Snapshot item dengan harga member
        $berasItem = $transaction->items->firstWhere('product_id', $this->productWithMemberPrice->id);
        $this->assertNotNull($berasItem);
        $this->assertTrue($berasItem->is_member_price_applied);
        $this->assertSame(70000.0, (float) $berasItem->regular_price_snapshot);
        $this->assertSame(65000.0, (float) $berasItem->unit_price_snapshot);
        $this->assertSame(10000.0, (float) $berasItem->member_discount_snapshot);
        $this->assertSame(130000.0, (float) $berasItem->subtotal);

        // Snapshot item tanpa harga member (fallback otomatis ke selling_price)
        $gulaItem = $transaction->items->firstWhere('product_id', $this->productWithoutMemberPrice->id);
        $this->assertNotNull($gulaItem);
        $this->assertFalse($gulaItem->is_member_price_applied);
        $this->assertSame(18000.0, (float) $gulaItem->regular_price_snapshot);
        $this->assertSame(18000.0, (float) $gulaItem->unit_price_snapshot);
        $this->assertSame(0.0, (float) $gulaItem->member_discount_snapshot);
        $this->assertSame(54000.0, (float) $gulaItem->subtotal);

        // Verifikasi response JSON menyertakan field struk thermal untuk Total Hemat Member
        $response->assertJsonPath('transaction.regular_subtotal', 194000)
            ->assertJsonPath('transaction.member_discount_total', 10000)
            ->assertJsonPath('transaction.total_amount', 184000);
    }

    public function test_non_member_checkout_always_uses_selling_price_even_when_member_price_exists(): void
    {
        // (d) Pembeli umum (member_id = null) membeli 2x Beras (normal 70.000, member 65.000) -> tetap 140.000
        $response = $this->actingAs($this->cashierNgunut)
            ->postJson(route('pos.checkout'), [
                'idempotency_key' => (string) Str::uuid(),
                'payment_method' => 'cash',
                'member_id' => null,
                'cash_received' => 150000,
                'items' => [
                    ['product_id' => $this->productWithMemberPrice->id, 'qty' => 2],
                ],
            ])
            ->assertOk();

        $transaction = Transaction::with('items')->findOrFail($response->json('transaction_id'));

        $this->assertNull($transaction->member_id);
        $this->assertSame(140000.0, (float) $transaction->regular_subtotal);
        $this->assertSame(0.0, (float) $transaction->member_discount_total);
        $this->assertSame(140000.0, (float) $transaction->total_amount);
        $this->assertSame(10000.0, (float) $transaction->change_amount);

        $item = $transaction->items->firstOrFail();
        $this->assertFalse($item->is_member_price_applied);
        $this->assertSame(70000.0, (float) $item->regular_price_snapshot);
        $this->assertSame(70000.0, (float) $item->unit_price_snapshot);
        $this->assertSame(0.0, (float) $item->member_discount_snapshot);
    }

    public function test_updating_product_member_price_after_checkout_does_not_alter_historical_transaction_snapshots(): void
    {
        // (e) Checkout member dengan harga awal: normal 70.000, member 65.000, qty 2 -> total 130.000, hemat 10.000
        $response = $this->actingAs($this->cashierNgunut)
            ->postJson(route('pos.checkout'), [
                'idempotency_key' => (string) Str::uuid(),
                'payment_method' => 'cash',
                'member_id' => $this->memberCekok->id,
                'cash_received' => 130000,
                'items' => [
                    ['product_id' => $this->productWithMemberPrice->id, 'qty' => 2],
                ],
            ])
            ->assertOk();

        $txId = $response->json('transaction_id');

        // Admin Nasional mengubah selling_price & member_price secara drastis setelah transaksi selesai
        $this->actingAs($this->adminNasional)
            ->put(route('products.update', $this->productWithMemberPrice), [
                'category_id' => $this->category->id,
                'sku' => $this->productWithMemberPrice->sku,
                'barcode' => $this->productWithMemberPrice->barcode,
                'name' => $this->productWithMemberPrice->name,
                'unit' => $this->productWithMemberPrice->unit,
                'cost_price' => 60000,
                'selling_price' => 85000,
                'member_price' => 80000,
                'is_active' => true,
            ])
            ->assertRedirect(route('products.index'));

        // Verifikasi snapshot transaksi & item historis tetap utuh tidak berubah
        $historicalTx = Transaction::with('items')->findOrFail($txId);
        $this->assertSame(140000.0, (float) $historicalTx->regular_subtotal);
        $this->assertSame(10000.0, (float) $historicalTx->member_discount_total);
        $this->assertSame(130000.0, (float) $historicalTx->total_amount);

        $historicalItem = $historicalTx->items->firstOrFail();
        $this->assertTrue($historicalItem->is_member_price_applied);
        $this->assertSame(70000.0, (float) $historicalItem->regular_price_snapshot);
        $this->assertSame(65000.0, (float) $historicalItem->unit_price_snapshot);
        $this->assertSame(10000.0, (float) $historicalItem->member_discount_snapshot);
        $this->assertSame(130000.0, (float) $historicalItem->subtotal);
    }
}
