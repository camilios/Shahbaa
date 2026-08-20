<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use UnexpectedValueException;

class StripeWebhookController extends Controller
{
    public function __invoke(Request $request)
    {
        $webhookSecret = config('services.stripe.webhook_secret');

        if (blank($webhookSecret)) {
            return response()->json([
                'message' => 'Stripe webhook secret is not configured.',
            ], 500);
        }

        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');

        if (blank($signature)) {
            return response()->json([
                'message' => 'Stripe signature is missing.',
            ], 400);
        }

        try {
            $event = Webhook::constructEvent(
                $payload,
                $signature,
                $webhookSecret
            );
        } catch (UnexpectedValueException|SignatureVerificationException $exception) {
            return response()->json([
                'message' => 'Invalid Stripe webhook.',
            ], 400);
        }

        if (! in_array($event->type, [
            'checkout.session.completed',
            'checkout.session.async_payment_succeeded',
        ], true)) {
            return response()->json([
                'message' => 'Stripe event received.',
            ]);
        }

        $session = $event->data->object;

        if ($session->payment_status !== 'paid') {
            return response()->json([
                'message' => 'Checkout session is not paid yet.',
            ]);
        }

        $bookingId = $session->metadata->booking_id ?? null;
        $userId = $session->metadata->user_id ?? null;
        $expectedAmountMinor = $session->metadata->amount_minor ?? null;

        if (
            blank($bookingId)
            || blank($userId)
            || blank($expectedAmountMinor)
        ) {
            return response()->json([
                'message' => 'Required Stripe metadata is missing.',
            ], 400);
        }

        if ((int) $session->amount_total !== (int) $expectedAmountMinor) {
            return response()->json([
                'message' => 'Stripe payment amount does not match.',
            ], 400);
        }

        DB::transaction(function () use ($session, $bookingId, $userId) {
            $booking = Booking::lockForUpdate()->findOrFail($bookingId);

            if (
                $booking->payment_status === 'paid'
                && $booking->payment_method === 'stripe'
                && $booking->payment_reference === $session->id
            ) {
                return;
            }

            if (
                $booking->payment_status !== 'unpaid'
                || $booking->status !== 'pending'
            ) {
                return;
            }

            if ((int) $booking->user_id !== (int) $userId) {
                return;
            }

            if ($booking->payment_reference !== $session->id) {
                return;
            }

            $booking->update([
                'status' => 'confirmed',
                'payment_method' => 'stripe',
                'payment_status' => 'paid',
                'paid_amount' => ((int) $session->amount_total) / 100,
                'paid_at' => now(),
                'payment_reference' => $session->id,
            ]);
        });

        return response()->json([
            'message' => 'Stripe webhook processed successfully.',
        ]);
    }
}
