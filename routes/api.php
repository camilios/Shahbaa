<?php

use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\AdminBookingConfirmationController;
use App\Http\Controllers\AdminGuestBookingController;
use App\Http\Controllers\AdminPointAuditLogController;
use App\Http\Controllers\AdminReportsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\CheckpointController;
use App\Http\Controllers\ComplaintController;
use App\Http\Controllers\CustomerTripController;
use App\Http\Controllers\CustomerPointWalletController;
use App\Http\Controllers\CustomerPointsPaymentController;
use App\Http\Controllers\DeviceTokenController;
use App\Http\Controllers\DriverAuthController;
use App\Http\Controllers\DriverCheckpointLogController;
use App\Http\Controllers\DriverRequestController;
use App\Http\Controllers\DriverTripCheckpointController;
use App\Http\Controllers\DriverTripController;
use App\Http\Controllers\DriverTripObjectionController;
use App\Http\Controllers\DriverTripPassengerController;
use App\Http\Controllers\DriverTripScanController;
use App\Http\Controllers\PrivateTripRequestController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\RatingController;
use App\Http\Controllers\RealtimeTrackingAuthorizationController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ScouringController;
use App\Http\Controllers\TripController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WaitingListController;
use App\Http\Controllers\CustomerStripeCheckoutController;
use Illuminate\Support\Facades\Route;

Route::middleware('api')->group(function () {
    // Admin dashboard auth: login and token-scoped logout.
    Route::post('admin/login', [AdminAuthController::class, 'login']);

    // Driver app auth: login only (no self-registration), logout, profile.
    Route::post('driver/login', [DriverAuthController::class, 'login']);

    // Customer app auth.
    Route::post('login', [AuthController::class, 'login']);
    Route::post('register', [AuthController::class, 'register']);

    // Stripe calls this endpoint directly, so it must remain outside auth:sanctum.
    Route::post('stripe/webhook', StripeWebhookController::class);

    Route::get('stripe/success', function () {
        return response()->json([
            'message' => 'Payment completed. Confirmation is being processed.',
        ]);
    });

    Route::get('stripe/cancel', function () {
        return response()->json([
            'message' => 'Stripe payment was cancelled.',
        ]);
    });

    Route::middleware(['auth:sanctum', 'active'])->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);

        Route::get('notifications', [NotificationController::class, 'index']);
        Route::patch('notifications/read-all', [NotificationController::class, 'readAll']);
        Route::patch('notifications/{notification}/read', [NotificationController::class, 'read']);

        Route::get('device-tokens', [DeviceTokenController::class, 'index']);
        Route::post('device-tokens', [DeviceTokenController::class, 'store']);
        Route::delete('device-tokens/{deviceToken}', [DeviceTokenController::class, 'destroy']);

        // Customer application read models and ticket state.
        Route::get('customer/trips', [CustomerTripController::class, 'myTrips']);
        Route::get('customer/governorates', [CustomerTripController::class, 'governorates']);
        Route::post('customer/checkpoints/by-governorate', [CustomerTripController::class, 'checkpointsByGovernorate']);
        Route::get('customer/points/wallet', [CustomerPointWalletController::class, 'show']);
        Route::get('customer/points/transactions', [CustomerPointWalletController::class, 'transactions']);
        Route::post('customer/bookings/{booking}/pay-with-points', CustomerPointsPaymentController::class);
        Route::post('customer/bookings/{booking}/stripe-checkout', CustomerStripeCheckoutController::class);
        Route::post('customer/trips/{trip}/ticket/before-scan', [CustomerTripController::class, 'detailsBeforeScan']);
        Route::post('customer/trips/{trip}/ticket/after-scan', [CustomerTripController::class, 'detailsAfterScan']);

        // Backward-compatible aliases used by the current customer frontend.
        Route::get('index_user_trip', [CustomerTripController::class, 'myTrips']);
        Route::get('index_gov', [CustomerTripController::class, 'governorates']);
        Route::post('index_droppoff_pickup', [CustomerTripController::class, 'checkpointsByGovernorate']);
        Route::post('details_not_scan', [CustomerTripController::class, 'detailsBeforeScanLegacy']);
        Route::post('details_scan', [CustomerTripController::class, 'detailsAfterScanLegacy']);

        Route::post('customer/waiting-list', [WaitingListController::class, 'storeForCurrentUser']);
        Route::delete('customer/waiting-list/{trip}', [WaitingListController::class, 'leaveCurrentUser']);
        Route::post('insert_to_waitingList', [WaitingListController::class, 'storeForCurrentUser']);
        Route::post('trip_wating_book', [WaitingListController::class, 'leaveCurrentUserLegacy']);

        Route::get('customer/ratings', [RatingController::class, 'mine']);
        Route::post('customer/ratings', [RatingController::class, 'storeForCurrentUser']);
        Route::patch('customer/ratings/{rating}', [RatingController::class, 'updateForCurrentUser']);
        Route::delete('customer/ratings/{rating}', [RatingController::class, 'destroyForCurrentUser']);

        Route::get('customer/complaints', [ComplaintController::class, 'mine']);
        Route::post('customer/complaints', [ComplaintController::class, 'storeForCurrentUser']);
        Route::patch('customer/complaints/{complaint}', [ComplaintController::class, 'updateForCurrentUser']);
        Route::delete('customer/complaints/{complaint}', [ComplaintController::class, 'destroyForCurrentUser']);

        Route::post('admin/logout', [AdminAuthController::class, 'logout']);

        Route::post(
            'v1/realtime/authorize',
            RealtimeTrackingAuthorizationController::class
        )->middleware('tracking.service');

        Route::post('driver/logout', [
            DriverAuthController::class,
            'logout',
        ]);

        Route::get('driver/profile', [
            DriverAuthController::class,
            'profile',
        ]);

        // Trip schedules list + trip details (scoped to the driver's own trips).
        Route::get('driver/trips', [
            DriverTripController::class,
            'index',
        ]);

        Route::get('driver/trips/{trip}', [
            DriverTripController::class,
            'show',
        ]);

        // Trip location tracking: driver checks in at route checkpoints so
        // the admin can follow where the trip has reached.
        Route::get('driver/trips/{trip}/checkpoints', [
            DriverTripCheckpointController::class,
            'index',
        ]);

        Route::post('driver/trips/{trip}/checkpoints', [
            DriverTripCheckpointController::class,
            'store',
        ]);

        // Passenger names booked on the driver's trip.
        Route::get('driver/trips/{trip}/passengers', [
            DriverTripPassengerController::class,
            'index',
        ]);

        // Driver files an objection against a trip's schedule (with a reason).
        Route::get('driver/trips/{trip}/objections', [
            DriverTripObjectionController::class,
            'index',
        ]);

        Route::post('driver/trips/{trip}/objections', [
            DriverTripObjectionController::class,
            'store',
        ]);

        // Driver scans a passenger's QR ticket to verify the booking and
        // mark the passenger as boarded.
        Route::post('driver/trips/{trip}/scan', [
            DriverTripScanController::class,
            'store',
        ]);
    });

    Route::middleware(['auth:sanctum', 'active'])->group(function () {
        // Customer profile endpoints added from mohamd branch.
        Route::post('update_profile', [
            UserController::class,
            'update_profile',
        ]);

        Route::get('profile', [
            UserController::class,
            'profile',
        ]);

        Route::post('qr', [
            UserController::class,
            'Qr',
        ]);

        Route::middleware('admin')->group(function () {
            Route::post('admin/bookings/{booking}/confirm', AdminBookingConfirmationController::class);
            Route::post('admin/guest-bookings', AdminGuestBookingController::class);
            Route::get('admin/point-audit-logs', AdminPointAuditLogController::class);
            Route::get('admin/reports', [AdminReportsController::class, 'index']);
            Route::get('admin/reports/export', [AdminReportsController::class, 'export']);

            Route::get('drivers', [
                UserController::class,
                'drivers',
            ]);

            Route::patch('users/{user}/block', [
                UserController::class,
                'block',
            ]);

            Route::patch('users/{user}/unblock', [
                UserController::class,
                'unblock',
            ]);

            Route::post('ratings/{rating}/reply', [
                RatingController::class,
                'reply',
            ]);

            Route::post('complaints/{complaint}/reply', [
                ComplaintController::class,
                'reply',
            ]);

            Route::post(
                'private-trip-requests/{private_trip_request}/approve',
                [PrivateTripRequestController::class, 'approve']
            );

            Route::post(
                'private-trip-requests/{private_trip_request}/reject',
                [PrivateTripRequestController::class, 'reject']
            );
        });

        Route::get('users', [
            UserController::class,
            'index',
        ]);

        Route::get('users/{user}', [
            UserController::class,
            'show',
        ]);

        Route::post('users', [
            UserController::class,
            'store',
        ])->middleware('admin');

        Route::put('users/{user}', [
            UserController::class,
            'update',
        ])->middleware('admin');

        Route::patch('users/{user}', [
            UserController::class,
            'update',
        ])->middleware('admin');

        Route::delete('users/{user}', [
            UserController::class,
            'destroy',
        ])->middleware('admin');

        Route::get('roles', [
            RoleController::class,
            'index',
        ]);

        Route::get('roles/{role}', [
            RoleController::class,
            'show',
        ]);

        Route::post('roles', [
            RoleController::class,
            'store',
        ]);

        Route::put('roles/{role}', [
            RoleController::class,
            'update',
        ]);

        Route::patch('roles/{role}', [
            RoleController::class,
            'update',
        ]);

        Route::delete('roles/{role}', [
            RoleController::class,
            'destroy',
        ]);

        Route::get('trips', [
            TripController::class,
            'index',
        ]);

        Route::get('trips/{trip}', [
            TripController::class,
            'show',
        ]);

        Route::post('trips', [
            TripController::class,
            'store',
        ])->middleware('admin');

        Route::put('trips/{trip}', [
            TripController::class,
            'update',
        ])->middleware('admin');

        Route::patch('trips/{trip}', [
            TripController::class,
            'update',
        ])->middleware('admin');

        Route::delete('trips/{trip}', [
            TripController::class,
            'destroy',
        ])->middleware('admin');

        Route::get('checkpoints', [
            CheckpointController::class,
            'index',
        ]);

        Route::get('checkpoints/{checkpoint}', [
            CheckpointController::class,
            'show',
        ]);

        Route::post('checkpoints', [
            CheckpointController::class,
            'store',
        ])->middleware('admin');

        Route::put('checkpoints/{checkpoint}', [
            CheckpointController::class,
            'update',
        ])->middleware('admin');

        Route::patch('checkpoints/{checkpoint}', [
            CheckpointController::class,
            'update',
        ])->middleware('admin');

        Route::delete('checkpoints/{checkpoint}', [
            CheckpointController::class,
            'destroy',
        ])->middleware('admin');

        Route::get('bookings', [
            BookingController::class,
            'index',
        ]);

        Route::get('bookings/{booking}', [
            BookingController::class,
            'show',
        ]);

        Route::post('bookings', [
            BookingController::class,
            'store',
        ]);

        Route::put('bookings/{booking}', [
            BookingController::class,
            'update',
        ]);

        Route::patch('bookings/{booking}', [
            BookingController::class,
            'update',
        ]);

        Route::delete('bookings/{booking}', [
            BookingController::class,
            'destroy',
        ]);

        Route::post('storebooking', [
            BookingController::class,
            'store',
        ]);

        Route::post('updatebooking/{booking}', [
            BookingController::class,
            'update',
        ]);

        Route::post('index_trip_time', [
            BookingController::class,
            'index_trip_time',
        ]);

        Route::post('Index_droppoff', [
            BookingController::class,
            'Index_droppoff',
        ]);

        Route::post('Index_pickup', [
            BookingController::class,
            'Index_pickup',
        ]);

        Route::delete('deletebooking/{booking}', [
            BookingController::class,
            'destroy',
        ]);

        Route::get('ratings', [
            RatingController::class,
            'index',
        ]);

        Route::get('ratings/{rating}', [
            RatingController::class,
            'show',
        ]);

        Route::post('ratings', [
            RatingController::class,
            'store',
        ]);

        Route::put('ratings/{rating}', [
            RatingController::class,
            'update',
        ]);

        Route::patch('ratings/{rating}', [
            RatingController::class,
            'update',
        ]);

        Route::delete('ratings/{rating}', [
            RatingController::class,
            'destroy',
        ]);

        Route::get('complaints', [
            ComplaintController::class,
            'index',
        ]);

        Route::get('complaints/{complaint}', [
            ComplaintController::class,
            'show',
        ]);

        Route::post('complaints', [
            ComplaintController::class,
            'store',
        ]);

        Route::put('complaints/{complaint}', [
            ComplaintController::class,
            'update',
        ]);

        Route::patch('complaints/{complaint}', [
            ComplaintController::class,
            'update',
        ]);

        Route::delete('complaints/{complaint}', [
            ComplaintController::class,
            'destroy',
        ]);

        Route::get('private-trip-requests', [
            PrivateTripRequestController::class,
            'index',
        ]);

        Route::get(
            'private-trip-requests/{private_trip_request}',
            [PrivateTripRequestController::class, 'show']
        );

        Route::post('private-trip-requests', [
            PrivateTripRequestController::class,
            'store',
        ]);

        Route::put(
            'private-trip-requests/{private_trip_request}',
            [PrivateTripRequestController::class, 'update']
        );

        Route::patch(
            'private-trip-requests/{private_trip_request}',
            [PrivateTripRequestController::class, 'update']
        );

        Route::delete(
            'private-trip-requests/{private_trip_request}',
            [PrivateTripRequestController::class, 'destroy']
        );

        Route::get('waiting-lists', [
            WaitingListController::class,
            'index',
        ]);

        Route::get('waiting-lists/{waiting_list}', [
            WaitingListController::class,
            'show',
        ]);

        Route::post('waiting-lists', [
            WaitingListController::class,
            'store',
        ]);

        Route::put('waiting-lists/{waiting_list}', [
            WaitingListController::class,
            'update',
        ]);

        Route::patch('waiting-lists/{waiting_list}', [
            WaitingListController::class,
            'update',
        ]);

        Route::delete('waiting-lists/{waiting_list}', [
            WaitingListController::class,
            'destroy',
        ]);

        Route::get('driver-requests', [
            DriverRequestController::class,
            'index',
        ]);

        Route::get('driver-requests/{driver_request}', [
            DriverRequestController::class,
            'show',
        ]);

        Route::post('driver-requests', [
            DriverRequestController::class,
            'store',
        ]);

        Route::put('driver-requests/{driver_request}', [
            DriverRequestController::class,
            'update',
        ]);

        Route::patch('driver-requests/{driver_request}', [
            DriverRequestController::class,
            'update',
        ]);

        Route::delete('driver-requests/{driver_request}', [
            DriverRequestController::class,
            'destroy',
        ]);

        Route::get('driver-checkpoint-logs', [
            DriverCheckpointLogController::class,
            'index',
        ]);

        Route::get(
            'driver-checkpoint-logs/{driver_checkpoint_log}',
            [DriverCheckpointLogController::class, 'show']
        );

        Route::post('driver-checkpoint-logs', [
            DriverCheckpointLogController::class,
            'store',
        ]);

        Route::put(
            'driver-checkpoint-logs/{driver_checkpoint_log}',
            [DriverCheckpointLogController::class, 'update']
        );

        Route::patch(
            'driver-checkpoint-logs/{driver_checkpoint_log}',
            [DriverCheckpointLogController::class, 'update']
        );

        Route::delete(
            'driver-checkpoint-logs/{driver_checkpoint_log}',
            [DriverCheckpointLogController::class, 'destroy']
        );

        Route::get('scourings', [
            ScouringController::class,
            'index',
        ]);

        Route::get('scourings/{scouring}', [
            ScouringController::class,
            'show',
        ]);

        Route::post('scourings', [
            ScouringController::class,
            'store',
        ]);

        Route::put('scourings/{scouring}', [
            ScouringController::class,
            'update',
        ]);

        Route::patch('scourings/{scouring}', [
            ScouringController::class,
            'update',
        ]);

        Route::delete('scourings/{scouring}', [
            ScouringController::class,
            'destroy',
        ]);
    });
});
