<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_register_and_receives_a_token(): void
    {
        $response = $this->postJson('/api/register', [
            'full_name' => 'New Customer',
            'father_name' => 'Father Name',
            'mother_name' => 'Mother Name',
            'email' => 'new.customer@example.com',
            'phone' => '0999123456',
            'gender' => 'male',
            'national_number' => 'N-10001',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role_id' => 999,
        ]);

        $response->assertCreated()
            ->assertJsonPath('message', 'Registered successfully')
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.name', 'New Customer')
            ->assertJsonPath('user.mother_name', 'Mother Name')
            ->assertJsonPath('user.role', 'customer')
            ->assertJsonPath('user.status', 'active')
            ->assertJsonPath('user.roles.0.name', 'Customer')
            ->assertJsonStructure(['token']);

        $this->assertDatabaseHas('users', [
            'email' => 'new.customer@example.com',
            'name' => 'New Customer',
            'role' => 'customer',
        ]);
    }

    public function test_registration_validates_unique_identity_fields_and_password_confirmation(): void
    {
        $payload = [
            'full_name' => 'First Customer',
            'father_name' => 'Father',
            'email' => 'same@example.com',
            'phone' => '0999000111',
            'gender' => 'female',
            'national_number' => 'N-SAME',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];
        $this->postJson('/api/register', $payload)->assertCreated();

        $this->postJson('/api/register', array_merge($payload, [
            'password_confirmation' => 'different-password',
        ]))->assertUnprocessable()->assertJsonValidationErrors([
            'email', 'phone', 'national_number', 'password',
        ]);
    }
}
