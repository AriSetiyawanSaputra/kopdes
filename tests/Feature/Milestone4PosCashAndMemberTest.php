<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\CashierShift;
use App\Models\Category;
use App\Models\Member;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Milestone4PosCashAndMemberTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branchNgunut;

    protected Branch $branchCekok;

    protected User $cashierNgunut;

    protected Product $productBeras;

    protected Product $productMinyak;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->branchNgunut = Branch::where('code', 'KOP-3502-001')->firstOrFail();
        $this->branchCekok = Branch::where('code', 'KOP-3502-002')->firstOrFail();
        $this->cashierNgunut = User::where('card_uid', '0008493873')->firstOrFail();

        CashierShift::create([
            'shift_code' => 'SHF-KOP-3502-001-TEST-001',
            'branch_id' => $this->branchNgunut->id,
            'cashier_id' => $this->cashierNgunut->id,
            'status' => CashierShift::STATUS_OPEN,
            'opening_cash' => 100000,
            'opened_at' => now(),
        ]);

        $category = Category::firstOrCreate(
            ['slug' => 'sembako'],
            ['name' => 'Sembako']
        );

        $this->productBeras = Product::create([
            'category_id' => $category->id,
            'sku' => 'SKU-POS-BRS01',
            'barcode' => '8997001001001',
            'name' => 'Beras Kepala Merah Putih 5kg',
            'unit' => 'karung',
            'cost_price' => 55000,
            'selling_price' => 62000,
            'is_active' => true,
        ]);

        $this->productMinyak = Product::create([
            'category_id' => $category->id,
            'sku' => 'SKU-POS-MNY01',
            'barcode' => '8997001001002',
            'name' => 'Minyak Goreng KOPDES 1L',
            'unit' => 'pch',
            'cost_price' => 15000,
            'selling_price' => 18000,
            'is_active' => true,
        ]);

        BranchStock::create([
            'branch_id' => $this->branchNgunut->id,
            'product_id' => $this->productBeras->id,
            'quantity' => 20,
            'reserved_qty' => 0,
            'min_stock' => 5,
        ]);

        BranchStock::create([
            'branch_id' => $this->branchNgunut->id,
            'product_id' => $this->productMinyak->id,
            'quantity' => 15,
            'reserved_qty' => 0,
            'min_stock' => 5,
        ]);
    }

    public function test_cash_checkout_deducts_quantity_calculates_change_populates_hpp_snapshots_and_writes_stock_movements(): void
    {
        $idempotencyKey = (string) Str::uuid();

        // Expected total_amount = (2 * 62000) + (3 * 18000) = 124000 + 54000 = 178000
        // Expected total_cogs = (2 * 55000) + (3 * 15000) = 110000 + 45000 = 155000
        // cash_received = 200000 -> change_amount = 22000
        $response = $this->actingAs($this->cashierNgunut)
            ->postJson(route('pos.checkout'), [
                'idempotency_key' => $idempotencyKey,
                'payment_method' => 'cash',
                'member_id' => null,
                'cash_received' => 200000,
                'items' => [
                    ['product_id' => $this->productMinyak->id, 'qty' => 3],
                    ['product_id' => $this->productBeras->id, 'qty' => 2],
                ],
            ]);

        $response->assertOk();

        $transaction = Transaction::where('idempotency_key', $idempotencyKey)
            ->with('items')
            ->firstOrFail();

        $this->assertSame('paid', $transaction->payment_status);
        $this->assertSame('cash', $transaction->payment_method);
        $this->assertNotNull($transaction->paid_at);
        $this->assertSame(178000.0, (float) $transaction->total_amount);
        $this->assertSame(155000.0, (float) $transaction->total_cogs);
        $this->assertSame(200000.0, (float) $transaction->cash_received);
        $this->assertSame(22000.0, (float) $transaction->change_amount);
        $this->assertStringStartsWith("INV-{$this->branchNgunut->code}-", $transaction->invoice_number);

        // Verify transaction_items snapshots
        $this->assertCount(2, $transaction->items);
        $berasItem = $transaction->items->firstWhere('product_id', $this->productBeras->id);
        $this->assertNotNull($berasItem);
        $this->assertSame('Beras Kepala Merah Putih 5kg', $berasItem->product_name_snapshot);
        $this->assertSame('8997001001001', $berasItem->barcode_snapshot);
        $this->assertSame('karung', $berasItem->unit_snapshot);
        $this->assertSame(2, $berasItem->qty);
        $this->assertSame(62000.0, (float) $berasItem->unit_price_snapshot);
        $this->assertSame(55000.0, (float) $berasItem->cost_price_snapshot);
        $this->assertSame(124000.0, (float) $berasItem->subtotal);
        $this->assertSame(110000.0, (float) $berasItem->subtotal_cogs);

        // Verify branch_stocks quantities were deducted
        $stockBeras = BranchStock::where('branch_id', $this->branchNgunut->id)
            ->where('product_id', $this->productBeras->id)
            ->firstOrFail();
        $stockMinyak = BranchStock::where('branch_id', $this->branchNgunut->id)
            ->where('product_id', $this->productMinyak->id)
            ->firstOrFail();

        $this->assertSame(18, $stockBeras->quantity);
        $this->assertSame(12, $stockMinyak->quantity);

        // Verify stock_movements append-only entries
        $berasMovement = StockMovement::where('branch_id', $this->branchNgunut->id)
            ->where('product_id', $this->productBeras->id)
            ->where('type', 'sale')
            ->firstOrFail();
        $this->assertSame(-2, $berasMovement->qty_change);
        $this->assertSame(20, $berasMovement->qty_before);
        $this->assertSame(18, $berasMovement->qty_after);
        $this->assertSame(Transaction::class, $berasMovement->reference_type);
        $this->assertSame($transaction->id, $berasMovement->reference_id);
    }

    public function test_duplicate_checkout_with_same_idempotency_key_does_not_create_duplicate_transaction_or_double_deduct_stock(): void
    {
        $idempotencyKey = (string) Str::uuid();

        $payload = [
            'idempotency_key' => $idempotencyKey,
            'payment_method' => 'cash',
            'member_id' => null,
            'cash_received' => 70000,
            'items' => [
                ['product_id' => $this->productBeras->id, 'qty' => 1],
            ],
        ];

        $firstResponse = $this->actingAs($this->cashierNgunut)
            ->postJson(route('pos.checkout'), $payload);
        $firstResponse->assertOk();

        $secondResponse = $this->actingAs($this->cashierNgunut)
            ->postJson(route('pos.checkout'), $payload);
        $secondResponse->assertOk();

        // Both responses return the exact same transaction ID and invoice_number
        $this->assertSame(
            $firstResponse->json('invoice_number'),
            $secondResponse->json('invoice_number')
        );
        $this->assertSame(
            $firstResponse->json('transaction_id'),
            $secondResponse->json('transaction_id')
        );

        // Only 1 transaction and 1 stock_movement exist, and stock was deducted only once (20 - 1 = 19)
        $this->assertSame(1, Transaction::where('idempotency_key', $idempotencyKey)->count());
        $this->assertSame(1, StockMovement::where('branch_id', $this->branchNgunut->id)->where('type', 'sale')->count());

        $stockBeras = BranchStock::where('branch_id', $this->branchNgunut->id)
            ->where('product_id', $this->productBeras->id)
            ->firstOrFail();
        $this->assertSame(19, $stockBeras->quantity);
    }

    public function test_cross_branch_member_lookup_and_checkout_records_branch_id_distinct_from_member_home_branch_id(): void
    {
        // Member Cintya Bella is registered at KOPDES Desa Cekok (branchCekok), shopping at KOPDES Desa Ngunut (branchNgunut)
        $memberCekok = Member::where('member_code', 'MBR-KOP-3502-002-00001')->firstOrFail();
        $this->assertSame($this->branchCekok->id, $memberCekok->home_branch_id);
        $this->assertNotSame($this->branchNgunut->id, $memberCekok->home_branch_id);

        // 1. Lookup member at POS Ngunut using unnormalized RFID UID
        $lookupResponse = $this->actingAs($this->cashierNgunut)
            ->getJson(route('pos.members.lookup', ['card_uid' => '  0009-4341:79  ']));

        $lookupResponse->assertOk()
            ->assertExactJson([
                'id' => $memberCekok->id,
                'member_code' => 'MBR-KOP-3502-002-00001',
                'name' => 'Cintya Bella',
                'home_branch_id' => $this->branchCekok->id,
                'home_branch_name' => 'KOPDES Desa Cekok',
            ]);

        // Ensure sensitive fields (nik, phone) are never exposed by POS lookup
        $this->assertArrayNotHasKey('nik', $lookupResponse->json());
        $this->assertArrayNotHasKey('phone', $lookupResponse->json());

        // 2. Checkout at KOPDES Desa Ngunut for Member from KOPDES Desa Cekok
        $idempotencyKey = (string) Str::uuid();
        $checkoutResponse = $this->actingAs($this->cashierNgunut)
            ->postJson(route('pos.checkout'), [
                'idempotency_key' => $idempotencyKey,
                'payment_method' => 'cash',
                'member_id' => $memberCekok->id,
                'cash_received' => 100000,
                'items' => [
                    ['product_id' => $this->productBeras->id, 'qty' => 1],
                ],
            ]);

        $checkoutResponse->assertOk();

        $transaction = Transaction::where('idempotency_key', $idempotencyKey)->firstOrFail();
        $this->assertSame($this->branchNgunut->id, $transaction->branch_id);
        $this->assertSame($memberCekok->id, $transaction->member_id);
        $this->assertSame($this->branchCekok->id, $transaction->member_home_branch_id);
        $this->assertNotSame($transaction->branch_id, $transaction->member_home_branch_id);

        // 3. Inactive member card is rejected with "Kartu member tidak aktif."
        $memberCekok->update(['status' => 'inactive']);
        $this->actingAs($this->cashierNgunut)
            ->getJson(route('pos.members.lookup', ['card_uid' => '0009434179']))
            ->assertStatus(422)
            ->assertJson(['message' => 'Kartu member tidak aktif.']);
    }

    public function test_checkout_and_lookup_are_rejected_with_http_403_when_pos_session_is_locked(): void
    {
        // Lock the POS session
        $this->actingAs($this->cashierNgunut)
            ->postJson(route('pos.lock'))
            ->assertOk()
            ->assertJson(['pos_locked' => true]);

        // Barcode lookup while locked -> 403 Forbidden
        $this->actingAs($this->cashierNgunut)
            ->withSession(['pos_locked' => true])
            ->getJson(route('pos.products.barcode', ['barcode' => $this->productBeras->barcode]))
            ->assertForbidden();

        // Checkout while locked -> 403 Forbidden
        $this->actingAs($this->cashierNgunut)
            ->withSession(['pos_locked' => true])
            ->postJson(route('pos.checkout'), [
                'idempotency_key' => (string) Str::uuid(),
                'payment_method' => 'cash',
                'member_id' => null,
                'cash_received' => 100000,
                'items' => [
                    ['product_id' => $this->productBeras->id, 'qty' => 1],
                ],
            ])
            ->assertForbidden();

        $this->assertSame(0, Transaction::count());
    }
}
