<?php

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
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/profile', [AuthController::class, 'profile']);
    });

    Route::get('users', [UserController::class, 'index']);
    Route::get('users/{user}', [UserController::class, 'show']);
    Route::post('users', [UserController::class, 'store']);
    Route::put('users/{user}', [UserController::class, 'update']);
    Route::patch('users/{user}', [UserController::class, 'update']);
    Route::delete('users/{user}', [UserController::class, 'destroy']);

    Route::get('roles', [RoleController::class, 'index']);
    Route::get('roles/{role}', [RoleController::class, 'show']);
    Route::post('roles', [RoleController::class, 'store']);
    Route::put('roles/{role}', [RoleController::class, 'update']);
    Route::patch('roles/{role}', [RoleController::class, 'update']);
    Route::delete('roles/{role}', [RoleController::class, 'destroy']);

    Route::get('trips', [TripController::class, 'index']);
    Route::get('trips/{trip}', [TripController::class, 'show']);
    Route::post('trips', [TripController::class, 'store']);
    Route::put('trips/{trip}', [TripController::class, 'update']);
    Route::patch('trips/{trip}', [TripController::class, 'update']);
    Route::delete('trips/{trip}', [TripController::class, 'destroy']);

    Route::get('checkpoints', [CheckpointController::class, 'index']);
    Route::get('checkpoints/{checkpoint}', [CheckpointController::class, 'show']);
    Route::post('checkpoints', [CheckpointController::class, 'store']);
    Route::put('checkpoints/{checkpoint}', [CheckpointController::class, 'update']);
    Route::patch('checkpoints/{checkpoint}', [CheckpointController::class, 'update']);
    Route::delete('checkpoints/{checkpoint}', [CheckpointController::class, 'destroy']);

    Route::get('bookings', [BookingController::class, 'index']);
    Route::get('bookings/{booking}', [BookingController::class, 'show']);
    Route::post('bookings', [BookingController::class, 'store']);
    Route::put('bookings/{booking}', [BookingController::class, 'update']);
    Route::patch('bookings/{booking}', [BookingController::class, 'update']);
    Route::delete('bookings/{booking}', [BookingController::class, 'destroy']);

    Route::get('ratings', [RatingController::class, 'index']);
    Route::get('ratings/{rating}', [RatingController::class, 'show']);
    Route::post('ratings', [RatingController::class, 'store']);
    Route::put('ratings/{rating}', [RatingController::class, 'update']);
    Route::patch('ratings/{rating}', [RatingController::class, 'update']);
    Route::delete('ratings/{rating}', [RatingController::class, 'destroy']);

    Route::get('complaints', [ComplaintController::class, 'index']);
    Route::get('complaints/{complaint}', [ComplaintController::class, 'show']);
    Route::post('complaints', [ComplaintController::class, 'store']);
    Route::put('complaints/{complaint}', [ComplaintController::class, 'update']);
    Route::patch('complaints/{complaint}', [ComplaintController::class, 'update']);
    Route::delete('complaints/{complaint}', [ComplaintController::class, 'destroy']);

    Route::get('private-trip-requests', [PrivateTripRequestController::class, 'index']);
    Route::get('private-trip-requests/{private_trip_request}', [PrivateTripRequestController::class, 'show']);
    Route::post('private-trip-requests', [PrivateTripRequestController::class, 'store']);
    Route::put('private-trip-requests/{private_trip_request}', [PrivateTripRequestController::class, 'update']);
    Route::patch('private-trip-requests/{private_trip_request}', [PrivateTripRequestController::class, 'update']);
    Route::delete('private-trip-requests/{private_trip_request}', [PrivateTripRequestController::class, 'destroy']);

    Route::get('waiting-lists', [WaitingListController::class, 'index']);
    Route::get('waiting-lists/{waiting_list}', [WaitingListController::class, 'show']);
    Route::post('waiting-lists', [WaitingListController::class, 'store']);
    Route::put('waiting-lists/{waiting_list}', [WaitingListController::class, 'update']);
    Route::patch('waiting-lists/{waiting_list}', [WaitingListController::class, 'update']);
    Route::delete('waiting-lists/{waiting_list}', [WaitingListController::class, 'destroy']);

    Route::get('driver-requests', [DriverRequestController::class, 'index']);
    Route::get('driver-requests/{driver_request}', [DriverRequestController::class, 'show']);
    Route::post('driver-requests', [DriverRequestController::class, 'store']);
    Route::put('driver-requests/{driver_request}', [DriverRequestController::class, 'update']);
    Route::patch('driver-requests/{driver_request}', [DriverRequestController::class, 'update']);
    Route::delete('driver-requests/{driver_request}', [DriverRequestController::class, 'destroy']);

    Route::get('driver-checkpoint-logs', [DriverCheckpointLogController::class, 'index']);
    Route::get('driver-checkpoint-logs/{driver_checkpoint_log}', [DriverCheckpointLogController::class, 'show']);
    Route::post('driver-checkpoint-logs', [DriverCheckpointLogController::class, 'store']);
    Route::put('driver-checkpoint-logs/{driver_checkpoint_log}', [DriverCheckpointLogController::class, 'update']);
    Route::patch('driver-checkpoint-logs/{driver_checkpoint_log}', [DriverCheckpointLogController::class, 'update']);
    Route::delete('driver-checkpoint-logs/{driver_checkpoint_log}', [DriverCheckpointLogController::class, 'destroy']);

    Route::get('scourings', [ScouringController::class, 'index']);
    Route::get('scourings/{scouring}', [ScouringController::class, 'show']);
    Route::post('scourings', [ScouringController::class, 'store']);
    Route::put('scourings/{scouring}', [ScouringController::class, 'update']);
    Route::patch('scourings/{scouring}', [ScouringController::class, 'update']);
    Route::delete('scourings/{scouring}', [ScouringController::class, 'destroy']);
