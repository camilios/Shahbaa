<?php

namespace App\Http\Controllers;

use App\Http\Requests\ScouringRequest;
use App\Models\Scouring;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ScouringController extends Controller
{
  public function index()
    {
        try {
            $scourings = Scouring::with([
                'driverCheckpointLog',
                'customer',
                'booking'
            ])->get();

            return response()->json([
                'message' => 'Scourings retrieved successfully.',
                'data' => $scourings
            ], 200);

        } catch (\Exception $e) {

            return response()->json([
                'message' => 'Failed to retrieve scourings.',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Store a newly created scouring.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'driver_checkpoint_log_id' => 'required|integer|exists:driver_checkpoint_logs,id',
            'customer_id' => 'required|integer|exists:users,id',
            'booking_id' => 'required|integer|exists:bookings,id',
            'points' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error.',
                'errors' => $validator->errors()
            ], 422);
        }

        try {

            $scouring = Scouring::create([
                'driver_checkpoint_log_id' => $request->driver_checkpoint_log_id,
                'customer_id' => $request->customer_id,
                'booking_id' => $request->booking_id,
                'points' => $request->points,
            ]);

            $scouring->load([
                'driverCheckpointLog',
                'customer',
                'booking'
            ]);

            return response()->json([
                'message' => 'Scouring created successfully.',
                'data' => $scouring
            ], 201);

        } catch (\Exception $e) {

            return response()->json([
                'message' => 'Failed to create scouring.',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Display the specified scouring.
     */
    public function show(Request $id)
    {
        try {

            $scouring = Scouring::with([
                'driverCheckpointLog',
                'customer',
                'booking'
            ])->find($id);

            if (!$scouring) {
                return response()->json([
                    'message' => 'Scouring not found.'
                ], 404);
            }

            return response()->json([
                'message' => 'Scouring retrieved successfully.',
                'data' => $scouring
            ], 200);

        } catch (\Exception $e) {

            return response()->json([
                'message' => 'Failed to retrieve scouring.',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Update the specified scouring.
     */
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'driver_checkpoint_log_id' => 'sometimes|required|integer|exists:driver_checkpoint_logs,id',
            'customer_id' => 'sometimes|required|integer|exists:users,id',
            'booking_id' => 'sometimes|required|integer|exists:bookings,id',
            'points' => 'sometimes|required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error.',
                'errors' => $validator->errors()
            ], 422);
        }

        try {

            $scouring = Scouring::find($request->id);

            if (!$scouring) {
                return response()->json([
                    'message' => 'Scouring not found.'
                ], 404);
            }

            $scouring->update($request->only([
                'driver_checkpoint_log_id',
                'customer_id',
                'booking_id',
                'points'
            ]));

            $scouring->load([
                'driverCheckpointLog',
                'customer',
                'booking'
            ]);

            return response()->json([
                'message' => 'Scouring updated successfully.',
                'data' => $scouring
            ], 200);

        } catch (\Exception $e) {

            return response()->json([
                'message' => 'Failed to update scouring.',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Remove the specified scouring.
     */
    public function destroy(Request $request)
    {
        try {

            $scouring = Scouring::find($request->id);

            if (!$scouring) {
                return response()->json([
                    'message' => 'Scouring not found.'
                ], 404);
            }

            $scouring->delete();

            return response()->json([
                'message' => 'Scouring deleted successfully.'
            ], 200);

        } catch (\Exception $e) {

            return response()->json([
                'message' => 'Failed to delete scouring.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
