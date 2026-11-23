<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\Category;
use App\Models\Member;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class Milestone1FoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        // Register a temporary test-only route protected by BranchPolicy and ReportPolicy to test HTTP 403 anti-IDOR response
        Route::middleware(['web', 'auth', 'role:admin_nasional,admin_provinsi,admin_kabupaten,admin_cabang'])
            ->get('/_test/milestone1/branches/{branch}', function (Branch $branch) {
                Gate::authorize('view', $branch);

                return response()->json(['id' => $branch->id, 'code' => $branch->code]);
            });

        Route::middleware(['web', 'auth', 'role:admin_nasional,admin_provinsi,admin_kabupaten,admin_cabang'])
            ->get('/_test/milestone1/reports', function () {
                $user = auth()->user();
                $provinceId = request()->query('province_id');
                $regencyId = request()->query('regency_id');
                $branchId = request()->query('branch_id') !== null ? (int) request()->query('branch_id') : null;

                Gate::authorize('filter-region', [$provinceId, $regencyId, $branchId]);

                return response()->json([
                    'branches_count' => Branch::forUser($user)->count(),
                ]);
            });
    }

    public function test_unique_village_id_constraint_rejects_second_kopdes_in_same_village(): void
    {
        $existingBranch = Branch::where('village_id', '3502090001')->firstOrFail();
        $this->assertSame('KOP-3502-001', $existingBranch->code);

        $this->expectException(QueryException::class);

        Branch::create([
            'code' => 'KOP-3502-999',
            'name' => 'KOPDES Duplikat Desa Ngunut',
            'village_id' => '3502090001', // Desa Ngunut already has KOP-3502-001
            'district_id' => '3502090',
            'regency_id' => '3502',
            'province_id' => '35',
            'address' => 'Jl. Duplikat No. 99',
            'phone' => '0352-000000',
            'is_active' => true,
        ]);
    }

    public function test_has_region_scope_filters_branches_stocks_members_and_users_by_actor_hierarchy(): void
    {
        $branchNgunut = Branch::where('code', 'KOP-3502-001')->firstOrFail();
        $branchCekok = Branch::where('code', 'KOP-3502-002')->firstOrFail();

        // Create a branch in Kabupaten Madiun (3519) and a branch in Provinsi Jawa Tengah (33 / 3312)
        $branchMadiun = Branch::create([
            'code' => 'KOP-3519-001',
            'name' => 'KOPDES Desa Balerejo Madiun',
            'village_id' => '3519010001',
            'district_id' => '3519010',
            'regency_id' => '3519',
            'province_id' => '35',
            'address' => 'Jl. Madiun No. 1',
            'is_active' => true,
        ]);

        $branchWonogiri = Branch::create([
            'code' => 'KOP-3312-001',
            'name' => 'KOPDES Desa Biting Wonogiri',
            'village_id' => '3312010001',
            'district_id' => '3312010',
            'regency_id' => '3312',
            'province_id' => '33',
            'address' => 'Jl. Wonogiri No. 1',
            'is_active' => true,
        ]);

        $category = Category::create(['name' => 'Sembako', 'slug' => 'sembako']);
        $product = Product::create([
            'category_id' => $category->id,
            'sku' => 'SKU-BERAS-01',
            'barcode' => '899000000001',
            'name' => 'Beras Merah Putih 5kg',
            'unit' => 'karung',
            'cost_price' => 55000,
            'selling_price' => 62000,
            'is_active' => true,
        ]);

        BranchStock::create(['branch_id' => $branchNgunut->id, 'product_id' => $product->id, 'quantity' => 20, 'reserved_qty' => 2]);
        BranchStock::create(['branch_id' => $branchCekok->id, 'product_id' => $product->id, 'quantity' => 15, 'reserved_qty' => 0]);
        BranchStock::create(['branch_id' => $branchMadiun->id, 'product_id' => $product->id, 'quantity' => 10, 'reserved_qty' => 0]);
        BranchStock::create(['branch_id' => $branchWonogiri->id, 'product_id' => $product->id, 'quantity' => 8, 'reserved_qty' => 0]);

        $adminNasional = User::where('email', 'admin.nasional@kopdes.id')->firstOrFail();
        $adminJatim = User::where('email', 'admin.jatim@kopdes.id')->firstOrFail();
        $adminPonorogo = User::where('email', 'admin.ponorogo@kopdes.id')->firstOrFail();
        $adminNgunut = User::where('email', 'admin.ngunut@kopdes.id')->firstOrFail();

        // 1. Admin Nasional sees all 4 branches & 4 stocks
        $this->assertCount(4, Branch::forUser($adminNasional)->get());
        $this->assertCount(4, BranchStock::forUser($adminNasional)->get());

        // 2. Admin Provinsi Jawa Timur (35) sees 3 branches (Ngunut, Cekok, Madiun) and excludes Wonogiri (33)
        $jatimBranchIds = Branch::forUser($adminJatim)->pluck('id')->all();
        $this->assertCount(3, $jatimBranchIds);
        $this->assertNotContains($branchWonogiri->id, $jatimBranchIds);
        $this->assertCount(3, BranchStock::forUser($adminJatim)->get());

        // 3. Admin Kabupaten Ponorogo (3502) sees only 2 branches (Ngunut, Cekok) and excludes Madiun (3519)
        $ponorogoBranchIds = Branch::forUser($adminPonorogo)->pluck('id')->all();
        $this->assertCount(2, $ponorogoBranchIds);
        $this->assertNotContains($branchMadiun->id, $ponorogoBranchIds);
        $this->assertCount(2, BranchStock::forUser($adminPonorogo)->get());

        // 4. Admin Cabang Ngunut sees only their own branch (Ngunut), stock, and members
        $ngunutBranchIds = Branch::forUser($adminNgunut)->pluck('id')->all();
        $this->assertSame([$branchNgunut->id], $ngunutBranchIds);
        $this->assertCount(1, BranchStock::forUser($adminNgunut)->get());
        $this->assertCount(1, Member::forUser($adminNgunut)->get());
    }

    public function test_policies_and_routes_reject_cross_region_access_with_http_403_anti_idor(): void
    {
        $branchNgunut = Branch::where('code', 'KOP-3502-001')->firstOrFail();
        $branchCekok = Branch::where('code', 'KOP-3502-002')->firstOrFail();
        $branchMadiun = Branch::create([
            'code' => 'KOP-3519-001',
            'name' => 'KOPDES Desa Balerejo Madiun',
            'village_id' => '3519010001',
            'district_id' => '3519010',
            'regency_id' => '3519',
            'province_id' => '35',
            'address' => 'Jl. Madiun No. 1',
            'is_active' => true,
        ]);

        $adminPonorogo = User::where('email', 'admin.ponorogo@kopdes.id')->firstOrFail();
        $adminNgunut = User::where('email', 'admin.ngunut@kopdes.id')->firstOrFail();

        // Admin Cabang Ngunut can view own branch (200), but gets 403 when viewing Cekok or Madiun
        $this->actingAs($adminNgunut)
            ->get("/_test/milestone1/branches/{$branchNgunut->id}")
            ->assertOk();

        $this->actingAs($adminNgunut)
            ->get("/_test/milestone1/branches/{$branchCekok->id}")
            ->assertForbidden();

        $this->actingAs($adminNgunut)
            ->get("/_test/milestone1/branches/{$branchMadiun->id}")
            ->assertForbidden();

        // Admin Kabupaten Ponorogo can view Ngunut & Cekok (200), but gets 403 when viewing Madiun branch
        $this->actingAs($adminPonorogo)
            ->get("/_test/milestone1/branches/{$branchCekok->id}")
            ->assertOk();

        $this->actingAs($adminPonorogo)
            ->get("/_test/milestone1/branches/{$branchMadiun->id}")
            ->assertForbidden();

        // Anti-IDOR URL query parameter tampering on reports:
        // Admin Kabupaten Ponorogo requesting ?branch_id={Madiun} or ?regency_id=3519 gets HTTP 403 Forbidden
        $this->actingAs($adminPonorogo)
            ->get("/_test/milestone1/reports?branch_id={$branchMadiun->id}")
            ->assertForbidden();

        $this->actingAs($adminPonorogo)
            ->get('/_test/milestone1/reports?regency_id=3519')
            ->assertForbidden();

        // Admin Cabang Ngunut requesting ?branch_id={Cekok} gets HTTP 403 Forbidden
        $this->actingAs($adminNgunut)
            ->get("/_test/milestone1/reports?branch_id={$branchCekok->id}")
            ->assertForbidden();
    }

    public function test_admin_login_rejects_cashier_and_never_exposes_pin_hash(): void
    {
        $cashier = User::where('role', User::ROLE_KASIR)->firstOrFail();
        $cashier->update([
            'email' => 'kasir.ngunut@kopdes.id',
            'password' => 'password',
        ]);

        // Cashier cannot login via /login (Admin login endpoint)
        $response = $this->post('/login', [
            'email' => 'kasir.ngunut@kopdes.id',
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();

        // Cashier trying to access /dashboard gets 403 Forbidden from EnsureRole middleware
        $this->actingAs($cashier)
            ->get('/dashboard')
            ->assertForbidden();

        // Admin user array serialization never contains pin_hash
        $this->assertArrayNotHasKey('pin_hash', $cashier->toArray());
    }
}
