<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\CheckpointController;
use App\Http\Controllers\ComplaintController;
use App\Http\Controllers\DriverCheckpointLogController;
use App\Http\Controllers\DriverRequestController;
use App\Http\Controllers\PrivateTripRequestController;
use App\Http\Controllers\RatingController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ScouringController;
use App\Http\Controllers\TripController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WaitingListController;
use Illuminate\Support\Facades\Route;

Route::middleware('api')->group(function () {

Route::post('/register', [AuthController::class , 'register']);
Route::post('/login', [AuthController::class , 'login']);
Route::post('/logout', [AuthController::class , 'logout']);
Route::post('/deleted', [AuthController::class , 'deleted']);

    Route::get('users', [UserController::class, 'index']);
    Route::get('users/{user}', [UserController::class, 'show']);
    Route::post('users', [UserController::class, 'store']);
    Route::put('users/{user}', [UserController::class, 'update']);
    Route::post('update_profile', [UserController::class, 'update_profile']);
    Route::post('profile', [UserController::class, 'profile']);
    Route::post('qr', [UserController::class, 'Qr']);
    Route::delete('users/{user}', [UserController::class, 'destroy']);

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

    Route::get('trips', [TripController::class, 'index']);
    Route::get('trips/{trip}', [TripController::class, 'show']);
    Route::post('trips', [TripController::class, 'store']);
    Route::put('trips/{trip}', [TripController::class, 'update']);
    Route::patch('trips/{trip}', [TripController::class, 'update']);
    Route::delete('trips/{trip}', [TripController::class, 'destroy']);
    Route::get('index_user_trip', [TripController::class, 'index_user_trip']);
    Route::get('index_gov', [TripController::class, 'index_gov']);




    Route::post('index_droppoff_pickup', [CheckpointController::class, 'index_droppoff_pickup']);
    Route::get('checkpoints/{checkpoint}', [CheckpointController::class, 'show']);
    Route::post('checkpoints', [CheckpointController::class, 'store']);
    Route::put('checkpoints/{checkpoint}', [CheckpointController::class, 'update']);
    Route::patch('checkpoints/{checkpoint}', [CheckpointController::class, 'update']);
    Route::delete('checkpoints/{checkpoint}', [CheckpointController::class, 'destroy']);
    Route::post('details_not_scan', [CheckpointController::class, 'details_not_scan']);
    Route::post('details_scan', [CheckpointController::class, 'details_scan']);



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

        // Customer booking aliases and search endpoints from mohamd branch.
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

        Route::get('rating_index', [
            RatingController::class,
            'index',
        ]);

        Route::get('ratings/{rating}', [
            RatingController::class,
            'show',
        ]);

        Route::post('rating_store', [
            RatingController::class,
            'store',
        ]);

        Route::post('rating_update', [
            RatingController::class,
            'update',
        ]);

        // Route::patch('ratings/{rating}', [
        //     RatingController::class,
        //     'update',
        // ]);

        Route::delete('rating_delete', [
            RatingController::class,
            'destroy',
        ]);

        Route::get('complaint_index', [
            ComplaintController::class,
            'index',
        ]);

        Route::get('complaint', [
            ComplaintController::class,
            'show',
        ]);

        Route::post('complaint_store', [
            ComplaintController::class,
            'store',
        ]);

        Route::post('complaint_update', [
            ComplaintController::class,
            'update',
        ]);

        // Route::patch('complaints/{complaint}', [
        //     ComplaintController::class,
        //     'update',
        // ]);

        Route::delete('complaint_delete', [
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

           Route::post('insert_to_waitingList', [
            WaitingListController::class,
            'insert_to_waitingList',
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

    Route::get('scourings', [ScouringController::class, 'index']);
    Route::get('scourings/{scouring}', [ScouringController::class, 'show']);
    Route::post('scourings', [ScouringController::class, 'store']);
    Route::put('scourings/{scouring}', [ScouringController::class, 'update']);
    Route::patch('scourings/{scouring}', [ScouringController::class, 'update']);
    Route::delete('scourings/{scouring}', [ScouringController::class, 'destroy']);
});

