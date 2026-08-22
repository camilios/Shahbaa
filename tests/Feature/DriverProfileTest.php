<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DriverProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_driver_profile_includes_parent_names_and_national_number(): void
    {
        $driver = User::factory()->create([
            'role' => 'driver',
            'status' => 'active',
            'father_name' => 'Ahmad',
            'mother_name' => 'Mariam',
            'national_number' => '01020304050',
        ]);
        Sanctum::actingAs($driver);

        $this->getJson('/api/driver/profile')
            ->assertOk()
            ->assertJsonPath('user.father_name', 'Ahmad')
            ->assertJsonPath('user.mother_name', 'Mariam')
            ->assertJsonPath('user.national_number', '01020304050');
    }
}
