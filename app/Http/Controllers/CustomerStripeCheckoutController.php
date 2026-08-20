<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class CustomerStripeCheckoutController extends Controller
{
    public function __invoke(Request $request, Booking $booking)
    {
        abort_unless(
            strtolower((string) $request->user()->role) === 'customer',
            403
        );

        abort_unless(
            $booking->user_id === $request->user()->id,
            404
        );

        try {
            return DB::transaction(function () use ($request, $booking) {
                $lockedBooking = Booking::with('trip')
                    ->lockForUpdate()
                    ->findOrFail($booking->id);

                if (
                    $lockedBooking->payment_status === 'paid'
                    && $lockedBooking->payment_method === 'stripe'
                ) {
                    return response()->json([
                        'message' => 'Booking was already paid with Stripe.',
                        'booking' => $lockedBooking,
                    ]);
                }

                if (
                    $lockedBooking->status !== 'pending'
                    || $lockedBooking->payment_status !== 'unpaid'
                ) {
                    throw ValidationException::withMessages([
                        'booking' => [
                            'Only pending unpaid bookings can be paid with Stripe.',
                        ],
                    ]);
                }

                if (
                    ! $lockedBooking->trip?->departure_date
                    || $lockedBooking->trip->departure_date->lte(now()->addHour())
                ) {
                    throw ValidationException::withMessages([
                        'trip' => [
                            'Payment must be completed at least one hour before departure.',
                        ],
                    ]);
                }

                if (
                    in_array(
                        $lockedBooking->trip->status,
                        ['cancelled', 'canceled', 'completed'],
                        true
                    )
                ) {
                    throw ValidationException::withMessages([
                        'trip' => [
                            'This trip is not available for payment.',
                        ],
                    ]);
                }

                $unitAmount = (int) round(
                    (float) $lockedBooking->trip->money_price * 100
                );

                $quantity = (int) $lockedBooking->seats_count;
                $totalAmount = $unitAmount * $quantity;

                if ($unitAmount <= 0 || $quantity <= 0) {
                    throw ValidationException::withMessages([
                        'amount' => [
                            'This booking does not have a valid monetary price.',
                        ],
                    ]);
                }

                $stripe = new StripeClient(
                    config('services.stripe.secret')
                );

                $baseUrl = rtrim(config('app.url'), '/');

                $session = $stripe->checkout->sessions->create([
                    'mode' => 'payment',

                    'payment_method_types' => ['card'],

                    'customer_email' => $request->user()->email,

                    'success_url' => $baseUrl
                        .'/api/stripe/success'
                        .'?session_id={CHECKOUT_SESSION_ID}',

                    'cancel_url' => $baseUrl
                        .'/api/stripe/cancel'
                        .'?booking_id='.$lockedBooking->id,

                    'line_items' => [
                        [
                            'price_data' => [
                                'currency' => config(
                                    'services.stripe.currency',
                                    'usd'
                                ),

                                'unit_amount' => $unitAmount,

                                'product_data' => [
                                    'name' => 'Shahbaa trip booking #'
                                        .$lockedBooking->id,

                                    'description' => 'Payment for '
                                        .$quantity.' seat(s)',
                                ],
                            ],

                            'quantity' => $quantity,
                        ],
                    ],

                    'metadata' => [
                        'booking_id' => (string) $lockedBooking->id,
                        'user_id' => (string) $request->user()->id,
                        'amount_minor' => (string) $totalAmount,
                    ],
                ], [
                    'idempotency_key' => 'booking-checkout-'
                        .$lockedBooking->id,
                ]);

                $lockedBooking->update([
                    'payment_reference' => $session->id,
                ]);

                return response()->json([
                    'message' => 'Stripe Checkout session created successfully.',
                    'checkout_url' => $session->url,
                    'session_id' => $session->id,
                    'amount' => number_format($totalAmount / 100, 2, '.', ''),
                    'currency' => config(
                        'services.stripe.currency',
                        'usd'
                    ),
                    'booking' => $lockedBooking->fresh()->load('trip'),
                ], 201);
            });
        } catch (ApiErrorException $exception) {
            report($exception);

            return response()->json([
                'message' => 'Unable to create the Stripe Checkout session.',
                'stripe_error' => $exception->getMessage(),
            ], 502);
        }
    }
}
