<?php

namespace App\Http\Controllers;

use App\Contracts\StripeCheckoutGateway;
use App\Models\Booking;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Stripe\Exception\ApiErrorException;

class CustomerStripeCheckoutController extends Controller
{
    public function __construct(private readonly StripeCheckoutGateway $stripe) {}

    public function __invoke(Request $request, int $booking)
    {
        abort_unless(strtolower((string) $request->user()->role) === 'customer', 403);

        try {
            return DB::transaction(function () use ($request, $booking) {
                $lockedBooking = Booking::with('trip')->lockForUpdate()
                    ->whereKey($booking)->where('user_id', $request->user()->id)->first();

                if (! $lockedBooking) {
                    return response()->json([
                        'message' => 'Booking not found or does not belong to the authenticated customer.',
                    ], 404);
                }

                if ($lockedBooking->payment_status === 'paid') {
                    return response()->json(['message' => 'Booking was already paid.'], 409);
                }

                if ($lockedBooking->status !== 'pending' || $lockedBooking->payment_status !== 'unpaid') {
                    throw ValidationException::withMessages([
                        'booking' => ['Only pending unpaid bookings can be paid with Stripe.'],
                    ]);
                }

                if (! $lockedBooking->trip) {
                    throw ValidationException::withMessages([
                        'trip' => ['The trip associated with this booking is unavailable.'],
                    ]);
                }

                if (! $lockedBooking->trip->departure_date
                    || $lockedBooking->trip->departure_date->lte(now()->addHour())) {
                    throw ValidationException::withMessages([
                        'trip' => ['Payment must be completed at least one hour before departure.'],
                    ]);
                }

                if (in_array($lockedBooking->trip->status, ['cancelled', 'canceled', 'completed'], true)) {
                    throw ValidationException::withMessages([
                        'trip' => ['This trip is not available for payment.'],
                    ]);
                }

                try {
                    $unitAmount = Money::toMinor($lockedBooking->trip->money_price);
                } catch (InvalidArgumentException) {
                    throw ValidationException::withMessages([
                        'amount' => ['This booking does not have a valid monetary price.'],
                    ]);
                }

                $quantity = (int) $lockedBooking->seats_count;
                $totalAmount = $unitAmount * $quantity;
                if ($unitAmount <= 0 || $quantity <= 0 || $totalAmount <= 0
                    || $totalAmount > 99_999_999) {
                    throw ValidationException::withMessages([
                        'amount' => ['This booking does not have a valid monetary price.'],
                    ]);
                }

                $currency = strtolower((string) config('services.stripe.currency', 'usd'));
                if (! preg_match('/^[a-z]{3}$/', $currency)) {
                    throw ValidationException::withMessages([
                        'currency' => ['The configured Stripe currency is invalid.'],
                    ]);
                }

                if ($currency === 'usd' && $totalAmount < 50) {
                    throw ValidationException::withMessages([
                        'amount' => ['The payment amount is below Stripe minimum for USD.'],
                    ]);
                }

                $baseUrl = rtrim((string) config('app.url'), '/');
                $parameters = [
                    'mode' => 'payment',
                    'payment_method_types' => ['card'],
                    'customer_email' => $request->user()->email,
                    'success_url' => $baseUrl.'/api/stripe/success?session_id={CHECKOUT_SESSION_ID}',
                    'cancel_url' => $baseUrl.'/api/stripe/cancel?booking_id='.$lockedBooking->id,
                    'line_items' => [[
                        'price_data' => [
                            'currency' => $currency,
                            'unit_amount' => $unitAmount,
                            'product_data' => [
                                'name' => 'Shahbaa trip booking #'.$lockedBooking->id,
                                'description' => 'Payment for '.$quantity.' seat(s)',
                            ],
                        ],
                        'quantity' => $quantity,
                    ]],
                    'metadata' => [
                        'booking_id' => (string) $lockedBooking->id,
                        'user_id' => (string) $request->user()->id,
                        'amount_minor' => (string) $totalAmount,
                    ],
                ];

                $session = $this->stripe->createSession($parameters, [
                    'idempotency_key' => 'booking-checkout-'.$lockedBooking->id,
                ]);

                $lockedBooking->update(['payment_reference' => $session->id]);

                return response()->json([
                    'message' => 'Stripe Checkout session created successfully.',
                    'checkout_url' => $session->url,
                    'session_id' => $session->id,
                    'amount' => Money::fromMinor($totalAmount),
                    'currency' => $currency,
                ], 201);
            });
        } catch (ApiErrorException $exception) {
            report($exception);

            return response()->json([
                'message' => 'Unable to create the Stripe Checkout session.',
            ], 502);
        }
    }
}
