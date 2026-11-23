<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Member;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Milestone2MasterAndCashierAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_cashier_can_login_with_unnormalized_card_uid_and_6_digit_pin(): void
    {
        // Seeder creates cashier with card_uid = 'KSR3502001A' and PIN = '123456'
        $response = $this->post('/pos/login', [
            'card_uid' => '  ksr-3502:001 a  ',
            'pin' => '123456',
        ]);

        $this->assertAuthenticated();
        $this->assertSame('KSR3502001A', auth()->user()->card_uid);
        $this->assertFalse((bool) session('pos_locked'));
        $response->assertRedirect(route('pos.index', absolute: false));
    }

    public function test_sixth_failed_cashier_pin_attempt_returns_http_429_rate_limited(): void
    {
        // 5 failed attempts return validation errors (302 / 422)
        for ($i = 1; $i <= 5; $i++) {
            $res = $this->postJson('/pos/login', [
                'card_uid' => 'ksr 3502-001a',
                'pin' => '000000',
            ]);
            $res->assertStatus(422);
        }

        // 6th attempt must be blocked by RateLimiter with HTTP 429 and retry_after seconds
        $sixthResponse = $this->postJson('/pos/login', [
            'card_uid' => 'KSR3502001A',
            'pin' => '123456',
        ]);

        $sixthResponse->assertStatus(429)
            ->assertJsonStructure(['message', 'retry_after']);

        $this->assertGreaterThan(0, $sixthResponse->json('retry_after'));
        $this->assertGuest();
    }

    public function test_card_uid_already_used_by_cashier_is_rejected_for_member_and_vice_versa(): void
    {
        $adminNgunut = User::where('email', 'admin.ngunut@kopdes.id')->firstOrFail();

        // 1. Try registering a member using cashier's card_uid ('KSR3502001A' formatted with lowercase & spaces)
        $memberResponse = $this->actingAs($adminNgunut)->post(route('members.store'), [
            'name' => 'Anggota Kartu Bentrok',
            'card_uid' => 'ksr-3502-001a',
            'phone' => '081299998888',
        ]);

        $memberResponse->assertSessionHasErrors('card_uid');
        $this->assertDatabaseMissing('members', ['name' => 'Anggota Kartu Bentrok']);

        // 2. Try registering a cashier using member Budi Santoso's card_uid ('MBR350200100001')
        $cashierResponse = $this->actingAs($adminNgunut)->post(route('users.store'), [
            'name' => 'Kasir Kartu Bentrok',
            'role' => User::ROLE_KASIR,
            'card_uid' => 'mbr 3502:00100001',
            'pin' => '654321',
        ]);

        $cashierResponse->assertSessionHasErrors('card_uid');
        $this->assertDatabaseMissing('users', ['name' => 'Kasir Kartu Bentrok']);
    }

    public function test_cashier_cannot_login_when_branch_is_deactivated(): void
    {
        $branchNgunut = Branch::where('code', 'KOP-3502-001')->firstOrFail();
        $adminPonorogo = User::where('email', 'admin.ponorogo@kopdes.id')->firstOrFail();

        // Deactivate branch via BranchController
        $this->actingAs($adminPonorogo)
            ->patch(route('branches.toggle-active', $branchNgunut->id))
            ->assertRedirect();

        $this->assertFalse($branchNgunut->fresh()->is_active);

        // Cashier from KOPDES Desa Ngunut tries to login
        $response = $this->post('/pos/login', [
            'card_uid' => 'KSR3502001A',
            'pin' => '123456',
        ]);

        $response->assertSessionHasErrors('card_uid');
        $this->assertGuest();
    }

    public function test_crud_branches_users_members_and_products_respect_rbac_and_server_derivation(): void
    {
        $adminNasional = User::where('email', 'admin.nasional@kopdes.id')->firstOrFail();
        $adminPonorogo = User::where('email', 'admin.ponorogo@kopdes.id')->firstOrFail();
        $adminNgunut = User::where('email', 'admin.ngunut@kopdes.id')->firstOrFail();
        $branchNgunut = Branch::where('code', 'KOP-3502-001')->firstOrFail();

        // 1. Admin Kabupaten Ponorogo registers a new KOPDES branch in Desa Babadan (3502090002) without sending code/province_id
        $this->actingAs($adminPonorogo)->post(route('branches.store'), [
            'name' => 'KOPDES Desa Babadan',
            'village_id' => '3502090002',
            'address' => 'Jl. Raya Babadan No. 10',
            'phone' => '0352-481003',
        ])->assertRedirect(route('branches.index'));

        $newBranch = Branch::where('village_id', '3502090002')->firstOrFail();
        $this->assertSame('KOP-3502-003', $newBranch->code);
        $this->assertSame('3502090', $newBranch->district_id);
        $this->assertSame('3502', $newBranch->regency_id);
        $this->assertSame('35', $newBranch->province_id);

        // Duplicate branch in Desa Ngunut (3502090001) is rejected by FormRequest (422 / session error)
        $this->actingAs($adminPonorogo)->post(route('branches.store'), [
            'name' => 'KOPDES Ganda Ngunut',
            'village_id' => '3502090001',
            'address' => 'Jl. Ganda',
        ])->assertSessionHasErrors('village_id');

        // Admin Kabupaten Ponorogo trying to create branch in Kabupaten Madiun (3519010001) gets HTTP 403
        $this->actingAs($adminPonorogo)->post(route('branches.store'), [
            'name' => 'KOPDES Ilegal Madiun',
            'village_id' => '3519010001',
            'address' => 'Jl. Madiun',
        ])->assertForbidden();

        // 2. Admin Cabang Ngunut creates a Cashier and a Member, and replaces member RFID card
        $this->actingAs($adminNgunut)->post(route('users.store'), [
            'name' => 'Kasir Ngunut Baru',
            'role' => User::ROLE_KASIR,
            'card_uid' => 'ksr-ngunut-02',
            'pin' => '654321',
        ])->assertRedirect(route('users.index'));

        $newCashier = User::where('card_uid', 'KSRNGUNUT02')->firstOrFail();
        $this->assertSame($branchNgunut->id, $newCashier->branch_id);
        $this->assertSame('3502', $newCashier->regency_id);
        $this->assertSame('35', $newCashier->province_id);

        $this->actingAs($adminNgunut)->post(route('members.store'), [
            'name' => 'Pak Joko Susilo',
            'card_uid' => 'mbr-ngunut-00002',
            'phone' => '081333444555',
        ])->assertRedirect(route('members.index'));

        $newMember = Member::where('card_uid', 'MBRNGUNUT00002')->firstOrFail();
        $this->assertSame('MBR-KOP-3502-001-00002', $newMember->member_code);
        $this->assertSame($branchNgunut->id, $newMember->home_branch_id);

        // Replace member's lost RFID card
        $this->actingAs($adminNgunut)->patch(route('members.replace-card', $newMember->id), [
            'card_uid' => 'mbr-ngunut-99999',
        ])->assertRedirect();

        $this->assertSame('MBRNGUNUT99999', $newMember->fresh()->card_uid);

        // 3. Global Categories & Products: Admin Nasional can CRUD, Admin Cabang gets 403 on write
        $this->actingAs($adminNasional)->post(route('categories.store'), [
            'name' => 'Pupuk & Pertanian',
        ])->assertRedirect(route('categories.index'));

        $category = Category::where('slug', 'pupuk-pertanian')->firstOrFail();

        $this->actingAs($adminNgunut)->post(route('products.store'), [
            'category_id' => $category->id,
            'sku' => 'SKU-ILG-01',
            'barcode' => '8990001112223',
            'name' => 'Produk Ilegal Cabang',
            'unit' => 'pcs',
            'cost_price' => 10000,
            'selling_price' => 12000,
        ])->assertForbidden();

        $this->actingAs($adminNasional)->post(route('products.store'), [
            'category_id' => $category->id,
            'sku' => 'sku-ppk-001',
            'barcode' => '8990001112223',
            'name' => 'Pupuk Organik Cair 1L',
            'unit' => 'liter',
            'cost_price' => 35000,
            'selling_price' => 45000,
        ])->assertRedirect(route('products.index'));

        $product = Product::where('sku', 'SKU-PPK-001')->firstOrFail();

        // SoftDelete product
        $this->actingAs($adminNasional)
            ->delete(route('products.destroy', $product->id))
            ->assertRedirect(route('products.index'));

        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }

    public function test_pos_lock_and_unlock_workflow(): void
    {
        $cashier1 = User::where('card_uid', 'KSR3502001A')->firstOrFail();

        $this->actingAs($cashier1)
            ->postJson(route('pos.lock'))
            ->assertOk()
            ->assertJson(['pos_locked' => true]);

        $this->assertTrue((bool) session('pos_locked'));

        $this->actingAs($cashier1)
            ->postJson(route('pos.unlock'), [
                'card_uid' => 'ksr 3502 001a',
                'pin' => '123456',
            ])
            ->assertOk()
            ->assertJson([
                'pos_locked' => false,
                'switched_cashier' => false,
            ]);

        $this->assertFalse((bool) session('pos_locked'));
    }
}
