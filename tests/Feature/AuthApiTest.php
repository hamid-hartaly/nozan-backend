<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_log_in_and_receive_expected_payload(): void
    {
        $user = User::factory()->create([
            'name' => 'Ahmad',
            'email' => 'hamid.hartaly@gmail.com',
            'password' => 'P@ssword123',
            'role' => 'staff',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'hamid.hartaly@gmail.com',
            'password' => 'P@ssword123',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('user.id', (string) $user->id)
            ->assertJsonPath('user.uid', (string) $user->id)
            ->assertJsonPath('user.name', 'Ahmad')
            ->assertJsonPath('user.full_name', 'Ahmad')
            ->assertJsonPath('user.email', 'hamid.hartaly@gmail.com')
            ->assertJsonPath('user.role', 'staff')
            ->assertJsonPath('user.is_active', true)
            ->assertJsonPath('user.can_record_payment', false);

        $this->assertIsString($response->json('token'));
        $this->assertNotEmpty($response->json('token'));
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'hamid.hartaly@gmail.com',
            'password' => 'P@ssword123',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'hamid.hartaly@gmail.com',
            'password' => 'wrong-password',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_authenticated_user_can_fetch_profile(): void
    {
        $user = User::factory()->create([
            'role' => 'accountant',
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this
            ->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/auth/me');

        $response
            ->assertOk()
            ->assertJsonPath('user.id', (string) $user->id)
            ->assertJsonPath('user.role', 'accountant')
            ->assertJsonPath('user.can_record_payment', true);
    }

    public function test_staff_with_cashier_flag_receives_payment_capability(): void
    {
        $user = User::factory()->create([
            'role' => 'staff',
            'can_record_payment' => true,
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $this
            ->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('user.role', 'staff')
            ->assertJsonPath('user.can_record_payment', true);
    }

    public function test_logout_revokes_current_access_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;
        [$tokenId] = explode('|', $token, 2);

        $this
            ->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/auth/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Signed out successfully.');

        $this->assertNull(PersonalAccessToken::find($tokenId));
    }
}
