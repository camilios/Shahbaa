<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_admin_can_log_in_and_receive_a_sanctum_token(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
            'password' => 'password',
        ]);

        $this->postJson('/api/admin/login', [
            'email' => $admin->email,
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.id', $admin->id)
            ->assertJsonPath('user.role', 'admin')
            ->assertJsonMissingPath('user.password');

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $admin->id,
            'name' => 'admin_token',
        ]);
    }

    public function test_non_admin_cannot_log_in_through_admin_endpoint(): void
    {
        $driver = User::factory()->create([
            'role' => 'driver',
            'status' => 'active',
            'password' => 'password',
        ]);

        $this->postJson('/api/admin/login', [
            'email' => $driver->email,
            'password' => 'password',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_inactive_admin_cannot_log_in(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'inactive',
            'password' => 'password',
        ]);

        $this->postJson('/api/admin/login', [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_wrong_credentials_are_rejected(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'password' => 'password',
        ]);

        $this->postJson('/api/admin/login', [
            'email' => $admin->email,
            'password' => 'wrong-password',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_authenticated_admin_can_log_out_and_revoke_current_token(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $token = $admin->createToken('admin_token');

        $this->withToken($token->plainTextToken)
            ->postJson('/api/admin/logout')
            ->assertOk()
            ->assertExactJson(['message' => 'Logged out successfully']);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $token->accessToken->id,
        ]);
    }

    public function test_logout_requires_authentication(): void
    {
        $this->postJson('/api/admin/logout')->assertUnauthorized();
    }
}
