<?php

namespace App\Http\Controllers;

use App\Models\DeviceToken;
use Illuminate\Http\Request;

class DeviceTokenController extends Controller
{
    public function index(Request $request)
    {
        return $request->user()->deviceTokens()->latest()->get([
            'id', 'platform', 'device_name', 'app_version', 'last_used_at', 'created_at',
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:512'],
            'platform' => ['required', 'in:android,ios,web'],
            'device_name' => ['nullable', 'string', 'max:255'],
            'app_version' => ['nullable', 'string', 'max:50'],
        ]);

        $deviceToken = DeviceToken::updateOrCreate(
            ['token' => $data['token']],
            [
                ...$data,
                'user_id' => $request->user()->id,
                'last_used_at' => now(),
            ]
        );

        return response()->json([
            'message' => 'Device token registered.',
            'device_token' => $deviceToken,
        ], $deviceToken->wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(Request $request, DeviceToken $deviceToken)
    {
        abort_unless($deviceToken->user_id === $request->user()->id, 404);
        $deviceToken->delete();

        return response()->noContent();
    }
}
