<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Member;
use App\Models\OperationalExpense;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\TransactionReturn;
use App\Models\TransactionReturnItem;
use App\Models\User;
use App\Services\AppSettingService;
use App\Services\MemberPortalService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Milestone12MemberPortalTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branchNgunut;

    protected Branch $branchCekok;

    protected User $adminNasional;

    protected User $adminNgunut;

    protected User $adminCekok;

    protected User $cashierNgunut;

    protected Member $memberNgunut;

    protected Member $memberCekok;

    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->branchNgunut = Branch::where('code', 'KOP-3502-001')->firstOrFail();
        $this->branchCekok = Branch::where('code', 'KOP-3502-002')->firstOrFail();

        $this->adminNasional = User::where('email', 'admin.nasional@kopdes.id')->firstOrFail();
        $this->adminNgunut = User::where('email', 'admin.ngunut@kopdes.id')->firstOrFail();
        $this->adminCekok = User::where('email', 'admin.cekok@kopdes.id')->firstOrFail();
        $this->cashierNgunut = User::where('card_uid', '0008493873')->firstOrFail();

        $this->memberNgunut = Member::where('home_branch_id', $this->branchNgunut->id)->firstOrFail();
        $this->memberCekok = Member::where('home_branch_id', $this->branchCekok->id)->firstOrFail();

        $this->memberNgunut->update([
            'phone' => '081234567890',
            'pin_hash' => Hash::make('123456'),
            'status' => 'active',
        ]);

        $this->memberCekok->update([
            'phone' => '081298765432',
            'pin_hash' => Hash::make('654321'),
            'status' => 'active',
        ]);

        $category = Category::firstOrCreate(
            ['slug' => 'sembako-m12'],
            ['name' => 'Sembako M12']
        );

        $this->product = Product::create([
            'category_id' => $category->id,
            'sku' => 'SKU-M12-001',
            'barcode' => '8991200120012',
            'name' => 'Beras Super 5kg',
            'unit' => 'sak',
            'cost_price' => 50000,
            'selling_price' => 75000,
            'member_price' => 70000,
            'is_active' => true,
        ]);
    }

    public function test_member_can_login_with_member_code_or_phone_and_shared_props_hide_sensitive_fields(): void
    {
        // (a) Login via member_code + 6-digit PIN
        $this->post(route('member.login.store'), [
            'identifier' => $this->memberNgunut->member_code,
            'pin' => '123456',
        ])->assertRedirect(route('member.dashboard', absolute: false));

        $this->assertAuthenticatedAs($this->memberNgunut, 'member');
        $this->assertGuest('web');

        // Verify shared Inertia props expose auth.member safely without pin_hash or nik
        $this->actingAs($this->memberNgunut, 'member')
            ->get(route('member.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Member/Dashboard')
                ->where('auth.user', null)
                ->where('auth.member.id', $this->memberNgunut->id)
                ->where('auth.member.member_code', $this->memberNgunut->member_code)
                ->missing('auth.member.pin_hash')
                ->missing('auth.member.nik')
                ->missing('auth.member.remember_token')
            );

        // Logout member guard
        $this->actingAs($this->memberNgunut, 'member')
            ->post(route('member.logout'))
            ->assertRedirect(route('member.login'));

        $this->assertGuest('member');

        // (a) Login via phone + 6-digit PIN
        $this->post(route('member.login.store'), [
            'identifier' => '081234567890',
            'pin' => '123456',
        ])->assertRedirect(route('member.dashboard', absolute: false));

        $this->assertAuthenticatedAs($this->memberNgunut, 'member');
    }

    public function test_member_login_rate_limiter_returns_429_on_sixth_attempt(): void
    {
        RateLimiter::clear('member-pin:'.strtolower($this->memberNgunut->member_code).'|127.0.0.1');

        // 5 failed attempts -> 422 (JSON) / 302 (web)
        for ($i = 1; $i <= 5; $i++) {
            $this->postJson(route('member.login.store'), [
                'identifier' => $this->memberNgunut->member_code,
                'pin' => '000000',
            ])->assertStatus(422);
        }

        // 6th attempt -> HTTP 429 Too Many Requests with Retry-After header
        $response = $this->postJson(route('member.login.store'), [
            'identifier' => $this->memberNgunut->member_code,
            'pin' => '123456',
        ]);

        $response->assertStatus(429)
            ->assertHeader('Retry-After')
            ->assertJsonStructure(['message', 'retry_after', 'errors' => ['pin']]);

        $this->assertGuest('member');
    }

    public function test_inactive_member_or_member_without_pin_is_rejected_from_portal_login(): void
    {
        // Member with pin_hash = null
        $noPinMember = Member::create([
            'member_code' => 'MBR-M12-NOPIN',
            'home_branch_id' => $this->branchNgunut->id,
            'card_uid' => null,
            'name' => 'Anggota Tanpa PIN',
            'phone' => '081111222333',
            'status' => 'active',
            'pin_hash' => null,
            'joined_at' => now()->toDateString(),
        ]);

        $this->postJson(route('member.login.store'), [
            'identifier' => $noPinMember->member_code,
            'pin' => '123456',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['identifier']);

        $this->assertGuest('member');

        // Inactive member even with valid pin_hash
        $inactiveMember = Member::create([
            'member_code' => 'MBR-M12-INACT',
            'home_branch_id' => $this->branchNgunut->id,
            'card_uid' => null,
            'name' => 'Anggota Nonaktif',
            'phone' => '081111222444',
            'status' => 'inactive',
            'pin_hash' => Hash::make('123456'),
            'joined_at' => now()->toDateString(),
        ]);

        $this->postJson(route('member.login.store'), [
            'identifier' => $inactiveMember->member_code,
            'pin' => '123456',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['identifier']);

        $this->assertGuest('member');
    }

    public function test_member_guard_is_strictly_isolated_from_admin_and_pos_routes(): void
    {
        // Guest visiting /member is redirected to /member/login
        $this->get(route('member.dashboard'))
            ->assertRedirect(route('member.login'));

        // Authenticated member cannot access /dashboard or /pos (web guard redirects to /login)
        $this->actingAs($this->memberNgunut, 'member')
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));

        $this->actingAs($this->memberNgunut, 'member')
            ->get(route('pos.index'))
            ->assertRedirect(route('login'));

        // Authenticated member visiting /member/login is redirected to /member
        $this->actingAs($this->memberNgunut, 'member')
            ->get(route('member.login'))
            ->assertRedirect(route('member.dashboard'));
    }

    public function test_member_cannot_access_another_members_transaction_receipt_anti_idor(): void
    {
        $txOwn = Transaction::create([
            'invoice_number' => 'INV-M12-OWN-001',
            'idempotency_key' => (string) Str::uuid(),
            'branch_id' => $this->branchNgunut->id,
            'cashier_id' => $this->cashierNgunut->id,
            'member_id' => $this->memberNgunut->id,
            'member_home_branch_id' => $this->branchNgunut->id,
            'regular_subtotal' => 75000,
            'member_discount_total' => 5000,
            'subtotal' => 70000,
            'total_amount' => 70000,
            'total_cogs' => 50000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'return_status' => 'none',
            'paid_at' => now(),
        ]);

        $txOther = Transaction::create([
            'invoice_number' => 'INV-M12-OTH-001',
            'idempotency_key' => (string) Str::uuid(),
            'branch_id' => $this->branchCekok->id,
            'cashier_id' => $this->cashierNgunut->id,
            'member_id' => $this->memberCekok->id,
            'member_home_branch_id' => $this->branchCekok->id,
            'regular_subtotal' => 75000,
            'member_discount_total' => 5000,
            'subtotal' => 70000,
            'total_amount' => 70000,
            'total_cogs' => 50000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'return_status' => 'none',
            'paid_at' => now(),
        ]);

        $txGuest = Transaction::create([
            'invoice_number' => 'INV-M12-GUEST-001',
            'idempotency_key' => (string) Str::uuid(),
            'branch_id' => $this->branchNgunut->id,
            'cashier_id' => $this->cashierNgunut->id,
            'member_id' => null,
            'member_home_branch_id' => null,
            'regular_subtotal' => 75000,
            'member_discount_total' => 0,
            'subtotal' => 75000,
            'total_amount' => 75000,
            'total_cogs' => 50000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'return_status' => 'none',
            'paid_at' => now(),
        ]);

        // Own transaction -> 200 OK
        $this->actingAs($this->memberNgunut, 'member')
            ->get(route('member.transactions.show', $txOwn))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Member/TransactionShow')
                ->where('transaction.invoice_number', 'INV-M12-OWN-001')
            );

        // Another member's transaction -> HTTP 403 Forbidden
        $this->actingAs($this->memberNgunut, 'member')
            ->get(route('member.transactions.show', $txOther))
            ->assertForbidden();

        // Non-member transaction -> HTTP 403 Forbidden
        $this->actingAs($this->memberNgunut, 'member')
            ->get(route('member.transactions.show', $txGuest))
            ->assertForbidden();
    }

    public function test_member_portal_calculations_for_net_spending_savings_returns_and_shu_including_negative_profit(): void
    {
        // Set SHU allocation member_usaha = 40% (total 100%)
        app(AppSettingService::class)->updateMany([
            'shu_allocation_member_usaha' => 40,
            'shu_allocation_reserve' => 30,
            'shu_allocation_management' => 20,
            'shu_allocation_education' => 10,
        ], $this->adminNasional);

        // Add second member at Ngunut so SHU sharing is proportional (not 100%)
        $memberNgunut2 = Member::create([
            'member_code' => 'MBR-M12-NGU-02',
            'home_branch_id' => $this->branchNgunut->id,
            'card_uid' => null,
            'name' => 'Anggota Kedua Ngunut',
            'status' => 'active',
            'pin_hash' => Hash::make('111222'),
            'joined_at' => now()->toDateString(),
        ]);

        // Tx 1: memberNgunut shops at HOME branch (Ngunut): 3 qty * Rp 70.000 (regular 75.000, discount 5.000/qty = 15.000, cogs 50.000/qty = 150.000)
        $txHome = Transaction::create([
            'invoice_number' => 'INV-M12-CALC-HOME',
            'idempotency_key' => (string) Str::uuid(),
            'branch_id' => $this->branchNgunut->id,
            'cashier_id' => $this->cashierNgunut->id,
            'member_id' => $this->memberNgunut->id,
            'member_home_branch_id' => $this->branchNgunut->id,
            'regular_subtotal' => 225000,
            'member_discount_total' => 15000,
            'subtotal' => 210000,
            'total_amount' => 210000,
            'total_cogs' => 150000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'return_status' => 'partial',
            'paid_at' => now(),
        ]);

        $txHomeItem = TransactionItem::create([
            'transaction_id' => $txHome->id,
            'product_id' => $this->product->id,
            'product_name_snapshot' => $this->product->name,
            'barcode_snapshot' => $this->product->barcode,
            'unit_snapshot' => $this->product->unit,
            'qty' => 3,
            'regular_price_snapshot' => 75000,
            'unit_price_snapshot' => 70000,
            'member_discount_snapshot' => 15000,
            'is_member_price_applied' => true,
            'cost_price_snapshot' => 50000,
            'subtotal' => 210000,
            'subtotal_cogs' => 150000,
        ]);

        // Partial Return of 1 qty (restockable) from Tx 1: refund 70.000, restocked cogs 50.000, returned discount = (75.000 - 70.000) * 1 = 5.000
        $retHome = TransactionReturn::create([
            'return_number' => 'RET-M12-001',
            'transaction_id' => $txHome->id,
            'branch_id' => $this->branchNgunut->id,
            'processed_by_user_id' => $this->adminNgunut->id,
            'member_id' => $this->memberNgunut->id,
            'member_home_branch_id' => $this->branchNgunut->id,
            'return_type' => TransactionReturn::TYPE_PARTIAL,
            'refund_method' => TransactionReturn::REFUND_CASH,
            'cash_source' => TransactionReturn::CASH_SOURCE_BRANCH,
            'total_refund_amount' => 70000,
            'total_restocked_cogs' => 50000,
            'total_damaged_cogs' => 0,
            'reason' => 'Kemasan sobek sebagian',
            'status' => TransactionReturn::STATUS_COMPLETED,
            'returned_at' => now(),
        ]);

        TransactionReturnItem::create([
            'transaction_return_id' => $retHome->id,
            'transaction_item_id' => $txHomeItem->id,
            'product_id' => $this->product->id,
            'product_name_snapshot' => $this->product->name,
            'qty_returned' => 1,
            'condition' => TransactionReturnItem::CONDITION_RESTOCKABLE,
            'unit_price_snapshot' => 70000,
            'cost_price_snapshot' => 50000,
            'subtotal_refund' => 70000,
            'subtotal_cogs' => 50000,
        ]);

        // Tx 2: memberNgunut shops CROSS-BRANCH at Cekok: 1 qty * Rp 70.000 (regular 75.000, discount 5.000)
        $txCross = Transaction::create([
            'invoice_number' => 'INV-M12-CALC-CROSS',
            'idempotency_key' => (string) Str::uuid(),
            'branch_id' => $this->branchCekok->id,
            'cashier_id' => $this->cashierNgunut->id,
            'member_id' => $this->memberNgunut->id,
            'member_home_branch_id' => $this->branchNgunut->id,
            'regular_subtotal' => 75000,
            'member_discount_total' => 5000,
            'subtotal' => 70000,
            'total_amount' => 70000,
            'total_cogs' => 50000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'return_status' => 'none',
            'paid_at' => now(),
        ]);

        TransactionItem::create([
            'transaction_id' => $txCross->id,
            'product_id' => $this->product->id,
            'product_name_snapshot' => $this->product->name,
            'barcode_snapshot' => $this->product->barcode,
            'unit_snapshot' => $this->product->unit,
            'qty' => 1,
            'regular_price_snapshot' => 75000,
            'unit_price_snapshot' => 70000,
            'member_discount_snapshot' => 5000,
            'is_member_price_applied' => true,
            'cost_price_snapshot' => 50000,
            'subtotal' => 70000,
            'subtotal_cogs' => 50000,
        ]);

        // Tx 3: memberNgunut2 shops at HOME branch (Ngunut): Rp 210.000 (3 qty, cogs 150.000)
        Transaction::create([
            'invoice_number' => 'INV-M12-CALC-M2',
            'idempotency_key' => (string) Str::uuid(),
            'branch_id' => $this->branchNgunut->id,
            'cashier_id' => $this->cashierNgunut->id,
            'member_id' => $memberNgunut2->id,
            'member_home_branch_id' => $this->branchNgunut->id,
            'regular_subtotal' => 225000,
            'member_discount_total' => 15000,
            'subtotal' => 210000,
            'total_amount' => 210000,
            'total_cogs' => 150000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'return_status' => 'none',
            'paid_at' => now(),
        ]);

        // Check expected numbers:
        // memberNgunut:
        //   home_branch_spending = 210.000 - 70.000 = 140.000
        //   cross_branch_spending = 70.000
        //   net_spending_total = 140.000 + 70.000 = 210.000
        //   net_member_savings = (15.000 + 5.000) - 5.000 = 15.000
        // Home branch (Ngunut) financials:
        //   gross_revenue = 210.000 + 210.000 = 420.000; returns = 70.000 => net_revenue = 350.000
        //   gross_cogs = 150.000 + 150.000 = 300.000; restocked_cogs = 50.000 => net_cogs = 250.000
        //   gross_profit = 100.000; operational_expenses = 0 => net_profit = 100.000
        //   member_usaha_pool (40%) = 40.000
        // Total net spending of Ngunut members = memberNgunut (210.000) + memberNgunut2 (210.000) = 420.000
        // memberNgunut share = 210.000 / 420.000 = 50% => estimated_shu = 40.000 * 0.5 = 20.000
        $portalService = app(MemberPortalService::class);
        $summary = $portalService->getDashboardSummary($this->memberNgunut, MemberPortalService::PERIOD_CURRENT_YEAR);

        $this->assertEquals(140000.0, $summary['home_branch_spending']);
        $this->assertEquals(70000.0, $summary['cross_branch_spending']);
        $this->assertEquals(210000.0, $summary['net_spending_total']);
        $this->assertEquals(15000.0, $summary['net_member_savings']);
        $this->assertEquals(100000.0, $summary['home_branch_net_profit']);
        $this->assertEquals(50.0, $summary['spend_share_percent']);
        $this->assertEquals(20000.0, $summary['estimated_shu']);
        $this->assertSame('Estimasi sementara sebelum penetapan RAT', $summary['shu_disclaimer']);

        // Now add Operational Expense of Rp 150.000 at Ngunut -> net_profit becomes -50.000 (<= 0) -> estimated_shu MUST be 0
        OperationalExpense::create([
            'branch_id' => $this->branchNgunut->id,
            'user_id' => $this->adminNgunut->id,
            'category' => OperationalExpense::CATEGORY_LISTRIK_AIR,
            'amount' => 150000,
            'expense_date' => now()->toDateString(),
            'description' => 'Tagihan listrik besar untuk uji rugi',
        ]);

        $summaryLoss = $portalService->getDashboardSummary($this->memberNgunut, MemberPortalService::PERIOD_CURRENT_YEAR);
        $this->assertEquals(-50000.0, $summaryLoss['home_branch_net_profit']);
        $this->assertEquals(0.0, $summaryLoss['estimated_shu']);
        $this->assertEquals(0.0, $summaryLoss['spend_share_percent']);
    }

    public function test_member_can_change_own_pin_with_valid_current_pin_and_rejects_wrong_current_pin(): void
    {
        // Wrong current_pin -> 422 validation error
        $this->actingAs($this->memberNgunut, 'member')
            ->from(route('member.security'))
            ->put(route('member.security.pin.update'), [
                'current_pin' => '999999',
                'new_pin' => '654321',
                'new_pin_confirmation' => '654321',
            ])
            ->assertRedirect(route('member.security'))
            ->assertSessionHasErrors(['current_pin']);

        // Same new_pin as current_pin -> validation error
        $this->actingAs($this->memberNgunut, 'member')
            ->from(route('member.security'))
            ->put(route('member.security.pin.update'), [
                'current_pin' => '123456',
                'new_pin' => '123456',
                'new_pin_confirmation' => '123456',
            ])
            ->assertRedirect(route('member.security'))
            ->assertSessionHasErrors(['new_pin']);

        // Valid current_pin -> updates pin_hash and keeps member logged in
        $this->actingAs($this->memberNgunut, 'member')
            ->from(route('member.security'))
            ->put(route('member.security.pin.update'), [
                'current_pin' => '123456',
                'new_pin' => '789012',
                'new_pin_confirmation' => '789012',
            ])
            ->assertRedirect(route('member.security'))
            ->assertSessionHas('success');

        $this->assertAuthenticatedAs($this->memberNgunut, 'member');
        $this->memberNgunut->refresh();
        $this->assertTrue(Hash::check('789012', $this->memberNgunut->pin_hash));
        $this->assertFalse(Hash::check('123456', $this->memberNgunut->pin_hash));
    }

    public function test_admin_cabang_can_reset_own_branch_member_pin_with_audit_log_and_is_blocked_cross_branch(): void
    {
        // Admin Ngunut resets PIN of memberNgunut -> allowed & logged without storing raw PIN
        $this->actingAs($this->adminNgunut)
            ->post(route('members.reset-pin', $this->memberNgunut), [
                'pin' => '246810',
                'pin_confirmation' => '246810',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->memberNgunut->refresh();
        $this->assertTrue(Hash::check('246810', $this->memberNgunut->pin_hash));

        $log = ActivityLog::query()
            ->where('action', ActivityLog::ACTION_MEMBER_PIN_RESET)
            ->where('subject_id', $this->memberNgunut->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame($this->adminNgunut->id, $log->actor_id);
        $this->assertSame($this->branchNgunut->id, $log->branch_id);
        $this->assertStringNotContainsString('246810', json_encode($log->toArray()));

        // Admin Ngunut tries to reset PIN of memberCekok (different home_branch_id) -> HTTP 403 Forbidden
        $this->actingAs($this->adminNgunut)
            ->post(route('members.reset-pin', $this->memberCekok), [
                'pin' => '135790',
                'pin_confirmation' => '135790',
            ])
            ->assertForbidden();

        // Admin Nasional can reset PIN for any branch's member
        $this->actingAs($this->adminNasional)
            ->post(route('members.reset-pin', $this->memberCekok), [
                'pin' => '135790',
                'pin_confirmation' => '135790',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->memberCekok->refresh();
        $this->assertTrue(Hash::check('135790', $this->memberCekok->pin_hash));
    }
}
