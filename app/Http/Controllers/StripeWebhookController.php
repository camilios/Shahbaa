<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use UnexpectedValueException;

class StripeWebhookController extends Controller
{
    public function __invoke(Request $request)
    {
        $secret = config('services.stripe.webhook_secret');
        if (blank($secret)) {
            return response()->json(['message' => 'Stripe webhook secret is not configured.'], 500);
        }

        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');
        if (blank($signature)) {
            return response()->json(['message' => 'Stripe signature is missing.'], 400);
        }

        try {
            $event = Webhook::constructEvent($payload, $signature, $secret);
        } catch (UnexpectedValueException|SignatureVerificationException) {
            return response()->json(['message' => 'Invalid Stripe webhook.'], 400);
        }

        if (! in_array($event->type, [
            'checkout.session.completed',
            'checkout.session.async_payment_succeeded',
        ], true)) {
            return response()->json(['message' => 'Stripe event received.']);
        }

        $session = $event->data->object;
        if (($session->payment_status ?? null) !== 'paid') {
            return response()->json(['message' => 'Checkout session is not paid yet.']);
        }

        $bookingId = $session->metadata->booking_id ?? null;
        $userId = $session->metadata->user_id ?? null;
        $metadataAmount = $session->metadata->amount_minor ?? null;

        if (blank($bookingId) || blank($userId) || blank($metadataAmount)) {
            return response()->json(['message' => 'Required Stripe metadata is missing.'], 400);
        }

        if (! ctype_digit((string) $bookingId)
            || ! ctype_digit((string) $userId)
            || ! ctype_digit((string) $metadataAmount)
            || ! isset($session->amount_total, $session->currency, $session->id)) {
            return response()->json(['message' => 'Stripe checkout data is invalid.'], 400);
        }

        if ((int) $session->amount_total !== (int) $metadataAmount) {
            return response()->json(['message' => 'Stripe payment amount does not match.'], 400);
        }

        $result = DB::transaction(function () use ($session, $bookingId, $userId) {
            $booking = Booking::with('trip')->lockForUpdate()->find($bookingId);
            if (! $booking) {
                return 'not_found';
            }

            if ($booking->payment_status === 'paid'
                && $booking->payment_method === 'stripe'
                && $booking->payment_reference === $session->id) {
                return 'duplicate';
            }

            if ($booking->payment_status !== 'unpaid' || $booking->status !== 'pending') {
                return 'invalid_state';
            }

            if ((int) $booking->user_id !== (int) $userId) {
                return 'user_mismatch';
            }

            if ($booking->payment_reference !== $session->id) {
                return 'session_mismatch';
            }

            $currency = strtolower((string) config('services.stripe.currency', 'usd'));
            if (strtolower((string) $session->currency) !== $currency) {
                return 'currency_mismatch';
            }

            if (! $booking->trip) {
                return 'trip_missing';
            }

            try {
                $databaseAmount = Money::toMinor($booking->trip->money_price)
                    * (int) $booking->seats_count;
            } catch (InvalidArgumentException) {
                return 'amount_mismatch';
            }

            if ($databaseAmount <= 0 || $databaseAmount !== (int) $session->amount_total) {
                return 'amount_mismatch';
            }

            $booking->update([
                'status' => 'confirmed',
                'payment_method' => 'stripe',
                'payment_status' => 'paid',
                'paid_amount' => Money::fromMinor((int) $session->amount_total),
                'paid_at' => now(),
                'payment_reference' => $session->id,
            ]);

            return 'processed';
        });

        if ($result === 'not_found') {
            return response()->json(['message' => 'Booking was not found; event ignored.']);
        }

        if ($result === 'duplicate') {
            return response()->json(['message' => 'Stripe webhook was already processed.']);
        }

        if ($result !== 'processed') {
            return response()->json([
                'message' => 'Stripe checkout data does not match the booking.',
            ], 400);
        }

        return response()->json(['message' => 'Stripe webhook processed successfully.']);
    }
}
