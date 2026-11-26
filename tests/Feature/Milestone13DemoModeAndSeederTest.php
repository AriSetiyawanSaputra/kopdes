<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\AppSetting;
use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\CashierShift;
use App\Models\Category;
use App\Models\Member;
use App\Models\OperationalExpense;
use App\Models\Product;
use App\Models\Province;
use App\Models\Regency;
use App\Models\Transaction;
use App\Models\TransactionReturn;
use App\Models\TransactionReturnItem;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Milestone13DemoModeAndSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_when_demo_mode_is_false_pos_samples_returns_404_and_shared_prop_is_false(): void
    {
        $this->seed(DatabaseSeeder::class);

        config(['kopdes.demo_mode' => false]);

        $cashier = User::where('card_uid', '0008493873')->firstOrFail();

        // Shared Inertia prop app.demo_mode MUST be false on all 3 login pages
        $this->get(route('login'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Login')
                ->where('app.demo_mode', false)
            );

        $this->get(route('pos.login'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Pos/Login')
                ->where('app.demo_mode', false)
            );

        $this->get(route('member.login'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Member/Login')
                ->where('app.demo_mode', false)
            );

        // Endpoint /demo/pos-samples MUST return HTTP 404 when demo_mode = false
        $this->actingAs($cashier, 'web')
            ->getJson(route('demo.pos-samples'))
            ->assertNotFound();
    }

    public function test_when_demo_mode_is_true_pos_samples_returns_cashier_branch_products_and_members(): void
    {
        $this->seed(DatabaseSeeder::class);

        config(['kopdes.demo_mode' => true]);

        $branchNgunut = Branch::where('code', 'KOP-3502-001')->firstOrFail();
        $cashierNgunut = User::where('card_uid', '0008493873')->firstOrFail();
        $adminNasional = User::where('email', 'admin.nasional@kopdes.id')->firstOrFail();

        $cat = Category::firstOrCreate(['slug' => 'demo-cat'], ['name' => 'Demo Cat']);

        $prodWithMember = Product::create([
            'category_id' => $cat->id,
            'sku' => 'DEMO-P-01',
            'barcode' => '8991300130011',
            'name' => 'Beras Demo Member',
            'unit' => 'sak',
            'cost_price' => 50000,
            'selling_price' => 70000,
            'member_price' => 65000,
            'is_active' => true,
        ]);

        $prodRegular = Product::create([
            'category_id' => $cat->id,
            'sku' => 'DEMO-P-02',
            'barcode' => '8991300130028',
            'name' => 'Keripik Demo Reguler',
            'unit' => 'pack',
            'cost_price' => 8000,
            'selling_price' => 12000,
            'member_price' => null,
            'is_active' => true,
        ]);

        BranchStock::create([
            'branch_id' => $branchNgunut->id,
            'product_id' => $prodWithMember->id,
            'quantity' => 20,
            'reserved_qty' => 0,
            'min_stock' => 5,
        ]);

        BranchStock::create([
            'branch_id' => $branchNgunut->id,
            'product_id' => $prodRegular->id,
            'quantity' => 15,
            'reserved_qty' => 0,
            'min_stock' => 5,
        ]);

        // Shared prop is true on login pages
        $this->get(route('login'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('app.demo_mode', true));

        // Non-cashier (admin) is still rejected with HTTP 403 by role:kasir
        $this->actingAs($adminNasional, 'web')
            ->getJson(route('demo.pos-samples'))
            ->assertForbidden();

        // Cashier receives HTTP 200 with stocked products & home/cross-branch RFID members
        $res = $this->actingAs($cashierNgunut, 'web')
            ->getJson(route('demo.pos-samples'));

        $res->assertOk()
            ->assertJsonStructure([
                'products' => [
                    '*' => ['id', 'sku', 'barcode', 'name', 'unit', 'selling_price', 'member_price', 'available_stock'],
                ],
                'members' => [
                    '*' => ['id', 'member_code', 'name', 'card_uid', 'home_branch_id', 'home_branch_name', 'is_cross_branch'],
                ],
            ]);

        $products = $res->json('products');
        $members = $res->json('members');

        $this->assertCount(2, $products);
        $this->assertTrue(collect($products)->contains(fn ($p) => $p['member_price'] !== null));
        $this->assertTrue(collect($products)->contains(fn ($p) => $p['member_price'] === null));

        $this->assertGreaterThanOrEqual(2, count($members));
        $this->assertTrue(collect($members)->contains(fn ($m) => $m['is_cross_branch'] === false));
        $this->assertTrue(collect($members)->contains(fn ($m) => $m['is_cross_branch'] === true));
    }

    public function test_demo_data_seeder_is_idempotent_fast_and_generates_complete_3_month_ecosystem(): void
    {
        $start = microtime(true);

        // Run DemoDataSeeder TWICE consecutively to verify strict idempotency
        $this->seed(DemoDataSeeder::class);

        $firstCounts = [
            'provinces' => Province::count(),
            'regencies' => Regency::count(),
            'branches' => Branch::where('is_active', true)->count(),
            'products' => Product::count(),
            'members' => Member::count(),
            'transactions' => Transaction::count(),
            'shifts' => CashierShift::count(),
            'returns' => TransactionReturn::count(),
            'expenses' => OperationalExpense::count(),
            'activity_logs' => ActivityLog::count(),
        ];

        $this->seed(DemoDataSeeder::class);

        $elapsed = microtime(true) - $start;

        $secondCounts = [
            'provinces' => Province::count(),
            'regencies' => Regency::count(),
            'branches' => Branch::where('is_active', true)->count(),
            'products' => Product::count(),
            'members' => Member::count(),
            'transactions' => Transaction::count(),
            'shifts' => CashierShift::count(),
            'returns' => TransactionReturn::count(),
            'expenses' => OperationalExpense::count(),
            'activity_logs' => ActivityLog::count(),
        ];

        // 1. Strictly idempotent across consecutive runs
        $this->assertSame($firstCounts, $secondCounts);

        // 2. Fast execution (< 15 seconds even for 2 consecutive runs)
        $this->assertLessThan(15.0, $elapsed);

        // 3. Verify all PRD FR-P3-14 quantitative & qualitative dataset benchmarks
        $this->assertGreaterThanOrEqual(2, $secondCounts['provinces']);
        $this->assertGreaterThanOrEqual(3, $secondCounts['regencies']);
        $this->assertGreaterThanOrEqual(4, $secondCounts['branches']);
        $this->assertGreaterThanOrEqual(15, $secondCounts['products']);
        $this->assertGreaterThan(0, Product::whereNotNull('member_price')->count());
        $this->assertGreaterThan(0, Product::whereNull('member_price')->count());

        $this->assertGreaterThanOrEqual(10, $secondCounts['members']);
        $this->assertGreaterThan(0, Member::whereNotNull('card_uid')->whereNotNull('pin_hash')->count());
        $this->assertGreaterThan(0, Member::whereNull('card_uid')->whereNotNull('pin_hash')->count());

        $this->assertGreaterThanOrEqual(60, $secondCounts['transactions']);
        $this->assertGreaterThan(0, Transaction::where('payment_method', 'cash')->count());
        $this->assertGreaterThan(0, Transaction::where('payment_method', 'midtrans')->count());
        $this->assertGreaterThan(0, Transaction::whereNotNull('member_id')->whereColumn('branch_id', '!=', 'member_home_branch_id')->count());
        $this->assertGreaterThan(0, Transaction::whereNull('member_id')->count());

        // Shifts: balanced (=0), surplus (>0), and minus (<0)
        $this->assertGreaterThan(0, CashierShift::where('status', CashierShift::STATUS_CLOSED)->where('variance', 0)->count());
        $this->assertGreaterThan(0, CashierShift::where('status', CashierShift::STATUS_CLOSED)->where('variance', '>', 0)->count());
        $this->assertGreaterThan(0, CashierShift::where('status', CashierShift::STATUS_CLOSED)->where('variance', '<', 0)->count());

        // Returns: restockable & damaged
        $this->assertGreaterThan(0, TransactionReturnItem::where('condition', TransactionReturnItem::CONDITION_RESTOCKABLE)->count());
        $this->assertGreaterThan(0, TransactionReturnItem::where('condition', TransactionReturnItem::CONDITION_DAMAGED)->count());

        // Operational expenses, app_settings & activity_logs
        $this->assertGreaterThanOrEqual(12, $secondCounts['expenses']);
        $this->assertGreaterThanOrEqual(8, AppSetting::count());
        $this->assertGreaterThanOrEqual(5, $secondCounts['activity_logs']);
    }
}
