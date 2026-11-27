<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_self_account_deletion_is_disabled(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response->assertForbidden();

        $this->assertAuthenticated();
        $this->assertNotNull($user->fresh());
    }

    public function test_cashier_cannot_access_admin_profile_or_password_routes(): void
    {
        $cashier = User::factory()->create([
            'role' => User::ROLE_KASIR,
            'email' => null,
            'password' => null,
            'card_uid' => '9988776655',
        ]);

        $this->actingAs($cashier)->get('/profile')->assertForbidden();
        $this->actingAs($cashier)->patch('/profile', [
            'name' => 'Kasir Hack',
            'email' => 'kasir@example.com',
        ])->assertForbidden();
        $this->actingAs($cashier)->delete('/profile', [
            'password' => 'password',
        ])->assertForbidden();
        $this->actingAs($cashier)->put('/password', [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertForbidden();
    }
}
