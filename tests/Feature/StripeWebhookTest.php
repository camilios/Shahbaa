<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Checkpoint;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK_SECRET = 'whsec_test_only_not_a_real_secret';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.stripe.webhook_secret' => self::WEBHOOK_SECRET]);
    }

    public function test_webhook_requires_a_valid_stripe_signature(): void
    {
        $payload = json_encode($this->eventPayload('customer.created'));

        $this->call('POST', '/api/stripe/webhook', [], [], [], [], $payload)
            ->assertBadRequest()
            ->assertJsonPath('message', 'Stripe signature is missing.');

        $this->postSignedPayload($payload, 'invalid-signature')
            ->assertBadRequest()
            ->assertJsonPath('message', 'Invalid Stripe webhook.');

        $this->postSignedPayload('{invalid json')
            ->assertBadRequest()
            ->assertJsonPath('message', 'Invalid Stripe webhook.');
    }

    public function test_webhook_accepts_a_valid_signature_and_ignores_an_unsupported_event(): void
    {
        $payload = json_encode($this->eventPayload('customer.created'));

        $this->postSignedPayload($payload)
            ->assertOk()
            ->assertJsonPath('message', 'Stripe event received.');
    }

    public function test_completed_checkout_confirms_the_booking(): void
    {
        [$booking, $amountMinor] = $this->bookingFixture();
        $sessionId = 'cs_test_completed';
        $booking->update(['payment_reference' => $sessionId]);

        $payload = json_encode($this->eventPayload(
            'checkout.session.completed',
            $booking,
            $sessionId,
            $amountMinor,
        ));

        $this->postSignedPayload($payload)
            ->assertOk()
            ->assertJsonPath('message', 'Stripe webhook processed successfully.');

        $booking->refresh();
        $this->assertSame('confirmed', $booking->status);
        $this->assertSame('stripe', $booking->payment_method);
        $this->assertSame('paid', $booking->payment_status);
        $this->assertSame('25.50', $booking->paid_amount);
        $this->assertNotNull($booking->paid_at);
        $this->assertSame($sessionId, $booking->payment_reference);
    }

    public function test_async_payment_success_is_supported_and_duplicate_events_are_idempotent(): void
    {
        [$booking, $amountMinor] = $this->bookingFixture();
        $sessionId = 'cs_test_async';
        $booking->update(['payment_reference' => $sessionId]);

        $completed = json_encode($this->eventPayload(
            'checkout.session.completed',
            $booking,
            $sessionId,
            $amountMinor,
        ));
        $async = json_encode($this->eventPayload(
            'checkout.session.async_payment_succeeded',
            $booking,
            $sessionId,
            $amountMinor,
        ));

        $this->postSignedPayload($completed)->assertOk();
        $firstPaidAt = $booking->fresh()->paid_at?->format('Y-m-d H:i:s.u');
        $notificationCount = \DB::table('notifications')->count();

        $this->postSignedPayload($completed)->assertOk();
        $this->postSignedPayload($async)->assertOk();

        $booking->refresh();
        $this->assertSame('paid', $booking->payment_status);
        $this->assertSame($firstPaidAt, $booking->paid_at?->format('Y-m-d H:i:s.u'));
        $this->assertSame($notificationCount, \DB::table('notifications')->count());
    }

    public function test_unpaid_checkout_does_not_change_the_booking(): void
    {
        [$booking, $amountMinor] = $this->bookingFixture();
        $sessionId = 'cs_test_unpaid';
        $booking->update(['payment_reference' => $sessionId]);
        $event = $this->eventPayload('checkout.session.completed', $booking, $sessionId, $amountMinor);
        $event['data']['object']['payment_status'] = 'unpaid';

        $this->postSignedPayload(json_encode($event))->assertOk();

        $this->assertSame('unpaid', $booking->fresh()->payment_status);
    }

    public function test_required_metadata_fields_and_one_minor_unit_difference_are_rejected(): void
    {
        [$booking, $amountMinor] = $this->bookingFixture();
        $booking->update(['payment_reference' => 'cs_test_metadata']);

        foreach (['booking_id', 'user_id', 'amount_minor'] as $field) {
            $event = $this->eventPayload(
                'checkout.session.completed', $booking, 'cs_test_metadata', $amountMinor
            );
            unset($event['data']['object']['metadata'][$field]);

            $this->postSignedPayload(json_encode($event))
                ->assertBadRequest()
                ->assertJsonPath('message', 'Required Stripe metadata is missing.');
        }

        $event = $this->eventPayload(
            'checkout.session.completed', $booking, 'cs_test_metadata', $amountMinor
        );
        $event['data']['object']['amount_total']++;
        $this->postSignedPayload(json_encode($event))
            ->assertBadRequest()
            ->assertJsonPath('message', 'Stripe payment amount does not match.');
    }

    public function test_user_session_currency_database_amount_and_booking_state_must_match(): void
    {
        [$booking, $amountMinor] = $this->bookingFixture();
        $sessionId = 'cs_test_mismatch';
        $booking->update(['payment_reference' => $sessionId]);

        $mutations = [
            fn (array &$event) => $event['data']['object']['metadata']['user_id'] = '999999',
            fn (array &$event) => $event['data']['object']['id'] = 'cs_test_wrong',
            fn (array &$event) => $event['data']['object']['currency'] = 'eur',
            function (array &$event) use ($booking): void {
                $booking->trip->update(['money_price' => '12.76']);
            },
            function (array &$event) use ($booking): void {
                $booking->update(['status' => 'cancelled']);
            },
        ];

        foreach ($mutations as $mutation) {
            $booking->update(['status' => 'pending']);
            $booking->trip->update(['money_price' => '12.75']);
            $event = $this->eventPayload(
                'checkout.session.completed', $booking, $sessionId, $amountMinor
            );
            $mutation($event);

            $this->postSignedPayload(json_encode($event))
                ->assertBadRequest()
                ->assertJsonPath('message', 'Stripe checkout data does not match the booking.');
            $this->assertSame('unpaid', $booking->fresh()->payment_status);
        }
    }

    public function test_unknown_booking_is_safely_ignored_without_a_server_error(): void
    {
        $event = $this->eventPayload('checkout.session.completed');
        $event['data']['object']['metadata']['booking_id'] = '999999';

        $this->postSignedPayload(json_encode($event))
            ->assertOk()
            ->assertJsonPath('message', 'Booking was not found; event ignored.');
    }

    private function postSignedPayload(string $payload, ?string $signature = null)
    {
        $timestamp = time();
        $signature ??= hash_hmac('sha256', $timestamp.'.'.$payload, self::WEBHOOK_SECRET);

        return $this->call('POST', '/api/stripe/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
        ], $payload);
    }

    private function eventPayload(
        string $type,
        ?Booking $booking = null,
        string $sessionId = 'cs_test_unused',
        int $amountMinor = 2550,
    ): array {
        return [
            'id' => 'evt_'.str_replace('.', '_', $type),
            'object' => 'event',
            'api_version' => '2025-02-24.acacia',
            'created' => time(),
            'data' => [
                'object' => [
                    'id' => $sessionId,
                    'object' => 'checkout.session',
                    'amount_total' => $amountMinor,
                    'currency' => 'usd',
                    'payment_status' => 'paid',
                    'metadata' => [
                        'booking_id' => (string) ($booking?->id ?? 1),
                        'user_id' => (string) ($booking?->user_id ?? 1),
                        'amount_minor' => (string) $amountMinor,
                    ],
                ],
            ],
            'livemode' => false,
            'pending_webhooks' => 1,
            'request' => ['id' => null, 'idempotency_key' => null],
            'type' => $type,
        ];
    }

    private function bookingFixture(): array
    {
        $driver = User::factory()->create(['role' => 'driver', 'status' => 'active']);
        $customer = User::factory()->create(['role' => 'customer', 'status' => 'active']);
        $from = Checkpoint::create(['name' => 'Stripe From']);
        $to = Checkpoint::create(['name' => 'Stripe To']);
        $trip = Trip::create([
            'driver_id' => $driver->id,
            'money_price' => 12.75,
            'status' => 'scheduled',
            'departure_date' => now()->addDay(),
            'total_seats' => 10,
            'available_seats' => 8,
        ]);
        $booking = Booking::create([
            'user_id' => $customer->id,
            'driver_id' => $driver->id,
            'trip_id' => $trip->id,
            'pickup_checkpoint_id' => $from->id,
            'dropoff_checkpoint_id' => $to->id,
            'seats_count' => 2,
            'status' => 'pending',
            'payment_status' => 'unpaid',
        ]);

        return [$booking, 2550];
    }
}
