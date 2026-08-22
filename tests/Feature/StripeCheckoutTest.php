<?php

namespace Tests\Feature;

use App\Contracts\StripeCheckoutGateway;
use App\Models\Booking;
use App\Models\Checkpoint;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StripeCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_creates_checkout_from_database_price_with_complete_parameters(): void
    {
        [$customer, $booking] = $this->bookingFixture('12.75', 2);
        $gateway = $this->fakeGateway();
        Sanctum::actingAs($customer);
        config(['app.url' => 'https://shahbaa.example', 'services.stripe.currency' => 'USD']);

        $response = $this->postJson("/api/customer/bookings/{$booking->id}/stripe-checkout", [
            'amount' => 1,
            'currency' => 'eur',
        ]);

        $response->assertCreated()
            ->assertJsonPath('checkout_url', 'https://checkout.stripe.test/session')
            ->assertJsonPath('session_id', 'cs_test_mocked')
            ->assertJsonPath('amount', '25.50')
            ->assertJsonPath('currency', 'usd')
            ->assertJsonMissingPath('booking')
            ->assertJsonMissingPath('stripe_error');

        $parameters = $gateway->parameters;
        $this->assertSame('payment', $parameters['mode']);
        $this->assertSame('https://shahbaa.example/api/stripe/success?session_id={CHECKOUT_SESSION_ID}', $parameters['success_url']);
        $this->assertSame("https://shahbaa.example/api/stripe/cancel?booking_id={$booking->id}", $parameters['cancel_url']);
        $this->assertSame('usd', $parameters['line_items'][0]['price_data']['currency']);
        $this->assertSame(1275, $parameters['line_items'][0]['price_data']['unit_amount']);
        $this->assertSame(2, $parameters['line_items'][0]['quantity']);
        $this->assertSame($customer->email, $parameters['customer_email']);
        $this->assertSame((string) $booking->id, $parameters['metadata']['booking_id']);
        $this->assertSame((string) $customer->id, $parameters['metadata']['user_id']);
        $this->assertSame('2550', $parameters['metadata']['amount_minor']);
        $this->assertSame("booking-checkout-{$booking->id}", $gateway->options['idempotency_key']);
        $this->assertSame('cs_test_mocked', $booking->fresh()->payment_reference);
    }

    public function test_missing_or_foreign_booking_has_the_same_non_empty_private_message(): void
    {
        [$owner, $booking] = $this->bookingFixture();
        $other = User::factory()->create(['role' => 'customer', 'status' => 'active']);
        Sanctum::actingAs($other);

        foreach ([$booking->id, 999999] as $id) {
            $this->postJson("/api/customer/bookings/{$id}/stripe-checkout")
                ->assertNotFound()
                ->assertJsonPath('message', 'Booking not found or does not belong to the authenticated customer.');
        }

        $this->assertNotSame($owner->id, $other->id);
    }

    public function test_non_pending_non_unpaid_and_already_paid_bookings_are_rejected(): void
    {
        [$customer, $booking] = $this->bookingFixture();
        Sanctum::actingAs($customer);
        $this->fakeGateway();

        $booking->update(['status' => 'confirmed']);
        $this->postJson("/api/customer/bookings/{$booking->id}/stripe-checkout")
            ->assertUnprocessable()->assertJsonValidationErrors('booking');

        $booking->update(['status' => 'pending', 'payment_status' => 'processing']);
        $this->postJson("/api/customer/bookings/{$booking->id}/stripe-checkout")
            ->assertUnprocessable()->assertJsonValidationErrors('booking');

        $booking->update(['payment_status' => 'paid']);
        $this->postJson("/api/customer/bookings/{$booking->id}/stripe-checkout")
            ->assertConflict()->assertJsonPath('message', 'Booking was already paid.');
    }

    public function test_zero_negative_and_invalid_prices_are_rejected_without_calling_stripe(): void
    {
        foreach (['0.00', '-1.00'] as $price) {
            [$customer, $booking] = $this->bookingFixture($price);
            $gateway = $this->fakeGateway();
            Sanctum::actingAs($customer);

            $this->postJson("/api/customer/bookings/{$booking->id}/stripe-checkout")
                ->assertUnprocessable()->assertJsonValidationErrors('amount');
            $this->assertSame(0, $gateway->calls);
        }
    }

    public function test_departed_near_departure_cancelled_and_invalid_currency_are_rejected(): void
    {
        [$customer, $booking, $trip] = $this->bookingFixture();
        Sanctum::actingAs($customer);
        $this->fakeGateway();

        $trip->update(['departure_date' => now()->subMinute()]);
        $this->postJson("/api/customer/bookings/{$booking->id}/stripe-checkout")
            ->assertUnprocessable()->assertJsonValidationErrors('trip');

        $trip->update(['departure_date' => now()->addDay(), 'status' => 'cancelled']);
        $this->postJson("/api/customer/bookings/{$booking->id}/stripe-checkout")
            ->assertUnprocessable()->assertJsonValidationErrors('trip');

        $trip->update(['status' => 'scheduled']);
        config(['services.stripe.currency' => 'US D']);
        $this->postJson("/api/customer/bookings/{$booking->id}/stripe-checkout")
            ->assertUnprocessable()->assertJsonValidationErrors('currency');
    }

    public function test_large_fractional_amount_is_converted_without_floating_point_error(): void
    {
        [$customer, $booking] = $this->bookingFixture('999999.99');
        $gateway = $this->fakeGateway();
        Sanctum::actingAs($customer);

        $this->postJson("/api/customer/bookings/{$booking->id}/stripe-checkout")->assertCreated();

        $this->assertSame(99999999, $gateway->parameters['line_items'][0]['price_data']['unit_amount']);
        $this->assertSame('99999999', $gateway->parameters['metadata']['amount_minor']);
    }

    public function test_amount_above_stripes_eight_digit_usd_limit_is_rejected(): void
    {
        [$customer, $booking] = $this->bookingFixture('1000000.00');
        $gateway = $this->fakeGateway();
        Sanctum::actingAs($customer);

        $this->postJson("/api/customer/bookings/{$booking->id}/stripe-checkout")
            ->assertUnprocessable()->assertJsonValidationErrors('amount');
        $this->assertSame(0, $gateway->calls);
    }

    public function test_amount_below_stripes_usd_minimum_is_rejected(): void
    {
        [$customer, $booking] = $this->bookingFixture('0.49');
        $gateway = $this->fakeGateway();
        Sanctum::actingAs($customer);

        $this->postJson("/api/customer/bookings/{$booking->id}/stripe-checkout")
            ->assertUnprocessable()->assertJsonValidationErrors('amount');
        $this->assertSame(0, $gateway->calls);
    }

    private function fakeGateway(): object
    {
        $gateway = new class implements StripeCheckoutGateway
        {
            public int $calls = 0;

            public array $parameters = [];

            public array $options = [];

            public function createSession(array $parameters, array $options = []): object
            {
                $this->calls++;
                $this->parameters = $parameters;
                $this->options = $options;

                return (object) [
                    'id' => 'cs_test_mocked',
                    'url' => 'https://checkout.stripe.test/session',
                ];
            }
        };

        $this->app->instance(StripeCheckoutGateway::class, $gateway);

        return $gateway;
    }

    private function bookingFixture(string $moneyPrice = '12.75', int $seats = 1): array
    {
        $driver = User::factory()->create(['role' => 'driver', 'status' => 'active']);
        $customer = User::factory()->create(['role' => 'customer', 'status' => 'active']);
        $from = Checkpoint::create(['name' => fake()->unique()->word()]);
        $to = Checkpoint::create(['name' => fake()->unique()->word()]);
        $trip = Trip::create([
            'driver_id' => $driver->id,
            'money_price' => $moneyPrice,
            'status' => 'scheduled',
            'departure_date' => now()->addDay(),
            'total_seats' => 20,
            'available_seats' => 20 - $seats,
        ]);
        $booking = Booking::create([
            'user_id' => $customer->id,
            'driver_id' => $driver->id,
            'trip_id' => $trip->id,
            'pickup_checkpoint_id' => $from->id,
            'dropoff_checkpoint_id' => $to->id,
            'seats_count' => $seats,
            'status' => 'pending',
            'payment_status' => 'unpaid',
        ]);

        return [$customer, $booking, $trip];
    }
}
