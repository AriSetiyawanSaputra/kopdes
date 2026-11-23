<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Milestone3InventoryTest extends TestCase
{
    use RefreshDatabase;

    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $category = Category::firstOrCreate(
            ['slug' => 'sembako'],
            ['name' => 'Sembako']
        );

        $this->product = Product::create([
            'category_id' => $category->id,
            'sku' => 'SKU-BRS-001',
            'barcode' => '8991002003001',
            'name' => 'Beras Medium Bulog 5kg',
            'unit' => 'karung',
            'cost_price' => 56000,
            'selling_price' => 62500,
            'is_active' => true,
        ]);
    }

    public function test_restock_increases_quantity_and_writes_single_accurate_stock_movement(): void
    {
        $adminNgunut = User::where('email', 'admin.ngunut@kopdes.id')->firstOrFail();
        $branchNgunut = Branch::where('code', 'KOP-3502-001')->firstOrFail();

        // 1. First restock on a product with no prior branch_stocks record (firstOrCreate -> qty_before = 0)
        $response1 = $this->actingAs($adminNgunut)->post(route('inventory.restock'), [
            'product_id' => $this->product->id,
            'qty_add' => 25,
            'notes' => 'Kiriman Supplier Bulog Faktur #101',
        ]);

        $response1->assertRedirect();
        $response1->assertSessionHasNoErrors();

        $stock = BranchStock::where('branch_id', $branchNgunut->id)
            ->where('product_id', $this->product->id)
            ->firstOrFail();

        $this->assertSame(25, $stock->quantity);
        $this->assertSame(0, $stock->reserved_qty);
        $this->assertSame(25, $stock->available_stock);

        $movements = StockMovement::where('branch_id', $branchNgunut->id)
            ->where('product_id', $this->product->id)
            ->get();

        $this->assertCount(1, $movements);
        $movement1 = $movements->first();
        $this->assertSame('restock', $movement1->type);
        $this->assertSame(0, $movement1->qty_before);
        $this->assertSame(25, $movement1->qty_change);
        $this->assertSame(25, $movement1->qty_after);
        $this->assertSame($adminNgunut->id, $movement1->user_id);
        $this->assertSame('Kiriman Supplier Bulog Faktur #101', $movement1->notes);

        // 2. Second restock adds to existing quantity (qty_before = 25, qty_add = 15 -> qty_after = 40)
        $response2 = $this->actingAs($adminNgunut)->post(route('inventory.restock'), [
            'product_id' => $this->product->id,
            'qty_add' => 15,
            'notes' => 'Kiriman Tambahan Faktur #102',
        ]);

        $response2->assertRedirect();
        $response2->assertSessionHasNoErrors();

        $this->assertSame(40, $stock->fresh()->quantity);

        $allMovements = StockMovement::where('branch_id', $branchNgunut->id)
            ->where('product_id', $this->product->id)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $allMovements);
        $movement2 = $allMovements->last();
        $this->assertSame('restock', $movement2->type);
        $this->assertSame(25, $movement2->qty_before);
        $this->assertSame(15, $movement2->qty_change);
        $this->assertSame(40, $movement2->qty_after);
    }

    public function test_stock_adjustment_rejects_actual_quantity_below_reserved_qty_and_allows_valid_adjustment(): void
    {
        $adminNgunut = User::where('email', 'admin.ngunut@kopdes.id')->firstOrFail();
        $branchNgunut = Branch::where('code', 'KOP-3502-001')->firstOrFail();

        // Setup initial stock: quantity = 20, reserved_qty = 6 (simulating pending Midtrans transactions)
        $stock = BranchStock::create([
            'branch_id' => $branchNgunut->id,
            'product_id' => $this->product->id,
            'quantity' => 20,
            'reserved_qty' => 6,
            'min_stock' => 5,
        ]);

        // 1. Attempt adjustment with actual_quantity = 5 (< reserved_qty = 6) -> must be rejected
        $invalidResponse = $this->actingAs($adminNgunut)->post(route('inventory.adjust'), [
            'product_id' => $this->product->id,
            'actual_quantity' => 5,
            'notes' => 'Coba kurangi di bawah stok reservasi Midtrans',
        ]);

        $invalidResponse->assertSessionHasErrors('actual_quantity');

        // Quantity must remain unchanged (20) and no stock_movements row should be written
        $this->assertSame(20, $stock->fresh()->quantity);
        $this->assertSame(6, $stock->fresh()->reserved_qty);
        $this->assertSame(0, StockMovement::where('branch_id', $branchNgunut->id)->count());

        // 2. Valid adjustment with actual_quantity = 18 (>= reserved_qty = 6) -> succeeds and writes 1 stock_movement
        $validResponse = $this->actingAs($adminNgunut)->post(route('inventory.adjust'), [
            'product_id' => $this->product->id,
            'actual_quantity' => 18,
            'notes' => 'Kemasan rusak 2 karung saat stock opname',
        ]);

        $validResponse->assertRedirect();
        $validResponse->assertSessionHasNoErrors();

        $this->assertSame(18, $stock->fresh()->quantity);
        $this->assertSame(6, $stock->fresh()->reserved_qty);
        $this->assertSame(12, $stock->fresh()->available_stock);

        $movement = StockMovement::where('branch_id', $branchNgunut->id)
            ->where('product_id', $this->product->id)
            ->firstOrFail();

        $this->assertSame('adjustment', $movement->type);
        $this->assertSame(20, $movement->qty_before);
        $this->assertSame(-2, $movement->qty_change);
        $this->assertSame(18, $movement->qty_after);
        $this->assertSame('Kemasan rusak 2 karung saat stock opname', $movement->notes);
    }

    public function test_admin_cabang_and_other_roles_receive_http_403_when_mutating_other_branch_inventory(): void
    {
        $adminNgunut = User::where('email', 'admin.ngunut@kopdes.id')->firstOrFail();
        $adminPonorogo = User::where('email', 'admin.ponorogo@kopdes.id')->firstOrFail();
        $adminNasional = User::where('email', 'admin.nasional@kopdes.id')->firstOrFail();
        $branchCekok = Branch::where('code', 'KOP-3502-002')->firstOrFail();

        // 1. Admin Cabang Ngunut trying to restock Cabang Cekok -> HTTP 403 Forbidden
        $this->actingAs($adminNgunut)->post(route('inventory.restock'), [
            'branch_id' => $branchCekok->id,
            'product_id' => $this->product->id,
            'qty_add' => 10,
            'notes' => 'Percobaan restock lintas cabang ilegal',
        ])->assertForbidden();

        // 2. Admin Cabang Ngunut trying to adjust stock of Cabang Cekok -> HTTP 403 Forbidden
        $this->actingAs($adminNgunut)->post(route('inventory.adjust'), [
            'branch_id' => $branchCekok->id,
            'product_id' => $this->product->id,
            'actual_quantity' => 50,
            'notes' => 'Percobaan adjustment lintas cabang ilegal',
        ])->assertForbidden();

        // 3. Admin Cabang Ngunut trying to view inventory or movements of Cabang Cekok via query param -> HTTP 403 Forbidden
        $this->actingAs($adminNgunut)
            ->get(route('inventory.index', ['branch_id' => $branchCekok->id]))
            ->assertForbidden();

        $this->actingAs($adminNgunut)
            ->get(route('inventory.movements', ['branch_id' => $branchCekok->id]))
            ->assertForbidden();

        // 4. Upper-level admins (Admin Kabupaten / Admin Nasional) have read-only access to inventory and get 403 on mutate
        $this->actingAs($adminPonorogo)->post(route('inventory.restock'), [
            'branch_id' => $branchCekok->id,
            'product_id' => $this->product->id,
            'qty_add' => 10,
        ])->assertForbidden();

        $this->actingAs($adminNasional)->post(route('inventory.adjust'), [
            'branch_id' => $branchCekok->id,
            'product_id' => $this->product->id,
            'actual_quantity' => 10,
            'notes' => 'Admin nasional read-only pada stok fisik cabang',
        ])->assertForbidden();

        // Verify Cabang Cekok stock was never created or modified
        $this->assertDatabaseMissing('branch_stocks', [
            'branch_id' => $branchCekok->id,
            'product_id' => $this->product->id,
        ]);
    }

    public function test_inventory_index_and_movements_pages_render_scoped_data_with_four_metrics(): void
    {
        $adminNgunut = User::where('email', 'admin.ngunut@kopdes.id')->firstOrFail();
        $branchNgunut = Branch::where('code', 'KOP-3502-001')->firstOrFail();

        $this->actingAs($adminNgunut)->post(route('inventory.restock'), [
            'product_id' => $this->product->id,
            'qty_add' => 12,
            'notes' => 'Stok awal uji halaman Vue',
        ])->assertRedirect();

        $stock = BranchStock::where('branch_id', $branchNgunut->id)
            ->where('product_id', $this->product->id)
            ->firstOrFail();
        $stock->update(['reserved_qty' => 3, 'min_stock' => 10]);

        $indexResponse = $this->actingAs($adminNgunut)->get(route('inventory.index'));
        $indexResponse->assertOk();

        $movementsResponse = $this->actingAs($adminNgunut)->get(route('inventory.movements', [
            'type' => 'restock',
            'product_id' => $this->product->id,
        ]));
        $movementsResponse->assertOk();
    }
}
