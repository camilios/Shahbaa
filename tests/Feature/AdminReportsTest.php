<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Checkpoint;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_receives_report_metrics_for_the_selected_period(): void
    {
        Sanctum::actingAs($this->user('admin'));
        $driver = $this->user('driver');
        $customer = $this->user('customer');
        [$from, $to] = $this->checkpoints();

        $standard = $this->trip($driver, 'standard', 100, 10, now()->subDays(2));
        $private = $this->trip($driver, 'private', 200, 5, now()->subDay());
        $this->trip($driver, 'standard', 999, 10, now()->subDays(40));

        $this->booking($customer, $standard, $from, $to, 3, 'confirmed');
        $this->booking($customer, $standard, $from, $to, 2, 'cancelled');
        $this->booking($customer, $private, $from, $to, 2, 'confirmed');

        $response = $this->getJson('/api/admin/reports?period=last_30_days')
            ->assertOk()
            ->assertJsonPath('summary.total_revenue', 700)
            ->assertJsonPath('summary.total_trips', 2)
            ->assertJsonPath('summary.total_passengers', 5)
            ->assertJsonPath('summary.average_occupancy', 35)
            ->assertJsonCount(2, 'trip_type_performance');

        $this->assertSame(
            2,
            collect($response->json('trips_overview'))->sum('trips')
        );
    }

    public function test_reports_are_restricted_to_administrators(): void
    {
        Sanctum::actingAs($this->user('customer'));

        $this->getJson('/api/admin/reports')->assertForbidden();
        $this->get('/api/admin/reports/export')->assertForbidden();
    }

    public function test_admin_can_export_the_report_as_csv(): void
    {
        Sanctum::actingAs($this->user('admin'));

        $this->get('/api/admin/reports/export?period=last_7_days')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertDownload('shahbaa-report-last_7_days.csv');
    }

    public function test_all_report_periods_are_supported_and_invalid_values_are_rejected(): void
    {
        Sanctum::actingAs($this->user('admin'));

        foreach (['last_7_days', 'last_30_days', 'last_3_months', 'last_year'] as $period) {
            $this->getJson("/api/admin/reports?period={$period}")
                ->assertOk()
                ->assertJsonPath('period.key', $period);
        }

        $this->getJson('/api/admin/reports?period=invalid')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('period');
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role, 'status' => 'active']);
    }

    private function checkpoints(): array
    {
        return [
            Checkpoint::create(['name' => 'From']),
            Checkpoint::create(['name' => 'To']),
        ];
    }

    private function trip(
        User $driver,
        string $type,
        float $price,
        int $seats,
        $departure
    ): Trip {
        return Trip::create([
            'driver_id' => $driver->id,
            'type' => $type,
            'money_price' => $price,
            'status' => 'completed',
            'departure_date' => $departure,
            'arrival_date' => $departure->copy()->addHours(2),
            'total_seats' => $seats,
            'available_seats' => $seats,
        ]);
    }

    private function booking(
        User $customer,
        Trip $trip,
        Checkpoint $from,
        Checkpoint $to,
        int $seats,
        string $status
    ): Booking {
        return Booking::create([
            'user_id' => $customer->id,
            'driver_id' => $trip->driver_id,
            'trip_id' => $trip->id,
            'pickup_checkpoint_id' => $from->id,
            'dropoff_checkpoint_id' => $to->id,
            'seats_count' => $seats,
            'status' => $status,
        ]);
    }
}
