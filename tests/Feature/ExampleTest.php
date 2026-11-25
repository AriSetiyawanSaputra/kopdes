<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_url_redirects_guests_directly_to_login_page(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('login'));
    }

    public function test_root_url_redirects_authenticated_admin_to_dashboard_and_cashier_to_pos(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin.nasional@kopdes.id')->firstOrFail();
        $cashier = User::where('role', User::ROLE_KASIR)->firstOrFail();

        $this->actingAs($admin)
            ->get('/')
            ->assertRedirect(route('dashboard'));

        $this->actingAs($cashier)
            ->get('/')
            ->assertRedirect(route('pos.index'));
    }
}
