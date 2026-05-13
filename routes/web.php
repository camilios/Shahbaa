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
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::apiResources([
    'users' => UserController::class,
    'roles' => RoleController::class,
    'trips' => TripController::class,
    'checkpoints' => CheckpointController::class,
    'bookings' => BookingController::class,
    'ratings' => RatingController::class,
    'complaints' => ComplaintController::class,
    'private-trip-requests' => PrivateTripRequestController::class,
    'waiting-lists' => WaitingListController::class,
    'driver-requests' => DriverRequestController::class,
    'driver-checkpoint-logs' => DriverCheckpointLogController::class,
    'scourings' => ScouringController::class,
]);
